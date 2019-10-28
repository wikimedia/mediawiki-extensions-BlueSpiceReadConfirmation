<?php

namespace BlueSpice\ReadConfirmation\Notifications;

use BlueSpice\Services;
use BlueSpice\BaseNotification;

class Remind extends BaseNotification {

	protected $affectedUsers = null;

	/**
	 *
	 * @param \User $agent
	 * @param \Title|null $title
	 * @param array $extraParams
	 */
	public function __construct( \User $agent, \Title $title = null, $extraParams = [] ) {
		parent::__construct( 'bs-readconfirmation-remind', $agent, $title, $extraParams );
	}

	/**
	 *
	 * @return array
	 */
	public function getAudience() {
		return $this->getAffectedUsers();
	}

	/**
	 *
	 * @return array
	 */
	public function getAffectedUsers() {
		if ( $this->affectedUsers ) {
			return $this->affectedUsers;
		}
		$target = $this->getAssignmentFactory()->newFromTargetTitle( $this->getTitle() );
		if ( $target === false ) {
			return [];
		}
		$assignedUserIds = $target->getAssignedUserIDs();
		$currentReads = \BlueSpice\ReadConfirmation\Extension::getCurrentReadConfirmations(
			$assignedUserIds,
			[ $this->getTitle()->getArticleID() ]
		);
		$this->affectedUsers = array_filter(
			$target->getAssignedUserIDs(),
			function ( $e ) use( $target, $currentReads ) {
				return !isset( $currentReads[$target->getTitle()->getArticleID()][$e] );
		 } );
		return $this->affectedUsers;
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
