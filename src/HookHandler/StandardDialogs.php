<?php

namespace BlueSpice\ReadConfirmation\HookHandler;

use Config;
use MediaWiki\Extension\StandardDialogs\Hook\StandardDialogsRegisterPageInfoPanelModules;
use ResourceLoaderContext;

class StandardDialogs implements StandardDialogsRegisterPageInfoPanelModules {

	/**
	 * @inheritDoc
	 */
	public function onStandardDialogsRegisterPageInfoPanelModules(
		&$modules,
		ResourceLoaderContext $context,
		Config $config ): void {
		$modules[] = "ext.readconfirmation.dialog.info.page";
	}
}
