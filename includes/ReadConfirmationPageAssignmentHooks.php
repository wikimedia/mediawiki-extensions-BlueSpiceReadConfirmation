<?php

use BlueSpice\Services;

class ReadConfirmationPageAssignmentHooks {
	/**
	 *
	 * @param ApiBase $oApiModule
	 * @param array &$aData
	 * @return bool
	 */
	public static function onBSApiExtJSStoreBaseBeforePostProcessData( $oApiModule, &$aData ) {
		if ( $oApiModule instanceof BSApiMyPageAssignmentStore ) {
			self::extendBSApiMyPageAssignmentStore( $aData );
		}
		if ( $oApiModule instanceof BSApiPageAssignmentStore ) {
			self::extendBSApiPageAssignmentStore( $aData );
		}
		return true;
	}

	/**
	 *
	 * @param array &$aData
	 */
	protected static function extendBSApiMyPageAssignmentStore( &$aData ) {
		$aPageIds = [];
		$aDisabledIds = [];
		foreach ( $aData as $oDataSet ) {
			$oTitle = Title::newFromID( $oDataSet->page_id );
			if ( !\BlueSpice\ReadConfirmation\Extension::isNamespaceEnabled( $oTitle ) ) {
				$aDisabledIds[] = $oDataSet->page_id;
			} else {
				$aPageIds[] = $oDataSet->page_id;
			}
		}

		$iCurrentUserId = RequestContext::getMain()->getUser()->getId();

		$aCurrentPageReads = \BlueSpice\ReadConfirmation\Extension::getCurrentReadConfirmations(
			[ $iCurrentUserId ],
			$aPageIds
		);

		foreach ( $aData as $oDataSet ) {
			if ( in_array( $oDataSet->page_id, $aDisabledIds ) ) {
				$oDataSet->read_confirmation = 'disabled';
				continue;
			}
			$sTimestamp = null;
			if ( isset( $aCurrentPageReads[$oDataSet->page_id][$iCurrentUserId] ) ) {
				$sTimestamp = $aCurrentPageReads[$oDataSet->page_id][$iCurrentUserId];
			}
			$oDataSet->read_confirmation = $sTimestamp;
		}
	}

	/**
	 *
	 * @param array &$aData
	 * @return bool
	 */
	protected static function extendBSApiPageAssignmentStore( &$aData ) {
		$factory = Services::getInstance()->getService(
			'BSPageAssignmentsAssignmentFactory'
		);
		if ( !$factory ) {
			return;
		}
		foreach ( $aData as $oDataSet ) {
			$oDataSet->all_assignees_have_read = false;
			$oTitle = Title::newFromID( $oDataSet->page_id );
			if ( !\BlueSpice\ReadConfirmation\Extension::isNamespaceEnabled( $oTitle ) ) {
				continue;
			}
			if ( empty( $oDataSet->assignments ) ) {
				continue;
			}

			$target = $factory->newFromTargetTitle( $oTitle );
			if ( !$target ) {
				continue;
			}
			if ( empty( $target->getAssignedUserIDs() ) ) {
				return true;
			}

			$aPageReads = \BlueSpice\ReadConfirmation\Extension::getCurrentReadConfirmations(
				$target->getAssignedUserIDs(),
				[ $oDataSet->page_id ]
			);
			$aUserIdsThatHaveRead = array_keys( $aPageReads[$oDataSet->page_id] );
			$oDataSet->all_assignees_have_read = empty( array_diff(
				$target->getAssignedUserIDs(),
				$aUserIdsThatHaveRead
			) );
		}
	}

	/**
	 *
	 * @param \SpecialPageAssignments|\SpecialManagePageAssignments $oSender
	 * @param array &$aDeps
	 * @return bool
	 */
	public static function onBSPageAssignmentsSpecialPages( $oSender, &$aDeps ) {
		$aDeps[] = 'ext.readconfirmation.pageassignmentsintegration';
		return true;
	}

}
