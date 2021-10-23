<?php

namespace BlueSpice\ReadConfirmation\Notifications;

use BlueSpice\BaseNotification;
use Title;
use User;

class DailyRemind extends BaseNotification {

	/**
	 * Remind constructor.
	 * @param User $agent
	 * @param Title|null $title
	 * @param array $extraParams
	 * @param array $affectedUsers
	 */
	public function __construct( User $agent, Title $title = null, $extraParams = [],
		$affectedUsers = [] ) {
		parent::__construct( 'bs-readconfirmation-remind-daily', $agent, $title, $extraParams );
		$this->addAffectedUsers( $affectedUsers );
	}

	/**
	 *
	 * @return array
	 */
	public function getParams() {
		return array_merge( parent::getParams(), [
			'titlelink' => true
		] );
	}
}
