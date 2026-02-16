<?php

namespace BlueSpice\ReadConfirmation\HookHandler\NamespaceManagerCollectNamespaceProperties;

class AddNamespaceProperties {

	/**
	 * @inheritDoc
	 */
	public function onNamespaceManagerCollectNamespaceProperties(
		int $namespaceId,
		array $globals,
		array &$properties
	): void {
		$properties['read_confirmation'] = in_array(
			$namespaceId,
			$globals['wgNamespacesWithEnabledReadConfirmation'] ?? []
		);
	}

}
