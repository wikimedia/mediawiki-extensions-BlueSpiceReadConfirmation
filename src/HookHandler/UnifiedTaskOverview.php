<?php

namespace BlueSpice\ReadConfirmation\HookHandler;

use BlueSpice\ReadConfirmation\IMechanism;
use BlueSpice\ReadConfirmation\MechanismFactory;
use BlueSpice\ReadConfirmation\UnifiedTaskOverview\ReadConfirmationDescriptor;
use MediaWiki\Extension\UnifiedTaskOverview\Hook\GetTaskDescriptors;
use MediaWiki\User\UserGroupManager;
use Title;
use User;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Rdbms\ILoadBalancer;

class UnifiedTaskOverview implements GetTaskDescriptors {

	/**
	 * Replica DB reader
	 *
	 * @var IDatabase
	 */
	private $dbr;

	/**
	 * User group manager to get info about user groups
	 *
	 * @var UserGroupManager
	 */
	private $groupManager;

	/**
	 * Read confirmations mechanism, which can be used to get user read confirmations.
	 *
	 * @var IMechanism
	 */
	private $readConfirmationMechanism;

	/**
	 * @param ILoadBalancer $loadBalancer
	 * @param UserGroupManager $groupManager
	 * @param MechanismFactory $readConfirmationMechanismFactory
	 */
	public function __construct(
		ILoadBalancer $loadBalancer,
		UserGroupManager $groupManager,
		MechanismFactory $readConfirmationMechanismFactory
	) {
		$this->dbr = $loadBalancer->getConnection( DB_REPLICA );
		$this->groupManager = $groupManager;
		$this->readConfirmationMechanism = $readConfirmationMechanismFactory->getMechanismInstance();
	}

	/**
	 * @inheritDoc
	 */
	public function onUnifiedTaskOverviewGetTaskDescriptors( &$descriptors, $user ) {
		$readConfirmationsDescriptors = $this->getReadConfirmationTasks( $user );
		$descriptors = array_merge( $descriptors, $readConfirmationsDescriptors );
	}

	/**
	 * Gets read confirmation tasks, assigned to specified user.
	 * Read confirmation tasks are based on pages, which the latest revision was not marked as "read" by user.
	 *
	 * @param User $user User, who read confirmation tasks are got for
	 * @return ReadConfirmationDescriptor[] List of read confirmation tasks, assigned to specified user
	 */
	private function getReadConfirmationTasks( User $user ): array {
		$username = $user->getName();
		$userGroups = $this->groupManager->getUserGroups( $user );

		// There can be a case when task is assigned to a user, and a case when task is assigned to user group.
		// So we should look for tasks assigned for both username and user groups
		$assigneeKeys = array_merge( [ $username ], $userGroups );
		$userAssignedPages = array_merge(
			$this->getAssignedPagesFor( [ 'pa_assignee_key' => $assigneeKeys ] ),
			$this->getAssignedPagesFor( [ 'pa_assignee_type' => 'everyone' ] )
		);

		$userId = $user->getId();
		$readConfirmations = $this->readConfirmationMechanism->getLatestReadConfirmations(
			[ $userId ],
			$userAssignedPages
		);
		$userReadConfirmations = $readConfirmations[$userId];

		$readConfirmationTasks = [];
		foreach ( $userAssignedPages as $pageId ) {
			$title = Title::newFromID( $pageId );

			// If user marked as "read" any of revisions of the page
			if ( isset( $userReadConfirmations[$pageId] ) ) {
				$pageLatestRevId = $userReadConfirmations[$pageId]['latest_rev'];
				$userLatestReadId = $userReadConfirmations[$pageId]['latest_read_rev'];

				// If marked as read revision is not the latest - create task
				if ( $pageLatestRevId !== $userLatestReadId ) {
					$readConfirmationTasks[] = new ReadConfirmationDescriptor( $title, $userLatestReadId );
				} else {
					// If marked as "read" revision is the latest - no task needed
				}

				continue;
			}

			// If user did not mark as "read" any of revisions of the page - create task
			$readConfirmationTasks[] = new ReadConfirmationDescriptor( $title );
		}

		return $readConfirmationTasks;
	}

	/**
	 * Gets pages, which specified conditions (like usernames and user groups) are assigned to.
	 * That means that they SHOULD mark such pages as read.
	 * But it gives no information regarding if pages are already marked as read.
	 *
	 * @param array $conds String keys of assignees, whose assigned pages will be looked for.
	 *	Can be either array of usernames or array of user groups (or combination of usernames and user groups)
	 * @return int[] Array with pages IDs, which assignees are assigned to
	 */
	private function getAssignedPagesFor( array $conds ): array {
		// There can be a case when read confirmation task is already assigned to both user and
		// user group (which user is member of). There also can be a case when user is member of
		// several groups and all (or just some of them) are assigned to a page.
		// So we use "DISTINCT" to avoid duplicates
		$res = $this->dbr->select(
			'bs_pageassignments',
			[
				'pa_page_id'
			],
			$conds,
			__METHOD__,
			[
				'DISTINCT'
			]
		);

		$assignedPages = [];
		foreach ( $res as $row ) {
			$assignedPages[] = $row->pa_page_id;
		}

		return $assignedPages;
	}
}
