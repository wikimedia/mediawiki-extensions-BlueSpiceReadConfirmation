<?php

namespace BlueSpice\ReadConfirmation\Mechanism;

use BlueSpice\NotificationManager;
use BlueSpice\PageAssignments\AssignmentFactory;
use BlueSpice\ReadConfirmation\IMechanism;
use BlueSpice\ReadConfirmation\Notifications\DailyRemind;
use BlueSpice\ReadConfirmation\Notifications\Remind;
use MediaWiki\Extension\ContentStabilization\StabilizationLookup;
use MediaWiki\Extension\ContentStabilization\StablePoint;
use MediaWiki\Extension\ContentStabilization\Storage\StablePointStore;
use MediaWiki\MediaWikiServices;
use MediaWiki\Page\PageIdentity;
use MediaWiki\Revision\RevisionLookup;
use MediaWiki\Revision\RevisionRecord;
use Title;
use User;
use Wikimedia\Rdbms\ILoadBalancer;

/**
 * Logic overview
 *
 * D = major draft, d = minor draft, S = major stable, s = minor stable, +r = read confirmed
 *
 * S+r -> d -> s => no read confirmation required
 * S+r -> D -> s => read confirmation required
 * S [-> d/D] -> s => read confirmation required
 * S+r [-> d/D] -> S => read confirmation required
 *
 * Class PageApproved
 * @package BlueSpice\FlaggedRevsConnector\ReadConfirmation\Mechanism
 */
class PageApproved implements IMechanism {

	/** @var StablePoint|null */
	private $stablePoint = null;

	/**
	 * @var int
	 */
	private $reminderDelay = 0;

	/**
	 * @var RevisionLookup
	 */
	private $revisionLookup = null;

	/**
	 * @var array
	 */
	private $enabledNamespaces = [];

	/**
	 * @var array
	 */
	private $recentMustReadRevisions = [];

	/**
	 * @var AssignmentFactory
	 */
	protected $assignmentFactory = null;

	/**
	 * @var NotificationManager
	 */
	protected $notificationsManager = null;

	/** @var StablePointStore */
	private $store;
	/** @var StabilizationLookup */
	private $lookup;
	/** @var ILoadBalancer */
	private $lb;

	/**
	 * @return PageApproved
	 */
	public static function factory() {
		$services = MediaWikiServices::getInstance();

		return new self(
			$services->getService( 'ContentStabilization.Lookup' ),
			$services->getService( 'ContentStabilization._Store' ),
			$services->getConfigFactory()->makeConfig( 'bsg' )->get( 'PageApprovedReminderDelay' ),
			$services->getMainConfig()->get( 'NamespacesWithEnabledReadConfirmation' ),
			$services->getService( 'BSPageAssignmentsAssignmentFactory' ),
			$services->getService( 'BSNotificationManager' ),
			$services->getDBLoadBalancer(),
			$services->getRevisionLookup()
		);
	}

	/**
	 * @param StabilizationLookup $lookup
	 * @param StablePointStore $store
	 * @param int $reminderDelay
	 * @param array $enabledNamespaces
	 * @param AssignmentFactory $assignmentFactory
	 * @param NotificationManager $notificationsManager
	 * @param ILoadBalancer $lb
	 * @param RevisionLookup $revisionLookup
	 */
	protected function __construct(
		StabilizationLookup $lookup, StablePointStore $store, $reminderDelay, $enabledNamespaces,
		AssignmentFactory $assignmentFactory, NotificationManager $notificationsManager, ILoadBalancer $lb,
		RevisionLookup $revisionLookup
	) {
		$this->lookup = $lookup;
		$this->store = $store;
		$this->reminderDelay = $reminderDelay;
		$this->enabledNamespaces = $enabledNamespaces;
		$this->assignmentFactory = $assignmentFactory;
		$this->notificationsManager = $notificationsManager;
		$this->lb = $lb;
		$this->revisionLookup = $revisionLookup;
	}

	/**
	 * @return void
	 */
	public function wireUpNotificationTrigger() {
	}

	/**
	 * @param StablePoint $point
	 *
	 * @return bool
	 */
	private function shouldNotify( StablePoint $point ): bool {
		if ( !$point->getPage()->exists() ) {
			return false;
		}
		if ( $point->getRevision()->isMinor()
			&& $this->hasNoPreviousMajorRevisionDrafts( $point->getRevision() ) ) {
			return false;
		}
		return true;
	}

	/**
	 * @param StablePoint $point
	 * @param User $userAgent
	 *
	 * @return bool
	 */
	private function notifyDaily( StablePoint $point, User $userAgent ) {
		if ( !$this->shouldNotify( $point ) ) {
			return false;
		}
		$notifyUsers = $this->getNotifyUsers( $point->getPage() );
		$notification = new DailyRemind( $userAgent, $point->getPage(), [], $notifyUsers );
		$this->notificationsManager->getNotifier()->notify( $notification );
		return true;
	}

	/**
	 * @param Title $title
	 * @param User $userAgent
	 *
	 * @return bool
	 */
	public function notify( Title $title, User $userAgent ) {
		$point = $this->lookup->getLastStablePoint( $title->toPageIdentity() );
		if ( !$this->shouldNotify( $point ) ) {
			return false;
		}
		$notifyUsers = $this->getNotifyUsers( $point->getPage() );
		$notification = new Remind( $userAgent, $title, [], $notifyUsers );
		$this->notificationsManager->getNotifier()->notify( $notification );
		return true;
	}

	/**
	 * @return void
	 */
	public function autoNotify() {
		$reviewMaxEndDate = date( "Y-m-d", time() - $this->reminderDelay * 3600 );

		$points = $this->store->query( [ "sp_time < '$reviewMaxEndDate'" ] );

		if ( empty( $points ) ) {
			return;
		}
		$userAgent = MediaWikiServices::getInstance()->getService( 'BSUtilityFactory' )
			->getMaintenanceUser()->getUser();

		foreach ( $points as $point ) {
			$this->notifyDaily( $point, $userAgent );
		}
	}

	/**
	 * @param Title $title
	 * @param User $user
	 * @param int|null $revId
	 * @return bool
	 */
	public function canConfirm( Title $title, User $user, $revId = null ) {
		if ( !$revId ) {
			return false;
		}

		$points = $this->lookup->getStablePointsForPage( $title->toPageIdentity() );
		$stable = null;
		foreach ( $points as $point ) {
			if ( $point->getRevision()->getId() === $revId ) {
				$stable = $point;
				break;
			}
		}
		if ( !$stable ) {
			return false;
		}

		if ( $stable->getRevision()->isMinor() ) {
			if ( $this->hasNoPreviousMajorRevisionDrafts( $stable->getRevision() ) ) {
				return false;
			}
			$revId = $this->getRecentMustReadRevision( $title->getArticleID() );
		}

		if ( !in_array( $user->getId(), $this->getAssignedUsers( $title ) ) ) {
			return false;
		}

		$arrayWithThisUsersIdIfAlreadyReadTheRevision =
			$this->usersAlreadyReadRevision( $revId, [ $user->getId() ] );
		if ( !empty( $arrayWithThisUsersIdIfAlreadyReadTheRevision ) ) {
			return false;
		}

		$this->stablePoint = $stable;

		return true;
	}

	/**
	 *
	 * @param RevisionRecord $revision
	 *
	 * @return bool
	 */
	private function hasNoPreviousMajorRevisionDrafts( RevisionRecord $revision ) {
		$previousRevision = $this->revisionLookup->getPreviousRevision( $revision );
		while ( $previousRevision instanceof RevisionRecord ) {
			if ( !$previousRevision->isMinor() ) {
				return false;
			}
			$previousRevision = $this->revisionLookup->getPreviousRevision( $previousRevision );
		}
		return true;
	}

	/**
	 * @param Title $title
	 * @param User $user
	 * @param int|null $revId
	 * @return bool
	 */
	public function confirm( Title $title, User $user, $revId = null ) {
		if ( !$this->canConfirm( $title, $user, $revId ) ) {
			return false;
		}

		$db = $this->lb->getConnection( DB_PRIMARY );
		$row = [
			'rc_rev_id' => $this->stablePoint->getRevision()->getId(),
			'rc_user_id' => $user->getId(),
			'rc_timestamp' => $db->timestamp(),
		];

		$db->upsert(
			'bs_readconfirmation',
			$row,
			[ [ 'rc_rev_id', 'rc_user_id' ] ],
			$row
		);

		return true;
	}

	/**
	 * @param Title $title
	 * @return bool
	 */
	public function mustRead( Title $title ) {
		if ( !isset( $this->enabledNamespaces[$title->getNamespace()] ) ||
			!$this->enabledNamespaces[$title->getNamespace()] ) {
			return false;
		}

		if ( !$this->getRecentMustReadRevision( $title->getArticleID() ) ) {
			return false;
		}

		return true;
	}

	/**
	 * @param int $pageId
	 * @return bool|int
	 */
	protected function getRecentMustReadRevision( $pageId ) {
		if ( !isset( $this->recentMustReadRevisions[$pageId] ) ) {
			$this->recentMustReadRevisions[$pageId] = false;
			$mustReadRevision = $this->getMustReadRevisions( [ $pageId ] );
			if ( isset( $mustReadRevision[$pageId] ) ) {
				$this->recentMustReadRevisions[$pageId] = $mustReadRevision[$pageId];
			}
		}
		return $this->recentMustReadRevisions[$pageId];
	}

	/**
	 * @param PageIdentity $page
	 *
	 * @return array
	 */
	private function getNotifyUsers( PageIdentity $page ) {
		$affectedUsers = $this->getAssignedUsers( $page );
		if ( count( $affectedUsers ) < 1 ) {
			return [];
		}
		$revId = $this->getRecentMustReadRevision( $page );
		if ( !$revId ) {
			return [];
		}
		return array_diff(
			$affectedUsers,
			$this->usersAlreadyReadRevision( $revId, $affectedUsers )
		);
	}

	/**
	 * @param PageIdentity $page
	 *
	 * @return array
	 * @throws \MWException
	 */
	private function getAssignedUsers( PageIdentity $page ) {
		if ( !$page->exists() ) {
			return [];
		}
		$target = $this->assignmentFactory->newFromTargetTitle( $page );
		if ( !$target ) {
			return [];
		}
		return $target->getAssignedUserIDs();
	}

	/**
	 * Gets THE LATEST read revisions of each page for each user specified
	 *
	 * @param array $userIds List of user IDs
	 * @return array Array with such structure:
	 *  [
	 *    <user_id1> => [
	 *		 <page_id1> => <latest_read_revision1>,
	 * 		 <page_id2> => <latest_read_revision2>
	 *	  ],
	 * 	  <user_id2> => [
	 * 	  ...
	 * 	  ],
	 * 	  ...
	 * 	]
	 */
	private function getUserLatestReadRevisions( array $userIds ): array {
		$conds = [];
		if ( $userIds ) {
			$conds = [
				'rc_user_id' => $userIds
			];
		}

		$res = $this->lb->getConnection( DB_REPLICA )->select(
			[
				'revision',
				'bs_readconfirmation'
			],
			[
				'latest_rev' => 'MAX(rc_rev_id)',
				'rev_page',
				'rc_user_id'
			],
			$conds,
			__METHOD__,
			[
				'GROUP BY' => [
					'rev_page',
					'rc_user_id'
				]
			],
			[
				'bs_readconfirmation' => [
					'INNER JOIN', 'rc_rev_id = rev_id'
				]
			]
		);

		$latestRevs = [];
		foreach ( $res as $row ) {
			$latestRevs[ (int)$row->rc_user_id ][ (int)$row->rev_page ] = (int)$row->latest_rev;
		}

		return $latestRevs;
	}

	/**
	 * @inheritDoc
	 */
	public function getLatestReadConfirmations( array $userIds = [] ): array {
		$userLatestReadRevisions = $this->getUserLatestReadRevisions( $userIds );

		$pageIds = [];
		foreach ( $userLatestReadRevisions as $latestReadRevisionData ) {
			foreach ( $latestReadRevisionData as $pageId => $latestReadRevisionId ) {
				$pageIds[$pageId] = true;
			}
		}
		$pageIds = array_keys( $pageIds );

		$recentRevisions = $this->getMustReadRevisions( $pageIds );

		$readConfirmations = [];
		foreach ( $userLatestReadRevisions as $userId => $latestReadRevisionData ) {
			foreach ( $latestReadRevisionData as $pageId => $latestReadRevisionId ) {
				// In case if there are no major revisions of the page
				$recentRevisionId = 0;

				// There is some major revision of the page
				if ( isset( $recentRevisions[ $pageId ] ) ) {
					$recentRevisionId = $recentRevisions[ $pageId ];
				}

				$readConfirmations[$userId][$pageId] = [
					'latest_rev' => $recentRevisionId,
					'latest_read_rev' => $latestReadRevisionId
				];
			}
		}

		return $readConfirmations;
	}

	/**
	 * @param array $userIds
	 * @param array $pageIds
	 * @return array [ <page_id> => [ <user_id1>, <user_id2>, ...], ... ]
	 */
	public function getCurrentReadConfirmations( array $userIds = [], array $pageIds = [] ) {
		$currentReadConfirmations = [];
		$userReadRevisions = $this->getUserReadRevisions( $userIds );
		$recentRevisions = $this->getMustReadRevisions( $pageIds );
		foreach ( $pageIds as $pageId ) {
			$reads = [];
			if (
				isset( $recentRevisions[$pageId] ) &&
				isset( $userReadRevisions[$recentRevisions[$pageId]] )
			) {
				$reads = $userReadRevisions[$recentRevisions[$pageId]];
			}
			$currentReadConfirmations[$pageId] = $reads;
		}

		return $currentReadConfirmations;
	}

	/**
	 * @param int $revisionId
	 * @param array $userIds
	 * @return array
	 */
	private function usersAlreadyReadRevision( $revisionId, $userIds ) {
		$res = $this->lb->getConnection( DB_REPLICA )->select(
			'bs_readconfirmation',
			'*',
			[
				'rc_user_id' => $userIds,
				'rc_rev_id' => $revisionId
			],
			__METHOD__
		);

		if ( $res->numRows() > 0 ) {
			$userIds = [];
			foreach ( $res as $row ) {
				$userIds[] = $row->rc_user_id;
			}
			return $userIds;
		}

		return [];
	}

	/**
	 * @param array $userIds
	 * @return array
	 */
	private function getUserReadRevisions( $userIds = [] ) {
		$conds = [];
		if ( !empty( $userIds ) ) {
			$conds['rc_user_id'] = $userIds;
		}
		$res = $this->lb
			->getConnection( DB_REPLICA )
			->select(
				'bs_readconfirmation',
				'*',
				$conds,
				__METHOD__
			);

		$readRevisions = [];
		foreach ( $res as $row ) {
			$revId = (int)$row->rc_rev_id;
			if ( !isset( $readRevisions[ $revId ] ) ) {
				$readRevisions[ $revId ] = [];
			}
			$readRevisions[ $revId ][(int)$row->rc_user_id] = $row->rc_timestamp;
		}

		return $readRevisions;
	}

	/**
	 * @param array $pageIds
	 * @return array
	 */
	private function getMustReadRevisions( array $pageIds = [] ) {
		$recentData = [];

		$conds = [];

		if ( !empty( $pageIds ) ) {
			$conds['rev_page'] = $pageIds;
		}

		$res = $this->lb->getConnection( DB_REPLICA )->select(
			[ 'revision', 'stable_points' ],
			[ 'rev_id', 'rev_page', 'rev_minor_edit', 'sp_revision' ],
			$conds,
			__METHOD__,
			[ 'ORDER BY' => 'rev_id DESC' ],
			[
				'flaggedpages' => [ 'LEFT JOIN', 'rev_page = sp_page' ]
			]
		);

		foreach ( $res as $row ) {
			if ( isset( $recentData[$row->rev_page] ) ) {
				continue;
			}
			if ( $row->rev_id <= $row->sp_revision && (int)$row->rev_minor_edit === 0 ) {
				$recentData[$row->rev_page] = (int)$row->rev_id;
			}
		}

		return $recentData;
	}

}
