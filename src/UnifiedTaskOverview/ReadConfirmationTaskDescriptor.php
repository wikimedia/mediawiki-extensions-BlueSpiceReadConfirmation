<?php

namespace BlueSpice\ReadConfirmation\UnifiedTaskOverview;

use MediaWiki\Extension\UnifiedTaskOverview\ITaskDescriptor;
use MediaWiki\Language\RawMessage;
use MediaWiki\MediaWikiServices;
use MediaWiki\Message\Message;
use MediaWiki\Page\PageProps;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Title\Title;
use stdClass;

class ReadConfirmationTaskDescriptor implements ITaskDescriptor {

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
	 * Revision to confirm
	 *
	 * @var RevisionRecord
	 */
	private $revisionToConfirm;

	/**
	 * @var PageProps
	 */
	private PageProps $pageProps;

	/**
	 * @param Title $title Title, which user should mark as "read"
	 * @param RevisionRecord $revisionToConfirm Revision to confirm
	 * @param int|null $latestReadRevision <tt>null</tt> if user did not mark any revision of specified article,
	 * 	or ID of the latest read revision otherwise
	 */
	public function __construct( Title $title, RevisionRecord $revisionToConfirm, ?int $latestReadRevision = null ) {
		$this->title = $title;
		$this->pageProps = MediaWikiServices::getInstance()->getPageProps();
		$this->revisionToConfirm = $revisionToConfirm;

		if ( $latestReadRevision ) {
			$this->latestReadRevision = $latestReadRevision;
		}
	}

	/**
	 * @param stdClass $row
	 * @return static|null
	 */
	public static function newFromTaskRow( stdClass $row ): ?static {
		$services = MediaWikiServices::getInstance();
		$title = $services->getTitleFactory()->newFromID( (int)$row->uto_page_id );
		if ( !$title ) {
			return null;
		}

		$revision = $services->getRevisionLookup()->getRevisionByTitle( $title );
		if ( !$revision ) {
			return null;
		}

		return new static(
			$title,
			$revision,
			null
		);
	}

	/**
	 * @inheritDoc
	 */
	public function getUniqueKey(): string {
		$pageId = $this->title->getArticleID();
		$revId = $this->revisionToConfirm->getId() ?? 0;

		return $pageId . ':' . $revId;
	}

	/**
	 * @inheritDoc
	 */
	public function getTitle(): Title {
		return $this->title;
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
		if ( !$this->title ) {
			return new RawMessage( '' );
		}

		$displayTitleProperties = $this->pageProps->getProperties( $this->title, 'displaytitle' );
		if ( count( $displayTitleProperties ) === 1 ) {
			$displayTitle = $displayTitleProperties[$this->title->getArticleID()];
		}

		return new RawMessage( $displayTitle ?? $this->title->getSubpageText() );
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
				'diff' => $this->revisionToConfirm->isCurrent() ? 'cur' : $this->revisionToConfirm->getId()
			];
		} elseif ( !$this->revisionToConfirm->isCurrent() ) {
			$query = [ 'oldid' => $this->revisionToConfirm->getId() ];
		}

		return $this->title->getFullURL( $query );
	}
}
