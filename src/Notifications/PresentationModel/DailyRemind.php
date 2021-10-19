<?php

namespace BlueSpice\ReadConfirmation\Notifications\PresentationModel;

use BlueSpice\EchoConnector\EchoEventPresentationModel;

class DailyRemind extends EchoEventPresentationModel {
	/**
	 * Gets appropriate messages keys and params
	 * for header message
	 *
	 * @return array
	 */
	public function getHeaderMessageContent() {
		$bundleKey = '';
		$bundleParams = [];

		$headerKey = 'notification-bs-readconfirmation-remind-daily-summary';
		$headerParams = [ 'title' ];

		return [
			'key' => $headerKey,
			'params' => $headerParams,
			'bundle-key' => $bundleKey,
			'bundle-params' => $bundleParams
		];
	}

	/**
	 * Gets appropriate message key and params for
	 * web notification message
	 *
	 * @return array
	 */
	public function getBodyMessageContent() {
		$bodyKey = 'notification-bs-readconfirmation-remind-daily-body';
		$bodyParams = [ 'title' ];

		return [
			'key' => $bodyKey,
			'params' => $bodyParams
		];
	}
}
