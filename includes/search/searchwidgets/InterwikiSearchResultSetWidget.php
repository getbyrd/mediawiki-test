<?php

namespace MediaWiki\Search\SearchWidgets;

use ISearchResultSet;
use MediaWiki\Html\Html;
use MediaWiki\Interwiki\InterwikiLookup;
use MediaWiki\Linker\LinkRenderer;
use OOUI;
use SpecialSearch;
use Title;

/**
 * Renders one or more ISearchResultSets into a sidebar grouped by
 * interwiki prefix. Includes a per-wiki header indicating where
 * the results are from.
 */
class InterwikiSearchResultSetWidget implements SearchResultSetWidget {
	/** @var SpecialSearch */
	protected $specialSearch;
	/** @var SearchResultWidget */
	protected $resultWidget;
	/** @var string[]|null */
	protected $customCaptions;
	/** @var LinkRenderer */
	protected $linkRenderer;
	/** @var InterwikiLookup */
	protected $iwLookup;
	/** @var \OutputPage */
	protected $output;
	/** @var bool */
	protected $showMultimedia;

	public function __construct(
		SpecialSearch $specialSearch,
		SearchResultWidget $resultWidget,
		LinkRenderer $linkRenderer,
		InterwikiLookup $iwLookup,
		$showMultimedia = false
	) {
		$this->specialSearch = $specialSearch;
		$this->resultWidget = $resultWidget;
		$this->linkRenderer = $linkRenderer;
		$this->iwLookup = $iwLookup;
		$this->output = $specialSearch->getOutput();
		$this->showMultimedia = $showMultimedia;
	}

	/**
	 * @param string $term User provided search term
	 * @param ISearchResultSet|ISearchResultSet[] $resultSets List of interwiki
	 *  results to render.
	 * @return string HTML
	 */
	public function render( $term, $resultSets ) {
		if ( !is_array( $resultSets ) ) {
			$resultSets = [ $resultSets ];
		}

		$this->loadCustomCaptions();

		if ( $this->showMultimedia ) {
			$this->output->addModules( 'mediawiki.special.search.commonsInterwikiWidget' );
		}
		$this->output->addModuleStyles( 'mediawiki.special.search.interwikiwidget.styles' );

		$iwResults = [];
		foreach ( $resultSets as $resultSet ) {
			foreach ( $resultSet as $result ) {
				if ( !$result->isBrokenTitle() ) {
					$iwResults[$result->getTitle()->getInterwiki()][] = $result;
				}
			}
		}

		$iwResultSetPos = 1;
		$iwResultListOutput = '';

		foreach ( $iwResults as $iwPrefix => $results ) {
			// TODO: Assumes interwiki results are never paginated
			$position = 0;
			$iwResultItemOutput = '';

			foreach ( $results as $result ) {
				$iwResultItemOutput .= $this->resultWidget->render( $result, $position++ );
			}

			$headerHtml = $this->headerHtml( $term, $iwPrefix );
			$footerHtml = $this->footerHtml( $term, $iwPrefix );
			$iwResultListOutput .= Html::rawElement( 'li',
				[
					'class' => 'iw-resultset',
					'data-iw-resultset-pos' => $iwResultSetPos,
					'data-iw-resultset-source' => $iwPrefix
				],

				$headerHtml .
				$iwResultItemOutput .
				$footerHtml
			);
			$iwResultSetPos++;
		}

		return Html::rawElement(
			'div',
			[ 'id' => 'mw-interwiki-results' ],
			Html::rawElement(
				'ul', [ 'class' => 'iw-results', ], $iwResultListOutput
			)
		);
	}

	/**
	 * Generates an HTML header for the given interwiki prefix
	 *
	 * @param string $term User provided search term
	 * @param string $iwPrefix Interwiki prefix of wiki to show heading for
	 * @return string HTML
	 */
	protected function headerHtml( $term, $iwPrefix ) {
		$href = Title::makeTitle( NS_SPECIAL, 'Search', '', $iwPrefix )->getLocalURL(
			[ 'search' => $term, 'fulltext' => 1 ]
		);

		$interwiki = $this->iwLookup->fetch( $iwPrefix );
		$parsed = wfParseUrl( wfExpandUrl( $interwiki ? $interwiki->getURL() : '/' ) );

		$caption = $this->customCaptions[$iwPrefix] ?? $parsed['host'];

		$searchLink = Html::rawElement( 'a', [ 'href' => $href, 'target' => '_blank' ], $caption );

		return Html::rawElement( 'div',
			[ 'class' => 'iw-result__header' ],
			$this->iwIcon( $iwPrefix ) . $searchLink );
	}

	/**
	 * Generates an HTML footer for the given interwiki prefix
	 *
	 * @param string $term User provided search term
	 * @param string $iwPrefix Interwiki prefix of wiki to show heading for
	 * @return string HTML
	 */
	protected function footerHtml( $term, $iwPrefix ) {
		$href = Title::makeTitle( NS_SPECIAL, 'Search', '', $iwPrefix )->getLocalURL(
			[ 'search' => $term, 'fulltext' => 1 ]
		);

		$interwiki = $this->iwLookup->fetch( $iwPrefix );
		$parsed = wfParseUrl( wfExpandUrl( $interwiki ? $interwiki->getURL() : '/' ) );

		$caption = $this->specialSearch->msg( 'search-interwiki-resultset-link', $parsed['host'] )->escaped();

		$searchLink = Html::rawElement( 'a', [ 'href' => $href, 'target' => '_blank' ], $caption );

		return Html::rawElement( 'div',
			[ 'class' => 'iw-result__footer' ],
			$searchLink );
	}

	protected function loadCustomCaptions() {
		if ( $this->customCaptions !== null ) {
			return;
		}

		$this->customCaptions = [];
		$customLines = explode( "\n", $this->specialSearch->msg( 'search-interwiki-custom' )->escaped() );
		foreach ( $customLines as $line ) {
			$parts = explode( ':', $line, 2 );
			if ( count( $parts ) === 2 ) {
				$this->customCaptions[$parts[0]] = $parts[1];
			}
		}
	}

	/**
	 * Generates a custom OOUI icon element with a favicon as the image.
	 * The favicon image URL is generated by parsing the interwiki URL
	 * and returning the default location of the favicon for that domain,
	 * which is assumed to be '/favicon.ico'.
	 *
	 * @param string $iwPrefix Interwiki prefix
	 * @return OOUI\IconWidget
	 */
	protected function iwIcon( $iwPrefix ) {
		$interwiki = $this->iwLookup->fetch( $iwPrefix );
		$parsed = wfParseUrl( wfExpandUrl( $interwiki ? $interwiki->getURL() : '/' ) );

		$iwIconUrl = $parsed['scheme'] .
			$parsed['delimiter'] .
			$parsed['host'] .
			( isset( $parsed['port'] ) ? ':' . $parsed['port'] : '' ) .
			'/favicon.ico';

		$iwIcon = new OOUI\IconWidget( [
			'icon' => 'favicon'
		] );

		$iwIcon->setAttributes( [ 'style' => "background-image:url($iwIconUrl);" ] );

		return $iwIcon;
	}
}
