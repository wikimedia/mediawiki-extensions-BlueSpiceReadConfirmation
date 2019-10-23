<?php

namespace BlueSpice\ReadConfirmation\Privacy;

use BlueSpice\Privacy\IPrivacyHandler;
use BlueSpice\Privacy\Module\Transparency;

class Handler implements IPrivacyHandler {
	protected $db;
	protected $language;

	public function __construct( \Database $db ) {
		$this->db = $db;
		$this->language = \RequestContext::getMain()->getLanguage();
	}

	public function anonymize( $oldUsername, $newUsername ) {
		return \Status::newGood();
	}

	public function delete( \User $userToDelete, \User $deletedUser ) {
		$this->db->update(
			'bs_readconfirmation',
			[ 'rc_user_id' => $deletedUser->getId() ],
			[ 'rc_user_id' => $userToDelete->getId() ]
		);

		return \Status::newGood();
	}

	public function exportData( array $types, $format, \User $user ) {
		if ( !in_array( Transparency::DATA_TYPE_WORKING, $types ) ) {
			return \Status::newGood( [] );
		}
		$res = $this->db->select(
			'bs_readconfirmation',
			'*',
			[ 'rc_user_id' => $user->getId() ]
		);

		$data = [];
		foreach( $res as $row ) {
			$lookup = \MediaWiki\MediaWikiServices::getInstance()->getRevisionLookup();
			$rev = $lookup->getRevisionById( $row->rc_rev_id );
			if ( !$rev ) {
				continue;
			}
			$title = \Title::newFromID( $rev->getPageId() );
			if ( !$title ) {
				continue;
			}

			$timestamp = $this->language->userTimeAndDate(
				$row->rc_timestamp,
				$user
			);

			$data[] = wfMessage(
				'bs-readconfirmation-privacy-transparency-working-rc',
				$title->getPrefixedText(),
				$rev->getId(),
				$timestamp
			)->plain();
		}

		return \Status::newGood( [
			Transparency::DATA_TYPE_WORKING => $data
		] );
	}
}
