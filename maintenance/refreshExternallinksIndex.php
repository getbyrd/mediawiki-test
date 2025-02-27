<?php
/**
 * Refresh the externallinks table el_index and el_index_60 from el_to
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 *
 * @file
 * @ingroup Maintenance
 */

require_once __DIR__ . '/Maintenance.php';

/**
 * Maintenance script that refreshes the externallinks table el_index and
 * el_index_60 from el_to
 *
 * @ingroup Maintenance
 * @since 1.33
 */
class RefreshExternallinksIndex extends LoggedUpdateMaintenance {
	public function __construct() {
		parent::__construct();
		$this->addDescription(
			'Refresh the externallinks table el_index and el_index_60 from el_to' );
		$this->setBatchSize( 10000 );
	}

	protected function getUpdateKey() {
		return static::class . ' v' . LinkFilter::VERSION . '+IDN';
	}

	protected function updateSkippedMessage() {
		return 'externallinks table indexes up to date.';
	}

	protected function doDBUpdates() {
		$dbw = $this->getDB( DB_PRIMARY );
		if ( !$dbw->tableExists( 'externallinks', __METHOD__ ) ) {
			$this->error( "externallinks table does not exist" );
			return false;
		}
		$this->output( "Updating externallinks table index fields\n" );

		$minmax = $dbw->selectRow(
			'externallinks',
			[ 'min' => 'MIN(el_id)', 'max' => 'MAX(el_id)' ],
			'',
			__METHOD__
		);

		$updated = 0;
		$deleted = 0;
		$start = $minmax->min - 1;
		$last = (int)$minmax->max;
		while ( $start < $last ) {
			$end = min( $start + $this->mBatchSize, $last );
			$this->output( "el_id $start - $end of $last\n" );
			$res = $dbw->select( 'externallinks', [ 'el_id', 'el_to', 'el_index' ],
				[
					"el_id > $start",
					"el_id <= $end",
				],
				__METHOD__,
				[ 'ORDER BY' => 'el_id' ]
			);
			foreach ( $res as $row ) {
				$newIndexes = LinkFilter::makeIndexes( $row->el_to );
				if ( !$newIndexes ) {
					$dbw->delete( 'externallinks', [ 'el_id' => $row->el_id ], __METHOD__ );
					$deleted++;
					continue;
				}
				$newIndexes2 = [];
				foreach ( $newIndexes as $newIndex ) {
					$newIndexes2[] = implode( '', $newIndex );
				}
				if ( in_array( $row->el_index, $newIndexes2, true ) ) {
					continue;
				}

				if ( count( $newIndexes2 ) === 1 ) {
					$newIndex = $newIndexes2[0];
				} else {
					// Assume the scheme is the only difference between the different $newIndexes.
					// Keep this row's scheme, assuming there's another row with the other scheme.
					$newIndex = substr( $row->el_index, 0, strpos( $row->el_index, ':' ) ) .
						substr( $newIndexes2[0], strpos( $newIndexes2[0], ':' ) );
				}
				$dbw->update( 'externallinks',
					[
						'el_index' => $newIndex,
						'el_index_60' => substr( $newIndex, 0, 60 ),
					],
					[ 'el_id' => $row->el_id ],
					__METHOD__
				);
				$updated++;
			}
			$this->waitForReplication();
			$start = $end;
		}
		$this->output( "Done, $updated rows updated, $deleted deleted.\n" );

		return true;
	}
}

$maintClass = RefreshExternallinksIndex::class;
require_once RUN_MAINTENANCE_IF_MAIN;
