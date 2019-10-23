<?php

use BlueSpice\Services;
use BlueSpice\Data\ResultSet;
use BlueSpice\PageAssignments\Data\Page\PrimaryDataProvider;
use BlueSpice\PageAssignments\Data\Page\Record;

class ReadConfirmationPageAssignmentHooks {
	/**
	 *
	 * @param PrimaryDataProvider $dataProvider
	 * @param ResultSet $dataSet
	 * @param Title $title
	 * @return bool
	 */
	public static function onBSPageStoreDataProviderBeforeAppendRow( $dataProvider,
		$dataSet, $title ) {
		if( !$dataProvider instanceof PrimaryDataProvider ) {
			return true;
		}
		$dataSet->set( 'all_assignees_have_read', false );
		if( !\BlueSpice\ReadConfirmation\Extension::isNamespaceEnabled ( $title ) ) {
			return true;
		}
		if( empty( $dataSet->get( Record::ASSIGNMENTS, [] ) ) ) {
			return true;
		}
		$factory = Services::getInstance()->getService(
			'BSPageAssignmentsAssignmentFactory'
		);
		if( !$factory ) {
			return true;
		}
		$target = $factory->newFromTargetTitle( $title );
		if( !$target ) {
			return true;
		}
		if( empty( $target->getAssignedUserIDs() ) ) {
			return true;
		}

		$aPageReads = \BlueSpice\ReadConfirmation\Extension::getCurrentReadConfirmations(
			$target->getAssignedUserIDs(),
			[ (int)$dataSet->get( Record::ID ) ]
		);
		$aUserIdsThatHaveRead = array_keys( $aPageReads[(int)$dataSet->get( Record::ID )] );
		$read = empty( array_diff(
			$target->getAssignedUserIDs(),
			$aUserIdsThatHaveRead
		));
		$dataSet->set( 'all_assignees_have_read', $read );
		return true;
	}

	public static function onBSApiExtJSStoreBaseBeforePostProcessData( $oApiModule, &$aData ) {
		if( $oApiModule instanceof BSApiMyPageAssignmentStore ) {
			self::extendBSApiMyPageAssignmentStore( $aData );
		}
		return true;
	}

	protected static function extendBSApiMyPageAssignmentStore( &$aData ) {
		$aPageIds = array();
		$aDisabledIds = array();
		foreach( $aData as $oDataSet ) {
			$oTitle = Title::newFromID( $oDataSet->page_id );
			if( !\BlueSpice\ReadConfirmation\Extension::isNamespaceEnabled( $oTitle ) ) {
				$aDisabledIds[] = $oDataSet->page_id;
			} else {
				$aPageIds[] = $oDataSet->page_id;
			}
		}

		$iCurrentUserId = RequestContext::getMain()->getUser()->getId();

		$aCurrentPageReads = \BlueSpice\ReadConfirmation\Extension::getCurrentReadConfirmations(
			array( $iCurrentUserId ),
			$aPageIds
		);

		foreach( $aData as $oDataSet ) {
			if( in_array( $oDataSet->page_id, $aDisabledIds ) ) {
				$oDataSet->read_confirmation = 'disabled';
				continue;
			}
			$sTimestamp = null;
			if( isset( $aCurrentPageReads[$oDataSet->page_id][$iCurrentUserId] ) ) {
				$sTimestamp = $aCurrentPageReads[$oDataSet->page_id][$iCurrentUserId];
			}
			$oDataSet->read_confirmation = $sTimestamp;
		}
	}

	public static function onBSPageAssignmentsSpecialPages( $oSender, &$aDeps ) {
		$aDeps[] = 'ext.readconfirmation.pageassignmentsintegration';
		return true;
	}

}
