<?php

namespace BlueSpice\ReadConfirmation;

class Extension extends \BlueSpice\Extension {

	/**
	 *
	 * @param int[] $aUserIds
	 * @param int[] $aPageIds
	 * @return array in form [ <page_id> => [ <user_id1>, <user_id2>, ...], ... ]
	 */
	public static function getCurrentReadConfirmations( $aUserIds = [], $aPageIds = [] ) {
		$dbr = wfGetDB( DB_REPLICA );

		// Step 1: Collect all data about what the users have read
		$aConds = [];
		if ( !empty( $aUserIds ) ) {
			$aConds['rc_user_id'] = $aUserIds;
		}
		$res = $dbr->select( 'bs_readconfirmation', '*', $aConds, __METHOD__ );

		$aReadRevisions = [];
		foreach ( $res as $row ) {
			$iRevId = (int)$row->rc_rev_id;
			if ( !isset( $aReadRevisions[$iRevId] ) ) {
				$aReadRevisions[$iRevId] = [];
			}
			$aReadRevisions[$iRevId][(int)$row->rc_user_id] = $row->rc_timestamp;
		}

		// Step 2: Collect data about the recent non-minor edits of the requested pages
		$aConds = [
			// Only non-minor-edits
			'rev_minor_edit' => 0
		];
		if ( !empty( $aPageIds ) ) {
			$aConds['rev_page'] = $aPageIds;
		}

		$res = $dbr->select(
			'revision',
			[ 'rev_id', 'rev_page', 'rev_minor_edit' ],
			$aConds,
			__METHOD__,
			[
				'ORDER BY' => 'rev_id DESC'
			]
		);

		$aPageRevisions = [];
		foreach ( $res as $row ) {
			$iPageId = (int)$row->rev_page;
			if ( isset( $aPageRevisions[$iPageId] ) ) {
				// This way we only get the latest non-minor revisions
				continue;
			}
			$aPageRevisions[$iPageId] = (int)$row->rev_id;
		}

		// Step 3: Combine the two information into one
		$aCurrentPageReads = [];
		foreach ( $aPageIds as $iPageId ) {
			$aReads = [];
			if ( isset( $aPageRevisions[$iPageId] )
				&& isset( $aReadRevisions[$aPageRevisions[$iPageId]] ) ) {
				$aReads = $aReadRevisions[$aPageRevisions[$iPageId]];
			}
			$aCurrentPageReads[$iPageId] = $aReads;
		}

		return $aCurrentPageReads;
	}

	/**
	 *
	 * @param \Title $oTitle
	 * @return bool
	 */
	public static function isNamespaceEnabled( $oTitle ) {
		global $wgNamespacesWithEnabledReadConfirmation;
		if ( !$oTitle instanceof \Title ) {
			return true;
		}
		$iNS = $oTitle->getNamespace();
		if ( isset( $wgNamespacesWithEnabledReadConfirmation[$iNS] )
			&& $wgNamespacesWithEnabledReadConfirmation[$iNS] ) {
			return true;
		}
		return false;
	}
}
