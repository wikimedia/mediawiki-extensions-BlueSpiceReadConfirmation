<?php

namespace BlueSpice\ReadConfirmation\Tests;

use BlueSpice\Tests\BSApiTasksTestBase;
use MediaWiki\MediaWikiServices;
use Title;

/**
 * @group Broken
 * @group medium
 * @group API
 * @group Database
 * @group BlueSpice
 * @group BlueSpiceReadConfirmation
 */
class BSApiReadConfirmationTasksTest extends BSApiTasksTestBase {
	protected function getModuleName() {
		return 'bs-readconfirmation-tasks';
	}

	public function setUp(): void {
		parent::setUp();

		$oTitle = Title::newFromId( 1 );
		$oExecutingUser = self::$users['sysop']->user;
		$oAssignedUser = self::$users['uploader']->user;

		$userOptionsManager = MediaWikiServices::getInstance()->getUserOptionsManager();
		$userOptionsManager->setOption( $oExecutingUser, 'echo-subscriptions-web-bs-pageassignments-action-cat', 1 );
		$userOptionsManager->saveOptions( $oExecutingUser );
		$userOptionsManager->setOption( $oAssignedUser, 'echo-subscriptions-web-bs-pageassignments-action-cat', 1 );
		$userOptionsManager->saveOptions( $oAssignedUser );

		$oDbw = wfGetDB( DB_PRIMARY );
		$oDbw->delete( 'bs_pageassignments', [ 'pa_page_id' => $oTitle->getArticleID() ] );
		$aPAData = [
				[
					'pa_page_id' => $oTitle->getArticleID(),
					'pa_assignee_key' => $oExecutingUser->getName(),
					'pa_assignee_type' => 'user',
					'pa_position' => 0
				],
				[
					'pa_page_id' => $oTitle->getArticleID(),
					'pa_assignee_key' => self::$users['uploader']->user->getName(),
					'pa_assignee_type' => 'user',
					'pa_position' => 0
				]
			];
		$oDbw->insert( 'bs_pageassignments', $aPAData, __METHOD__ );
	}

	/**
	 * @covers \BSApiReadConfirmationTasks::task_check
	 */
	public function testCheck() {
		$oTitle = Title::newFromId( 1 );
		$iPageID = $oTitle->getArticleID();
		$oExecutingUser = MediaWikiServices::getInstance()->getUserFactory()
			->newFromName( 'Apitestsysop' );

		$oResponse = $this->executeTask(
			'check',
			[
				'pageId' => $iPageID
			]
		);

		$this->assertTrue( $oResponse->success, 'Check task failed' );
		$aPayload = $oResponse->payload;
		$this->assertArrayHasKey(
			'pageId',
			$aPayload,
			'Payload does not contain "pageId" key'
		);
		$this->assertArrayHasKey(
			'userId',
			$aPayload,
			'Payload does not contain "userId" key'
		);
		$this->assertArrayHasKey(
			'userHasConfirmed',
			$aPayload,
			'Payload does not contain "userhasConfirmed" key'
		);

		$this->assertSame(
			1,
			$aPayload['pageId'],
			'Returned unexpected value for "pageId"'
		);
		$this->assertEquals(
			$oExecutingUser->getId(),
			$aPayload['userId'],
			'Returned unexpected value for "userId"'
		);
		$this->assertFalse(
			$aPayload['userHasConfirmed'],
			'Returned unexpected value for "userHasConfirmed"'
		);
	}

	/**
	 * @covers \BSApiReadConfirmationTasks::task_remind
	 */
	public function testRemind() {
		$oTitle = Title::newFromId( 1 );
		$oExecutingUser = self::$users['sysop']->user;
		$oAssignedUser = self::$users['uploader']->user;

		$oResponse = $this->executeTask(
			'remind',
			[
				'pageId' => $oTitle->getArticleID()
			]
		);

		$this->assertTrue( $oResponse->success, 'Remind task failed' );

		$this->assertSelect(
			'echo_event',
			[ 'event_agent_id', 'event_page_id' ],
			[ 'event_type' => 'bs-readconfirmation-remind' ],
			[
				[ (string)$oExecutingUser->getId(), (string)$oTitle->getArticleID() ],
				[ (string)$oExecutingUser->getId(), (string)$oTitle->getArticleID() ]
			]
		);
	}

	/**
	 * @covers \BSApiReadConfirmationTasks::task_confirm
	 */
	public function testConfirm() {
		$oTitle = Title::newFromId( 1 );
		$oWikiPage = $this->getServiceContainer()->getWikiPageFactory()->newFromTitle( $oTitle );
		$iPageID = $oTitle->getArticleID();
		$oExecutingUser = self::$users['sysop']->user;

		$oResponse = $this->executeTask(
			'confirm',
			[
				'pageId' => $iPageID
			]
		);

		$this->assertTrue( $oResponse->success, 'Confirm task failed' );

		$this->assertSelect(
			'bs_readconfirmation',
			[ 'rc_rev_id', 'rc_user_id' ],
			[],
			[ [ $oWikiPage->getRevisionRecord()->getId(), $oExecutingUser->getId() ] ]
		);
	}
}
