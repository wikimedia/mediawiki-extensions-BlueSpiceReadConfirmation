<?php

use BlueSpice\Services;

class ReadConfirmationHooks {

	public static function setup() {
		BSNotifications::registerNotification(
			'bs-readconfirmation-remind',
			'bs-pageassignments-action-cat',
			'notification-bs-readconfirmation-remind-summary',
			[ 'agent', 'title', 'titlelink' ],
			'notification-bs-readconfirmation-remind-subject',
			[ 'agent', 'title', 'titlelink' ],
			'notification-bs-readconfirmation-remind-body',
			[ 'agent', 'title', 'titlelink' ],
			[
				'formatter-class' => 'PageAssignmentsNotificationFormatter',
				'primary-link' => [ 'message' => 'notification-link-text-view-page', 'destination' => 'title' ]
			]
		);
	}

	/**
	 * Adds database tables
	 * @param DatabaseUpdater $updater
	 * @return bool
	 */
	public static function onLoadExtensionSchemaUpdates( $updater ) {
		$updater->addExtensionTable(
			'bs_readconfirmation',
			dirname( __DIR__ ) . '/db/bs_readconfirmation.sql'
		);
		return true;
	}

	/**
	 *
	 * @param OutputPage &$out
	 * @param Skin &$skin
	 * @return bool
	 */
	public static function onBeforePageDisplay( &$out, &$skin ) {
		$out->addModuleStyles( 'ext.readconfirmation.styles' );
		$out->addModules( 'ext.readconfirmation.scripts' );

		if ( $out->getTitle()->isSpecial( 'ManagePageAssignments' ) ) {
			$out->addModuleStyles(
				'ext.readconfirmation.pageassignmentsintegration.styles'
			);
			$out->addModules( 'ext.readconfirmation.pageassignmentsintegration' );
		}

		return true;
	}

	/**
	 * Automatically set a page revision as read if user is creator of the
	 * revision
	 * @param WikiPage $wikiPage
	 * @param User $user
	 * @param Content $content
	 * @param string $summary
	 * @param bool $isMinor
	 * @param bool $isWatch
	 * @param int $section
	 * @param int $flags
	 * @param Revision $revision
	 * @param Status $status
	 * @param int $baseRevId
	 * @return bool
	 */
	public static function onPageContentSaveComplete( $wikiPage, $user,
			$content, $summary, $isMinor, $isWatch, $section, $flags,
			$revision, $status, $baseRevId ) {
		if ( $isMinor ) {
			return true;
		}

		$iNS = $wikiPage->getTitle()->getNamespace();
		if ( !isset( $wgNamespacesWithEnabledReadConfirmation[$iNS] )
			|| $wgNamespacesWithEnabledReadConfirmation[$iNS] === false ) {
			return true;
		}

		$factory = Services::getInstance()->getService(
			'BSPageAssignmentsAssignmentFactory'
		);
		if ( !$factory ) {
			return true;
		}
		$target = $factory->newFromTargetTitle( $wikiPage->getTitle() );
		if ( !$target ) {
			return true;
		}
		if ( $target->isUserAssigned( $user->getId() ) ) {
			return true;
		}

		$aRow = [
			'rc_rev_id' => $revision->getId(),
			'rc_user_id' => $user->getId()
		];

		$dbw = wfGetDB( DB_MASTER );
		$dbw->delete( 'bs_readconfirmation', $aRow );
		$aRow['rc_timestamp'] = wfTimestampNow();
		$dbw->insert( 'bs_readconfirmation', $aRow );

		return true;
	}

	/**
	 * Hook handler for
	 * NamespaceManager::getMetaFields
	 *
	 * @param array &$aMetaFields
	 * @return bool Always true
	 */
	public static function onNamespaceManager_getMetaFields( &$aMetaFields ) {
		$aMetaFields[] = [
				'name' => 'read_confirmation',
				'type' => 'boolean',
				'sortable' => true,
				'filter' => [
					'type' => 'boolean'
				],
				'label' => wfMessage( 'bs-readconfirmation-label-ns-manager' )->plain()
		];
		return true;
	}

	/**
	 * Hook handler for
	 * NamespaceManager::editNamespace
	 *
	 * @param array &$aNamespaceDefinition
	 * @param int &$iNs
	 * @param array $aAdditionalSettings
	 * @param bool $bUseInternalDefaults
	 * @return bool Always true
	 */
	public static function onNamespaceManager_editNamespace( &$aNamespaceDefinition, &$iNs,
		$aAdditionalSettings, $bUseInternalDefaults ) {
		if ( empty( $aNamespaceDefinition[$iNs] ) ) {
			$aNamespaceDefinition[$iNs] = [];
		}

		if ( isset( $aAdditionalSettings['read_confirmation'] ) ) {
			$aNamespaceDefinition[$iNs][ 'read_confirmation' ]
				= $aAdditionalSettings['read_confirmation'];
		} else {
			$aNamespaceDefinition[$iNs][ 'read_confirmation' ] = false;
		}
		return true;
	}

	/**
	 * Hook handler for
	 * NamespaceManager::WriteNamespaceConfiguration
	 *
	 * @param string &$sSaveContent
	 * @param string $sConstName
	 * @param int $iNs
	 * @param array $aDefinition
	 * @return bool Always true
	 */
	public static function onNamespaceManager_writeNamespaceConfiguration( &$sSaveContent, $sConstName,
		$iNs, $aDefinition ) {
		global $wgNamespacesWithEnabledReadConfirmation;
		if ( isset( $aDefinition[ 'read_confirmation' ] )
			&& $aDefinition['read_confirmation'] === true ) {
			$sSaveContent .= "\$GLOBALS['wgNamespacesWithEnabledReadConfirmation'][{$sConstName}] = true;\n";
		} elseif ( isset( $aDefinition[ 'read_confirmation' ] )
			&& $aDefinition['read_confirmation'] === false ) {
			return true;
		}
		if ( isset( $wgNamespacesWithEnabledReadConfirmation[$iNs] )
			&& $wgNamespacesWithEnabledReadConfirmation[$iNs] === true ) {
			$sSaveContent .= "\$GLOBALS['wgNamespacesWithEnabledReadConfirmation'][{$sConstName}] = true;\n";
		}
		return true;
	}

	/**
	 * Hook handler for
	 * BSApiNamespaceStoreMakeData
	 *
	 * @param array &$aResult
	 * @return bool Always true
	 */
	public static function onBSApiNamespaceStoreMakeData( &$aResult ) {
		global $wgNamespacesWithEnabledReadConfirmation;

		foreach ( $aResult as &$aSingleResult ) {
			$iNs = $aSingleResult['id'];
			$activated = false;
			if ( isset( $wgNamespacesWithEnabledReadConfirmation[$iNs] )
				&& $wgNamespacesWithEnabledReadConfirmation[$iNs] === true ) {
				$activated = true;
			}
			$aSingleResult[ 'read_confirmation' ] = [
				'value' => $activated,
				'disabled' => $aSingleResult['isTalkNS']
			];
		}

		return true;
	}

	/**
	 * Hook handler for UnitTestList
	 *
	 * @param array &$paths
	 * @return bool
	 */
	public static function onUnitTestsList( &$paths ) {
		$paths[] = __DIR__ . '/../tests/phpunit/';
		return true;
	}
}
