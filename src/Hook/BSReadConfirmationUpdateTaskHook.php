<?php

namespace BlueSpice\ReadConfirmation\Hook;

use MediaWiki\Extension\UnifiedTaskOverview\ITaskDescriptor;
use MediaWiki\User\User;

interface BSReadConfirmationUpdateTaskHook {

	/**
	 * @param ITaskDescriptor $descriptor
	 * @param User $user
	 * @param bool $isConfirmed
	 * @return void
	 */
	public function onBSReadConfirmationUpdateTask(
		ITaskDescriptor $descriptor,
		User $user,
		bool $isConfirmed
	): void;

}
