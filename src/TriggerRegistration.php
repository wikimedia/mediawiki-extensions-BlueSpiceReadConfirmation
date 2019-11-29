<?php

namespace BlueSpice\ReadConfirmation;

class TriggerRegistration {

	public static function addNotificationTrigger() {
		/** @var MechanismFactory $readConfirmationsFactory */
		$readConfirmationsFactory = \BlueSpice\Services::getInstance()
			->getService( 'BSReadConfirmationMechanismFactory' );

		$readConfirmationsFactory->getMechanismInstance()->wireUpNotificationTrigger();
	}
}
