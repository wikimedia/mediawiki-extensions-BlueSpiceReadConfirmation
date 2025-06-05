<?php

namespace BlueSpice\ReadConfirmation;

use InvalidArgumentException;
use LogicException;

class MechanismFactory {

	/**
	 * @var IMechanism
	 */
	private $mechanism = null;

	/**
	 * MechanismFactory constructor.
	 * @param string $mechanismCallback
	 * @throws InvalidArgumentException
	 * @throws LogicException
	 */
	public function __construct( $mechanismCallback ) {
		if ( !is_callable( $mechanismCallback ) ) {
			throw new InvalidArgumentException(
				"There is no ReadConfirmation Mechanism"
			);
		}
		$this->mechanism = call_user_func( $mechanismCallback );
		if ( !$this->mechanism instanceof IMechanism ) {
			throw new LogicException(
				(string)$mechanismCallback . " must implements IMechanism interface"
			);
		}
	}

	/**
	 * @return IMechanism
	 */
	public function getMechanismInstance() {
		return $this->mechanism;
	}
}
