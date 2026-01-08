<?php

namespace BlueSpice\ReadConfirmation;

use BlueSpice\ReadConfirmation\Process\AutomaticReadConfirmationNotifier;
use MediaWiki\MediaWikiServices;
use MWStake\MediaWiki\Component\ProcessManager\ManagedProcess;
use MWStake\MediaWiki\Component\WikiCron\WikiCronManager;

class ReadConfirmationNotificationCron {

	/**
	 * @return void
	 */
	public static function register() {
		if ( defined( 'MW_PHPUNIT_TEST' ) || defined( 'MW_QUIBBLE_CI' ) ) {
			return;
		}

		/** @var WikiCronManager $cronManager */
		$cronManager = MediaWikiServices::getInstance()->getService( 'MWStake.WikiCronManager' );

		// Interval: Daily at 01:00
		$cronManager->registerCron( 'bs-readconfirmation-autonotifier', '0 1 * * *', new ManagedProcess( [
			'read-confirmation-notification' => [
				'class' => AutomaticReadConfirmationNotifier::class,
				'services' => [
					'BSReadConfirmationMechanismFactory',
				],
			]
		] ) );
	}
}
