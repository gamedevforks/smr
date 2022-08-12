<?php declare(strict_types=1);

use Smr\Pages\Player\AttackForcesProcessor;
use Smr\Pages\Player\CurrentSector;
use Smr\ShipClass;

function hit_sector_mines(AbstractSmrPlayer $player): void {

	$sectorForces = $player->getSector()->getForces();
	Sorter::sortByNumMethod($sectorForces, 'getMines', true);
	$mine_owner_id = null;
	foreach ($sectorForces as $forces) {
		if ($forces->hasMines() && !$player->forceNAPAlliance($forces->getOwner())) {
			$mine_owner_id = $forces->getOwnerID();
			break;
		}
	}

	if ($mine_owner_id === null) {
		return;
	}

	$ship = $player->getShip();
	if ($player->hasNewbieTurns() || $ship->getClass() === ShipClass::Scout) {
		$turns = $sectorForces[$mine_owner_id]->getBumpTurnCost($ship);
		$player->takeTurns($turns, $turns);
		if ($player->hasNewbieTurns()) {
			$flavor = 'Because of your newbie status you have been spared from the harsh reality of the forces';
		} else {
			$flavor = 'Your ship was sufficiently agile to evade them';
		}
		$msg = 'You have just flown past a sprinkle of mines.<br />' . $flavor . ',<br />but it has cost you <span class="red">' . pluralise($turns, 'turn') . '</span> to navigate the minefield safely.';
		$container = new CurrentSector(message: $msg);
		$container->go();
	} else {
		$container = new AttackForcesProcessor($mine_owner_id, bump: true);
		$container->go();
	}

}
