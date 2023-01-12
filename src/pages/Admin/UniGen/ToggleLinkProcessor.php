<?php declare(strict_types=1);

namespace Smr\Pages\Admin\UniGen;

use Smr\Account;
use Smr\Page\AccountPageProcessor;
use Smr\Request;
use Smr\Sector;

class ToggleLinkProcessor extends AccountPageProcessor {

	public function __construct(
		private readonly int $gameID,
		private readonly int $galaxyID
	) {}

	public function build(Account $account): never {
		$linkSector = Sector::getSector($this->gameID, Request::getInt('SectorID'));
		$linkSector->toggleLink(Request::get('Dir'));
		Sector::saveSectors();

		$container = new EditGalaxy($this->gameID, $this->galaxyID);
		$container->go();
	}

}
