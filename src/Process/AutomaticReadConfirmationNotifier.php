<?php

namespace BlueSpice\ReadConfirmation\Process;

use BlueSpice\ReadConfirmation\IMechanism;
use BlueSpice\ReadConfirmation\MechanismFactory;
use MWStake\MediaWiki\Component\ProcessManager\IProcessStep;

class AutomaticReadConfirmationNotifier implements IProcessStep {

	/**
	 * @param MechanismFactory $factory
	 */
	public function __construct( private readonly MechanismFactory $factory ) {
	}

	/**
	 * @param array $data
	 *
	 * @return array
	 */
	public function execute( $data = [] ): array {
		$this->getReadConfirmationMechanism()->autoNotify();

		return [];
	}

	/**
	 * @return IMechanism
	 */
	private function getReadConfirmationMechanism(): IMechanism {
		return $this->factory->getMechanismInstance();
	}
}
