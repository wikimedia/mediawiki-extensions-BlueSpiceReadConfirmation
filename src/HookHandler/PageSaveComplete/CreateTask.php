<?php

namespace BlueSpice\ReadConfirmation\HookHandler\PageSaveComplete;

use BlueSpice\ReadConfirmation\Mechanism\NonMinorEdit;
use BlueSpice\ReadConfirmation\MechanismFactory;
use BlueSpice\ReadConfirmation\UnifiedTaskOverview\ReadConfirmationTaskDescriptor;
use MediaWiki\HookContainer\HookContainer;
use MediaWiki\Storage\Hook\PageSaveCompleteHook;
use MediaWiki\Title\Title;

class CreateTask implements PageSaveCompleteHook {

	/** @var HookContainer */
	private HookContainer $hookContainer;

	/** @var MechanismFactory */
	private MechanismFactory $mechanismFactory;

	/**
	 * @param HookContainer $hookContainer
	 * @param MechanismFactory $mechanismFactory
	 */
	public function __construct( HookContainer $hookContainer, MechanismFactory $mechanismFactory ) {
		$this->hookContainer = $hookContainer;
		$this->mechanismFactory = $mechanismFactory;
	}

	/**
	 * @inheritDoc
	 */
	public function onPageSaveComplete(
		$wikiPage,
		$user,
		$summary,
		$flags,
		$revisionRecord,
		$editResult
	) {
		$title = $wikiPage->getTitle();
		if ( !$this->isReadConfirmationEnabledNamespace( $title ) ) {
			return;
		}

		/** @var NonMinorEdit */
		$mechanism = $this->mechanismFactory->getMechanismInstance();
		$usersToConfirm = $mechanism->getUsersToConfirm( $title );
		if ( !$usersToConfirm ) {
			return;
		}

		$descriptor = new ReadConfirmationTaskDescriptor(
			$title,
			$revisionRecord,
			$revisionRecord->getId()
		);

		foreach ( $usersToConfirm as $targetUser ) {
			$this->hookContainer->run(
				'BSReadConfirmationUpdateTask',
				[ $descriptor, $targetUser, false ]
			);
		}
	}

	/**
	 * @param Title $title
	 * @return bool
	 */
	private function isReadConfirmationEnabledNamespace( Title $title ): bool {
		global $wgNamespacesWithEnabledReadConfirmation;

		$namespaces = isset( $wgNamespacesWithEnabledReadConfirmation )
			? array_keys( $GLOBALS['wgNamespacesWithEnabledReadConfirmation'] )
			: [];

		return in_array( $title->getNamespace(), $namespaces );
	}

}
