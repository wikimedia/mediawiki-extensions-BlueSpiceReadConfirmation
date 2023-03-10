<?php

namespace BlueSpice\ReadConfirmation\Hook\BeforePageDisplay;

use BlueSpice\Hook\BeforePageDisplay;

class AddResources extends BeforePageDisplay {

	/**
	 *
	 * @return bool
	 */
	protected function skipProcessing() {
		global $wgNamespacesWithEnabledReadConfirmation;

		$namespaces = isset( $wgNamespacesWithEnabledReadConfirmation )
			? array_keys( $GLOBALS['wgNamespacesWithEnabledReadConfirmation'] )
			: [];

		$title = $this->out->getTitle();
		$namespace = $title->getNamespace();

		if ( !in_array( $namespace, $namespaces ) ) {
			return true;
		}
		return false;
	}

	protected function doProcess() {
		$this->out->addModuleStyles( 'ext.readconfirmation.styles' );
		$this->out->addModules( 'ext.readconfirmation.scripts' );

		return true;
	}

}
