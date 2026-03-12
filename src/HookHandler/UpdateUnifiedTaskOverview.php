<?php

namespace BlueSpice\ReadConfirmation\HookHandler;

use MediaWiki\Extension\UnifiedTaskOverview\ITaskDescriptor;
use MediaWiki\MediaWikiServices;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\User\User;

class UpdateUnifiedTaskOverview {

	public function onBSReadConfirmationUpdateTask(
		ITaskDescriptor $descriptor,
		User $user,
		bool $isConfirmed
	): void {
		if ( !ExtensionRegistry::getInstance()->isLoaded( 'UnifiedTaskOverview' ) ) {
			return;
		}
		MediaWikiServices::getInstance()->getService( 'UnifiedTaskOverview.TaskStore' )
			->updateTask( $descriptor, $user, $isConfirmed );
	}

}
