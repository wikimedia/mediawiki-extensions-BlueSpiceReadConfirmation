<?php

namespace BlueSpice\ReadConfirmation;

use Title;
use User;

interface IMechanism {

	/**
	 * @param Title $title
	 * @param User $user
	 * @param int|null $revId
	 * @return bool
	 */
	public function canConfirm( Title $title, User $user, $revId = null );

	/**
	 * @param Title $title
	 * @param User $user
	 * @param int|null $revId
	 * @return bool
	 */
	public function confirm( Title $title, User $user, $revId = null );

	/**
	 * @param Title $title
	 * @param User $userAgent
	 * @return User[]|bool
	 */
	public function notify( Title $title, User $userAgent );

	/**
	 * @return void
	 */
	public function autoNotify();

	/**
	 * @return void
	 */
	public function wireUpNotificationTrigger();

}
