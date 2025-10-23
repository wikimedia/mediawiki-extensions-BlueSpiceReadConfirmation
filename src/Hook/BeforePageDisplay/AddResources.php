<?php

namespace BlueSpice\ReadConfirmation\Hook\BeforePageDisplay;

use BlueSpice\ReadConfirmation\Mechanism\NonMinorEdit;
use BlueSpice\ReadConfirmation\MechanismFactory;
use MediaWiki\Output\Hook\BeforePageDisplayHook;
use MediaWiki\Output\OutputPage;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Revision\RevisionLookup;

class AddResources implements BeforePageDisplayHook {

	/** @var RevisionLookup */
	protected $revisionLookup;

	/** @var MechanismFactory */
	protected $mechanismFactory;

	/** @var PermissionManager */
	protected $permissionManager;

	/**
	 * @param RevisionLookup $revisionLookup
	 * @param MechanismFactory $mechanismFactory
	 * @param PermissionManager $permissionManager
	 */
	public function __construct(
		RevisionLookup $revisionLookup, MechanismFactory $mechanismFactory, PermissionManager $permissionManager
	) {
		$this->revisionLookup = $revisionLookup;
		$this->mechanismFactory = $mechanismFactory;
		$this->permissionManager = $permissionManager;
	}

	/**
	 * @param OutputPage $out
	 * @return bool
	 */
	protected function shouldSkipProcessing( OutputPage $out ) {
		global $wgNamespacesWithEnabledReadConfirmation;

		$namespaces = isset( $wgNamespacesWithEnabledReadConfirmation )
			? array_keys( $GLOBALS['wgNamespacesWithEnabledReadConfirmation'] )
			: [];

		$title = $out->getTitle();
		if ( !$title ) {
			return true;
		}

		$namespace = $title->getNamespace();
		if ( !in_array( $namespace, $namespaces ) ) {
			if ( $title->isSpecial( 'ManagePageAssignments' ) ) {
				return false;
			}
			return true;
		}

		$isRevisionCurrent = $out->isRevisionCurrent();
		if ( !$isRevisionCurrent ) {
			return true;
		}

		/** @var NonMinorEdit */
		$mechanism = $this->mechanismFactory->getMechanismInstance();
		$pageId = $title->getArticleID();
		$userId = $out->getUser()->getId();
		$confirmations = $mechanism->getCurrentReadConfirmations( [ $userId ], [ $pageId ] );

		$hasRead = !empty( $confirmations[$pageId] );
		if ( $hasRead ) {
			return true;
		}

		return false;
	}

	/**
	 * @inheritDoc
	 */
	public function onBeforePageDisplay( $out, $skin ): void {
		if ( $this->shouldSkipProcessing( $out ) ) {
			return;
		}

		$out->addModuleStyles( 'ext.readconfirmation.styles' );
		$out->addModules( 'ext.readconfirmation.scripts' );

		$user = $skin->getUser();
		$isAllowed = $this->permissionManager->userHasRight( $user, 'readconfirmationviewconfirmations' );

		$out->addJsConfigVars( 'bsReadConfirmationsViewRight', $isAllowed );
	}

}
