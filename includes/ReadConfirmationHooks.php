<?php

use BlueSpice\Services;

class ReadConfirmationHooks {

	public static function setup() {
		BSNotifications::registerNotification(
			'bs-readconfirmation-remind',
			'bs-pageassignments-action-cat',
			'notification-bs-readconfirmation-remind-summary',
			array('agent', 'title', 'titlelink'),
			'notification-bs-readconfirmation-remind-subject',
			array('agent', 'title', 'titlelink'),
			'notification-bs-readconfirmation-remind-body',
			array('agent', 'title', 'titlelink'),
			array(
				'formatter-class' => 'PageAssignmentsNotificationFormatter',
				'primary-link' => array( 'message' => 'notification-link-text-view-page', 'destination' => 'title' )
			)
		);
	}


	/**
	 * Adds database tables
	 * @param DatabaseUpdater $updater
	 * @return boolean
	 */
	public static function onLoadExtensionSchemaUpdates( $updater ) {
		$updater->addExtensionTable( 'bs_readconfirmation', dirname( __DIR__ ).'/db/bs_readconfirmation.sql' );
		return true;
	}

	/**
	 *
	 * @param OutputPage $out
	 * @param Skin $skin
	 * @return boolean
	 */
	public static function onBeforePageDisplay( &$out, &$skin ) {
		$out->addModuleStyles( 'ext.readconfirmation.styles' );
		$out->addModules( 'ext.readconfirmation.scripts' );

		if( $out->getTitle()->isSpecial( 'ManagePageAssignments' ) ) {
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
	 * @param boolean $isMinor
	 * @param boolean $isWatch
	 * @param int $section
	 * @param int $flags
	 * @param Revision $revision
	 * @param Status $status
	 * @param int $baseRevId
	 * @return boolean
	 */
	public static function onPageContentSaveComplete( $wikiPage, $user,
			$content, $summary, $isMinor, $isWatch, $section, $flags,
			$revision, $status, $baseRevId ) {

		if( $isMinor ) {
			return true;
		}

		$iNS = $wikiPage->getTitle()->getNamespace();
		if( !isset( $wgNamespacesWithEnabledReadConfirmation[$iNS] ) || $wgNamespacesWithEnabledReadConfirmation[$iNS] === false ) {
			return true;
		}

		$factory = Services::getInstance()->getService(
			'BSPageAssignmentsAssignmentFactory'
		);
		if( !$factory ) {
			return true;
		}
		if( !$target = $factory->newFromTargetTitle( $wikiPage->getTitle() ) ) {
			return true;
		}
		if( $target->isUserAssigned( $user->getId() ) ) {
			return true;
		}

		$aRow = array(
			'rc_rev_id' => $revision->getId(),
			'rc_user_id' =>  $user->getId()
		);

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
	 * @param array $aMetaFields
	 * @return bool Always true
	 */
	public static function onNamespaceManager_getMetaFields( &$aMetaFields ) {
		$aMetaFields[] = array(
				'name' => 'read_confirmation',
				'type' => 'boolean',
				'sortable' => true,
				'filter' => array(
					'type' => 'boolean'
				),
				'label' => wfMessage( 'bs-readconfirmation-label-ns-manager' )->plain()
		);
		return true;
	}

	/**
	 * Hook handler for
	 * NamespaceManager::editNamespace
	 *
	 * @param array $aNamespaceDefinitions
	 * @param integer $iNs
	 * @param array $aAdditionalSettings
	 * @param boolean $bUseInternalDefaults
	 * @return bool Always true
	 */
	public static function onNamespaceManager_editNamespace( &$aNamespaceDefinition, &$iNs, $aAdditionalSettings, $bUseInternalDefaults ) {
		if ( empty( $aNamespaceDefinition[$iNs] ) ) {
			$aNamespaceDefinition[$iNs] = array();
		}

		if ( isset( $aAdditionalSettings['read_confirmation'] ) ) {
			$aNamespaceDefinition[$iNs][ 'read_confirmation' ] = $aAdditionalSettings['read_confirmation'];
		}
		else {
			$aNamespaceDefinition[$iNs][ 'read_confirmation' ] = false;
		}
		return true;
	}

	/**
	 * Hook handler for
	 * NamespaceManager::WriteNamespaceConfiguration
	 *
	 * @param string $sSaveContent
	 * @param string $sConstName
	 * @param integer $iNs
	 * @param array $aDefinition
	 * @return bool Always true
	 */
	public static function onNamespaceManager_writeNamespaceConfiguration( &$sSaveContent, $sConstName, $iNs, $aDefinition ) {
		global $wgNamespacesWithEnabledReadConfirmation;
		if ( isset( $aDefinition[ 'read_confirmation' ] ) && $aDefinition['read_confirmation'] === true ) {
			$sSaveContent .= "\$GLOBALS['wgNamespacesWithEnabledReadConfirmation'][{$sConstName}] = true;\n";
		} else if( isset( $aDefinition[ 'read_confirmation' ] ) && $aDefinition['read_confirmation'] === false ) {
			return true;
		}
		if( isset( $wgNamespacesWithEnabledReadConfirmation[$iNs] ) && $wgNamespacesWithEnabledReadConfirmation[$iNs] === true ) {
			$sSaveContent .= "\$GLOBALS['wgNamespacesWithEnabledReadConfirmation'][{$sConstName}] = true;\n";
		}
		return true;
	}

	/**
	 * Hook handler for
	 * BSApiNamespaceStoreMakeData
	 *
	 * @param array $aResult
	 * @return bool Always true
	 */
	public static function onBSApiNamespaceStoreMakeData( &$aResult ) {
		global $wgNamespacesWithEnabledReadConfirmation;

		foreach( $aResult as &$aSingleResult ) {
			$iNs = $aSingleResult['id'];
			if( isset( $wgNamespacesWithEnabledReadConfirmation[$iNs] ) && $wgNamespacesWithEnabledReadConfirmation[$iNs] === true ) {
				$aSingleResult['read_confirmation'] = true;
			} else {
				$aSingleResult['read_confirmation'] = false;
			}
		}

		return true;
	}

	/**
	 * Hook handler for UnitTestList
	 *
	 * @param array $paths
	 * @return boolean
	 */
	public static function onUnitTestsList( &$paths ) {
		$paths[] = __DIR__ . '/../tests/phpunit/';
		return true;
	}
}
