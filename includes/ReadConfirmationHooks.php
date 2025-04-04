<?php

use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\RevisionStoreRecord;
use MediaWiki\Storage\EditResult;
use MediaWiki\User\User;

class ReadConfirmationHooks {

	/**
	 * Automatically set a page revision as read if user is creator of the
	 * revision
	 *
	 * @param WikiPage $wikiPage
	 * @param User $user
	 * @param string $summary
	 * @param int $flags
	 * @param RevisionStoreRecord $revision
	 * @param EditResult $editResult
	 * @return bool
	 */
	public static function onPageSaveComplete( WikiPage $wikiPage, User $user, string $summary,
		int $flags, RevisionStoreRecord $revision, EditResult $editResult ) {
		if ( $flags & EDIT_MINOR ) {
			return true;
		}
		global $wgNamespacesWithEnabledReadConfirmation;

		$iNS = $wikiPage->getTitle()->getNamespace();
		if ( !isset( $wgNamespacesWithEnabledReadConfirmation[$iNS] )
			|| $wgNamespacesWithEnabledReadConfirmation[$iNS] === false ) {
			return true;
		}

		$services = MediaWikiServices::getInstance();
		$factory = $services->getService(
			'BSPageAssignmentsAssignmentFactory'
		);
		if ( !$factory ) {
			return true;
		}
		$target = $factory->newFromTargetTitle( $wikiPage->getTitle() );
		if ( !$target ) {
			return true;
		}
		if ( !$target->isUserAssigned( $user ) ) {
			return true;
		}

		$aRow = [
			'rc_rev_id' => $revision->getId(),
			'rc_user_id' => $user->getId()
		];

		$dbw = $services->getDBLoadBalancer()->getConnection( DB_PRIMARY );
		$dbw->delete(
			'bs_readconfirmation',
			$aRow,
			__METHOD__
		);
		$aRow['rc_timestamp'] = wfTimestampNow();
		$dbw->insert(
			'bs_readconfirmation',
			$aRow,
			__METHOD__
		);

		return true;
	}

	/**
	 * Hook handler for
	 * NamespaceManager::getMetaFields
	 *
	 * @param array &$aMetaFields
	 * @return bool Always true
	 */
	public static function onNamespaceManager_getMetaFields( &$aMetaFields ) { // phpcs:ignore MediaWiki.NamingConventions.LowerCamelFunctionsName.FunctionName, Generic.Files.LineLength.TooLong
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
	public static function onNamespaceManager_editNamespace( // phpcs:ignore MediaWiki.NamingConventions.LowerCamelFunctionsName.FunctionName, Generic.Files.LineLength.TooLong
		&$aNamespaceDefinition, &$iNs, $aAdditionalSettings, $bUseInternalDefaults
	) {
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
}
