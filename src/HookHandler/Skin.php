<?php

namespace BlueSpice\ReadConfirmation\HookHandler;

use MediaWiki\Hook\PersonalUrlsHook;
use SkinTemplate;
use Title;

class Skin implements PersonalUrlsHook {

	/**
	 * @param array &$personal_urls
	 * @param Title &$title
	 * @param SkinTemplate $skin
	 * @return void
	 */
	public function onPersonalUrls( &$personal_urls, &$title, $skin ): void {
		if ( !isset( $personal_urls['pageassignments'] ) ) {
			return;
		}
		if ( !isset( $personal_urls['pageassignments']['data'] ) ) {
			$personal_urls['pageassignments']['data'] = [];
		}
		$personal_urls['pageassignments']['data']['attentionindicator'] = 'readconfirmation';
	}

}
