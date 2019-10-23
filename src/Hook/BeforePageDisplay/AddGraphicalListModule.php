<?php

namespace BlueSpice\ReadConfirmation\Hook\BeforePageDisplay;

use BlueSpice\Hook\BeforePageDisplay;

class AddGraphicalListModule extends BeforePageDisplay {

	protected function doProcess() {
		$this->out->addModules( 'bs.readconfirmation.GraphicalListFactory' );
		return true;
	}

}