<?php

namespace MediaWiki\Tests\Rest\Handler;

use MediaWiki\Rest\Handler\MediaLinksHandler;
use MediaWiki\Rest\LocalizedHttpException;
use MediaWiki\Rest\RequestData;
use RequestContext;
use Title;
use Wikimedia\Message\MessageValue;

/**
 * @covers \MediaWiki\Rest\Handler\MediaLinksHandler
 *
 * @group Database
 */
class MediaLinksHandlerTest extends \MediaWikiIntegrationTestCase {

	use MediaTestTrait;

	public function addDBDataOnce() {
		$this->editPage( __CLASS__ . '_Foo', 'Foo [[Image:Existing.jpg]] [[Image:Missing.jpg]]' );
	}

	private function newHandler() {
		return new MediaLinksHandler(
			$this->getServiceContainer()->getDBLoadBalancerFactory(),
			$this->makeMockRepoGroup( [ 'Existing.jpg' ] ),
			$this->getServiceContainer()->getPageStore()
		);
	}

	private function assertLink( $expected, $actual ) {
		foreach ( $expected as $key => $value ) {
			$this->assertArrayHasKey( $key, $actual );
			$this->assertSame( $value, $actual[$key], $key );
		}
	}

	public function testExecute() {
		$title = __CLASS__ . '_Foo';
		$request = new RequestData( [ 'pathParams' => [ 'title' => $title ] ] );

		$user = RequestContext::getMain()->getUser();
		$userOptionsManager = $this->getServiceContainer()->getUserOptionsManager();
		$this->setMwGlobals( 'wgImageLimits', [
			$userOptionsManager->getIntOption( $user, 'imagesize' ) => [ 100, 100 ],
		] );

		$handler = $this->newHandler();
		$data = $this->executeHandlerAndGetBodyData( $handler, $request );

		$this->assertArrayHasKey( 'files', $data );
		$this->assertCount( 2, $data['files'] );

		$links = [];
		foreach ( $data['files'] as $row ) {
			$links[$row['title']] = $row;
		}

		$this->assertArrayHasKey( 'Existing.jpg', $links );
		$this->assertArrayHasKey( 'Missing.jpg', $links );

		// NOTE: See MediaTestTrait::makeMockFile() for hard-coded values.
		$this->assertLink( [
			'title' => 'Existing.jpg',
			// File repo mocks will end up calling File namespace ns6
			'file_description_url' => 'https://example.com/wiki/ns6:Existing.jpg',
			'latest' => [
				'timestamp' => '2020-01-02T03:04:05Z',
				'user' => [ 'id' => 7, 'name' => 'Alice' ]
			],
			'preferred' => [
				'mediatype' => 'test',
				'size' => null,
				'width' => 100,
				'height' => 67,
				'duration' => 678,
				'url' => 'https://media.example.com/static/thumb/Existing.jpg',
			],
			'original' => [
				'mediatype' => 'test',
				'size' => 12345,
				'width' => 600,
				'height' => 400,
				'duration' => 678,
				'url' => 'https://media.example.com/static/Existing.jpg',
			],
		], $links['Existing.jpg'] );

		// NOTE: MediaTestTrait::makeMockRepoGroup() treats files with "missing" in the
		// name as non-existent.
		$this->assertLink( [
			'title' => 'Missing.jpg',
			// File repo mocks will end up calling File namespace ns6
			'file_description_url' => 'https://example.com/wiki/ns6:Missing.jpg',
			'latest' => null,
			'preferred' => null,
			'original' => null,
		], $links['Missing.jpg'] );
	}

	public function testCacheControl() {
		$title = Title::newFromText( __METHOD__ );
		$this->editPage( $title->getPrefixedDBkey(), 'First' );

		$request = new RequestData( [ 'pathParams' => [ 'title' => $title->getPrefixedDBkey() ] ] );

		$handler = $this->newHandler();
		$response = $this->executeHandler( $handler, $request );

		$firstETag = $response->getHeaderLine( 'ETag' );
		$this->assertSame(
			wfTimestamp( TS_RFC2822, $title->getTouched() ),
			$response->getHeaderLine( 'Last-Modified' )
		);

		$this->editPage( $title->getPrefixedDBkey(), 'Second' );

		Title::clearCaches();
		$handler = $this->newHandler();
		$response = $this->executeHandler( $handler, $request );

		$this->assertNotEquals( $response->getHeaderLine( 'ETag' ), $firstETag );
		$this->assertSame(
			wfTimestamp( TS_RFC2822, $title->getTouched() ),
			$response->getHeaderLine( 'Last-Modified' )
		);
	}

	public function testExecute_notFound() {
		$title = __CLASS__ . '_Xyzzy';
		$request = new RequestData( [ 'pathParams' => [ 'title' => $title ] ] );

		$handler = $this->newHandler();

		$this->expectExceptionObject(
			new LocalizedHttpException( new MessageValue( 'rest-nonexistent-title' ), 404 )
		);
		$this->executeHandler( $handler, $request );
	}

}
