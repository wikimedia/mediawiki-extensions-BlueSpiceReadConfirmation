<?php

namespace BlueSpice\ReadConfirmation\UnifiedTaskOverview;

use MediaWiki\Extension\UnifiedTaskOverview\ITaskDescriptor;
use Message;
use RawMessage;
use Title;

class ReadConfirmationDescriptor implements ITaskDescriptor {

	/**
	 * Title, which user should mark as "read"
	 *
	 * @var Title
	 */
	private $title = null;

	/**
	 * Latest marked as "read" by user revision.
	 * <tt>null</tt> if user did not mark any revision of specified article
	 *
	 * @var int
	 */
	private $latestReadRevision = null;

	/**
	 * @param Title $title Title, which user should mark as "read"
	 * @param int|null $latestReadRevision <tt>null</tt> if user did not mark any revision of specified article,
	 * 	or ID of the latest read revision otherwise
	 */
	public function __construct( Title $title, int $latestReadRevision = null ) {
		$this->title = $title;

		if ( $latestReadRevision ) {
			$this->latestReadRevision = $latestReadRevision;
		}
	}

	/**
	 * @inheritDoc
	 */
	public function getBody(): Message {
		if ( $this->latestReadRevision ) {
			return Message::newFromKey( 'bs-readconfirmation-uto-task-body-diff' );
		}
		return new RawMessage( '' );
	}

	/**
	 * @inheritDoc
	 */
	public function getHeader(): Message {
		$message = $this->title ? $this->title->getPrefixedText() : '';

		return new RawMessage( $message );
	}

	/**
	 * @inheritDoc
	 */
	public function getRLModules(): array {
		return [ 'ext.readconfirmation.uto.styles' ];
	}

	/**
	 * @inheritDoc
	 */
	public function getSortKey(): int {
		return 20;
	}

	/**
	 * @inheritDoc
	 */
	public function getSubHeader(): Message {
		return Message::newFromKey( 'bs-readconfirmation-uto-task-header' );
	}

	/**
	 * @inheritDoc
	 */
	public function getType(): string {
		return 'read-confirmation';
	}

	/**
	 * @inheritDoc
	 */
	public function getURL(): string {
		$query = [];

		if ( $this->latestReadRevision ) {
			$query = [
				'oldid' => $this->latestReadRevision,
				'diff' => 'cur'
			];
		}

		return $this->title->getFullURL( $query );
	}
}
