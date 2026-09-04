<?php

namespace BlueSpice\ReadConfirmation\HookHandler;

use MediaWiki\Extension\UnifiedTaskOverview\ITaskDescriptor;
use MediaWiki\Extension\UnifiedTaskOverview\TaskStore;
use MediaWiki\MediaWikiServices;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\User\User;

class UpdateUnifiedTaskOverview {

	/**
	 * @param ITaskDescriptor $descriptor
	 * @param User $user
	 * @param bool $isConfirmed
	 * @return void
	 * @throws \Throwable
	 */
	public function onBSReadConfirmationUpdateTask(
		ITaskDescriptor $descriptor,
		User $user,
		bool $isConfirmed
	): void {
		if ( !ExtensionRegistry::getInstance()->isLoaded( 'UnifiedTaskOverview' ) ) {
			return;
		}
		/** @var TaskStore $store */
		$store = MediaWikiServices::getInstance()->getService( 'UnifiedTaskOverview.TaskStore' );

		if ( !$isConfirmed ) {
			$store->storeTask( $descriptor, $user );
		} else {
			$store->deleteTask( $descriptor, $user );
		}
	}

}
