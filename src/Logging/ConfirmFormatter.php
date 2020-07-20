<?php

namespace BlueSpice\ReadConfirmation\Logging;

use LogEntry;
use LogFormatter;
use LogPage;
use MediaWiki\MediaWikiServices;
use MediaWiki\Storage\RevisionRecord;
use Message;

class ConfirmFormatter extends LogFormatter {

	/**
	 *
	 * @var integer
	 */
	private $revId = -1;

	/**
	 * @inheritDoc
	 */
	protected function __construct( LogEntry $entry ) {
		parent::__construct( $entry );
		$this->initRevId();
	}

	/**
	 * @inheritDoc
	 */
	public function getActionText() {
		$text = parent::getActionText();
		if ( $this->canView( LogPage::DELETED_ACTION ) ) {
			$text .= $this->appendRevisionLink();
		}

		return $text;
	}

	private function appendRevisionLink() {
		if ( $this->hasRevisionIdLogged() ) {
			$timestamp = $this->getTimestampFromRevisionId();
			if ( empty( $timestamp ) ) {
				return '';
			}

			$language = $this->context->getLanguage();
			$date = $language->timeanddate( $timestamp, true );

			$message = Message::newFromKey( 'bs-readconfirmation-logentry-confirm-suffix-as-of' );
			return ' ' . $message->params( $date )->text();
		}
		return '';
	}

	private function initRevId() {
		$parameters = $this->entry->getParameters();
		if ( isset( $parameters['revid'] ) ) {
			$this->revId = $parameters['revid'];
		}
	}

	private function hasRevisionIdLogged() {
		return $this->revId !== -1;
	}

	private function getTimestampFromRevisionId() {
		$timestamp = '';
		$revisionLookop = MediaWikiServices::getInstance()->getRevisionLookup();
		$revision = $revisionLookop->getRevisionById( $this->revId );
		if ( $revision instanceof RevisionRecord ) {
			$timestamp = $revision->getTimestamp();
		}

		return $timestamp;
	}

	/**
	 * @inheritDoc
	 */
	protected function makePageLink( $title = null, $parameters = [], $html = null ) {
		$link = parent::makePageLink( $title, $parameters, $html );

		if ( $this->hasRevisionIdLogged() ) {
			$link = $this->getLinkRenderer()->makeLink(
				$this->entry->getTarget(),
				null,
				[],
				[
					'oldid' => $this->revId
				]
			);
		}

		return $link;
	}
}
