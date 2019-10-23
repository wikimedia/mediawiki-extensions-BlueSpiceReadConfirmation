<?php

use BlueSpice\Services;
use BlueSpice\PageAssignments\AssignmentFactory;
use BlueSpice\ReadConfirmation\Notifications\Remind;

class BSApiReadConfirmationTasks extends BSApiTasksBase {

	protected $sTaskLogType = 'bs-readconfirmation';

	protected $aTasks = array( 'confirm', 'check', 'remind' );

	protected function getRequiredTaskPermissions() {
		return array(
			'confirm' => array( 'read' ),
			'check' => array( 'read' ),
			'remind' => array( 'readconfirmationremind' )
		);
	}

	protected function task_confirm( $oTaskData, $aParams ) {
		$oResult = $this->makeStandardReturn();

		if( is_int( $oTaskData->pageId ) === false ) {
			$oResult->message = wfMessage( 'bs-readconfirmation-api-error-no-page' )->plain();
			return $oResult;
		}

		$oTitle = Title::newFromId( $oTaskData->pageId );
		if( !\BlueSpice\ReadConfirmation\Extension::isNamespaceEnabled ( $oTitle ) ) {
			$oResult->message = wfMessage( 'bs-readconfirmation-api-error-not-active-ns' )->plain();
			return $oResult;
		}
		$oWikiPage = WikiPage::factory( $oTitle );
		$oRevision = $oWikiPage->getRevision();
		while( $oRevision instanceof Revision && $oRevision->isMinor() === true ) {
			$oRevision = $oRevision->getPrevious();
		}

		if( $oRevision instanceof Revision === false ) {
			$oResult->message = wfMessage( 'bs-readconfirmation-api-error-no-non-minor-revision' )->plain();
			return $oResult;
		}

		$aRow = array(
			'rc_rev_id' => $oRevision->getId(),
			'rc_user_id' =>  $this->getUser()->getId()
		);

		//I don't understand the usage of "DatabaseBase::uspert"
		$this->getDB( DB_MASTER )->delete( 'bs_readconfirmation', $aRow );
		$aRow['rc_timestamp'] = wfTimestampNow();
		$this->getDB( DB_MASTER )->insert( 'bs_readconfirmation', $aRow );

		$this->logTaskAction( 'confirm', array(), array(
			'target' => $oTitle
		) );

		$oResult->success = true;

		return $oResult;
	}

	protected function task_check( $oTaskData, $aParams ) {
		$oResult = $this->makeStandardReturn();

		$oTitle = Title::newFromID( $oTaskData->pageId );
		if( !\BlueSpice\ReadConfirmation\Extension::isNamespaceEnabled ( $oTitle ) ) {
			$oResult->message = wfMessage( 'bs-readconfirmation-api-error-not-active-ns' )->plain();
			return $oResult;
		}
		$iCurrentUserId = $this->getUser()->getId();
		if( $oTitle instanceof Title === false ) {
			$oResult->message = wfMessage( 'bs-pageassignments-api-error-no-page' )->plain();
			return $oResult;
		}

		$oResult->success = true;
		$oResult->payload = array(
			'pageId' => $oTaskData->pageId,
			'userId' => $iCurrentUserId,
			'userHasConfirmed' => true
		);

		$target = $this->getAssignmentFactory()->newFromTargetTitle( $oTitle );
		if( $target === false ) {
			return $oResult;
		}

		//This is a hard dependency to PageAssignments extension.
		//It could also be placed in an appropriate hook handler
		if( !$target->isUserAssigned( $this->getUser() ) ) {
			return $oResult; //If the user is not assigned we bail out telling
			//the caller that it already has been confirmed. This is not the
			//truth and therefore not nice, but for the time being it is
			//sufficient. Better solution would probably be to throw an
			//exception
		}

		$aCurrentPageReads = BlueSpice\ReadConfirmation\Extension::getCurrentReadConfirmations(
			array( $iCurrentUserId ),
			array( $oTaskData->pageId )
		);

		if( !isset( $aCurrentPageReads[$oTaskData->pageId][$iCurrentUserId] ) ) {
			$oResult->payload['userHasConfirmed'] = false;
		}

		return $oResult;
	}

	protected function task_remind( $oTaskData, $aParams ) {
		global $wgNamespacesWithEnabledReadConfirmation;
		$oResult = $this->makeStandardReturn();

		$oTitle = Title::newFromID( $oTaskData->pageId );
		if( !\BlueSpice\ReadConfirmation\Extension::isNamespaceEnabled ( $oTitle ) ) {
			$oResult->message = wfMessage( 'bs-readconfirmation-api-error-not-active-ns' )->plain();
			return $oResult;
		}
		if( $oTitle instanceof Title === false ) {
			$oResult->message = wfMessage( 'bs-pageassignments-api-error-no-page' )->plain();
			return $oResult;
		}

		$aUserDisplayNames = array();
		$target = $this->getAssignmentFactory()->newFromTargetTitle( $oTitle );
		if( $target === false || empty( $target->getAssignedUserIDs() ) ) {
			return $oResult;
		}

		$notificationsManager = Services::getInstance()->getBSNotificationManager();
		$notifier = $notificationsManager->getNotifier();
		$notification = new Remind( $this->getUser(), $oTitle );
		$notifier->notify( $notification );

		foreach( $notification->getAffectedUsers() as $userId ) {
			if ( !$user = \User::newFromId( $userId ) ) {
				continue;
			}
			$aUserDisplayNames[] = Services::getInstance()->getBSUtilityFactory()
				->getUserHelper( $user )->getDisplayName();
		}
		$this->logTaskAction( 'remind', array(
			'4::users' => implode( ', ',  $aUserDisplayNames )
		),
		array(
			'target' => $oTitle
		));

		$oResult->success = true;
		return $oResult;
	}

	public function isWriteMode() {
		return true;
	}

	/**
	 * Creates a log entry for Special:Log, based on $this->sTaskLogType or
	 * $aOptions['type']. See https://www.mediawiki.org/wiki/Manual:Logging_to_Special:Log
	 * @param string $sAction
	 * @param array $aParams for the log entry
	 * @param array $aOptions <br/>
	 * 'performer' of type User<br/>
	 * 'target' of type Title<br/>
	 * 'timestamp' of type string<br/>
	 * 'relations of type array<br/>
	 * 'deleted' of type int<br/>
	 * 'type' of type string; to allow overriding of class default
	 * @param bool $bDoPublish
	 * @return int Id of the newly created log entry or -1 on error
	 */
	protected function logTaskAction( $sAction, $aParams, $aOptions = array(), $bDoPublish = false ) {
		$aOptions += array(
			'performer' => null,
			'target' => null,
			'timestamp' => null,
			'relations' => null,
			'comment' => null,
			'deleted' =>  null,
			'publish' => null,
			'type' => null //To allow overriding of class default
		);

		$oTarget = $aOptions['target'];
		if ( $oTarget === null ) {
			$oTarget = $this->makeDefaultLogTarget();
		}

		$oPerformer = $aOptions['performer'];
		if ( $oPerformer === null ) {
			$oPerformer = $this->getUser();
		}

		$sType = $this->sTaskLogType;
		if ( $aOptions['type'] !== null ) {
			$sType = $aOptions['type'];
		}

		if ( $sType === null ) { //Not set on class, not set as call option
			return -1;
		}

		$oLogger = new ManualLogEntry( $sType, $sAction );
		$oLogger->setPerformer( $oPerformer );
		$oLogger->setTarget( $oTarget );
		$oLogger->setParameters( $aParams );

		if ( $aOptions['timestamp'] !== null ) {
			$oLogger->setTimestamp( $aOptions['timestamp'] );
		}

		if ( $aOptions['relations'] !== null ) {
			$oLogger->setRelations( $aOptions['relations'] );
		}

		if ( $aOptions['comment'] !== null ) {
			$oLogger->setComment( $aOptions['comment'] );
		}

		if ( $aOptions['deleted'] !== null ) {
			$oLogger->setDeleted( $aOptions['deleted'] );
		}

		$iLogEntryId = $oLogger->insert();

		if ( $bDoPublish ) {
			$oLogger->publish();
		}

		return $iLogEntryId;
	}

	/**
	 *
	 * @return AssignmentFactory
	 */
	protected function getAssignmentFactory() {
		$factory = Services::getInstance()->getService(
			'BSPageAssignmentsAssignmentFactory'
		);
		return $factory;
	}
}
