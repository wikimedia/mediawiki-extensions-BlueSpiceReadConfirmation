<?php

use BlueSpice\Tests\BSApiTasksTestBase;

/**
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

	public function setUp() : void {
		parent::setUp();

		$oTitle = Title::newFromId( 1 );
		$oExecutingUser = self::$users['sysop']->user;
		$oAssignedUser = self::$users['uploader']->user;

		$oExecutingUser->setOption( 'echo-subscriptions-web-bs-pageassignments-action-cat', 1 );
		$oExecutingUser->saveSettings();
		$oAssignedUser->setOption( 'echo-subscriptions-web-bs-pageassignments-action-cat', 1 );
		$oAssignedUser->saveSettings();

		$oDbw = wfGetDB( DB_MASTER );
		$oDbw->delete( 'bs_pageassignments', [ 'pa_page_id' => $oTitle->getArticleID() ] );
		$aPAData = array(
				array(
					'pa_page_id' => $oTitle->getArticleID(),
					'pa_assignee_key' => $oExecutingUser->getName(),
					'pa_assignee_type' => 'user',
					'pa_position' => 0
				),
				array(
					'pa_page_id' => $oTitle->getArticleID(),
					'pa_assignee_key' => self::$users['uploader']->user->getName(),
					'pa_assignee_type' => 'user',
					'pa_position' => 0
				)
			);
		$oDbw->insert( 'bs_pageassignments', $aPAData, __METHOD__ );
	}

	public function testCheck() {
		$oTitle = Title::newFromId( 1 );
		$iPageID = $oTitle->getArticleID();
		$oExecutingUser = User::newFromName( 'Apitestsysop' );

		$oResponse = $this->executeTask(
			'check',
			[
				'pageId' => $iPageID
			]
		);

		$this->assertTrue( $oResponse->success, 'Check task failed' );
		$aPayload = $oResponse->payload;
		$this->assertArrayHasKey( 'pageId', $aPayload, 'Payload does not contain "pageId" key' );
		$this->assertArrayHasKey( 'userId', $aPayload, 'Payload does not contain "userId" key' );
		$this->assertArrayHasKey( 'userHasConfirmed', $aPayload, 'Payload does not contain "userhasConfirmed" key' );

		$this->assertEquals( 1, $aPayload['pageId'], 'Returned unexpected value for "pageId"' );
		$this->assertEquals( $oExecutingUser->getId(), $aPayload['userId'], 'Returned unexpected value for "userId"' );
		$this->assertFalse( $aPayload['userHasConfirmed'], 'Returned unexpected value for "userHasConfirmed"' );
	}

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
			array( 'event_agent_id', 'event_page_id' ),
			array( 'event_type' => 'bs-readconfirmation-remind' ),
			array(
				array( (string) $oExecutingUser->getId(), (string) $oTitle->getArticleID() ),
				array( (string) $oExecutingUser->getId(), (string) $oTitle->getArticleID() )
			)
		);
	}

	public function testConfirm() {
		$oTitle = Title::newFromId( 1 );
		$oWikiPage = WikiPage::factory( $oTitle );
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
			array( 'rc_rev_id', 'rc_user_id' ),
			array(),
			array( array( $oWikiPage->getRevision()->getId(), $oExecutingUser->getId() ) )
		);
	}
}