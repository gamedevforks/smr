<?php declare(strict_types=1);

use Smr\Exceptions\UserError;
use Smr\HardwareType;
use Smr\Path;
use Smr\PlotGroup;
use Smr\TradeGood;
use Smr\TransactionType;

class Plotter {

	public static function getX(PlotGroup $xType, int|string $X, int $gameID, AbstractSmrPlayer $player = null): mixed {
		// Special case for Location categories (i.e. Bar, HQ, SafeFed)
		if (!is_numeric($X)) {
			if ($xType != PlotGroup::Locations) {
				throw new Exception('Non-numeric X only exists for Locations');
			}
			return $X;
		}

		// In all other cases, X is either an int or a numeric string
		if (is_string($X)) {
			$X = str2int($X);
		}

		// Helper function for plots to trade goods
		$getGoodWithTransaction = function(int $goodID) use ($xType, $player) {
			$good = TradeGood::get($goodID);
			if (isset($player) && !$player->meetsAlignmentRestriction($good->alignRestriction)) {
				throw new Exception('Player trying to access alignment-restricted good!');
			}
			return [
				'Type' => 'Good',
				'GoodID' => $goodID,
				'TransactionType' => TransactionType::from(explode(' ', $xType->value)[0]),
			];
		};

		return match ($xType) {
			PlotGroup::Technology => HardwareType::get($X),
			PlotGroup::Ships => SmrShipType::get($X),
			PlotGroup::Weapons => SmrWeaponType::getWeaponType($X),
			PlotGroup::Locations => SmrLocation::getLocation($gameID, $X),
			PlotGroup::SellGoods, PlotGroup::BuyGoods => $getGoodWithTransaction($X),
			PlotGroup::Galaxies => SmrGalaxy::getGalaxy($gameID, $X), // $X is the galaxyID
		};
	}

	/**
	 * Returns the shortest path from $sector to $x as a Distance object.
	 * The path is guaranteed reversible ($x -> $sector == $sector -> $x), which
	 * is not true for findDistanceToX. If $x is not a SmrSector, then this
	 * function does 2x the work.
	 */
	public static function findReversiblePathToX(mixed $x, SmrSector $sector, AbstractSmrPlayer $needsToHaveBeenExploredBy = null, AbstractSmrPlayer $player = null): Path {
		if ($x instanceof SmrSector) {

			// To ensure reversibility, always plot lowest to highest.
			$reverse = $sector->getSectorID() > $x->getSectorID();
			if ($reverse) {
				$start = $x;
				$end = $sector;
			} else {
				$start = $sector;
				$end = $x;
			}
			$path = self::findDistanceToX($end, $start, true, $needsToHaveBeenExploredBy, $player);
			if ($path === false) {
				throw new UserError('Unable to plot from ' . $sector->getSectorID() . ' to ' . $x->getSectorID() . '.');
			}
			// Reverse if we plotted $x -> $sector (since we want $sector -> $x)
			if ($reverse) {
				$path->reversePath();
			}

		} else {

			// At this point we don't know what sector $x will be at
			$path = self::findDistanceToX($x, $sector, true, $needsToHaveBeenExploredBy, $player);
			if ($path === false) {
				throw new UserError('Unable to find what you\'re looking for, it either hasn\'t been added to this game or you haven\'t explored it yet.');
			}
			// Now that we know where $x is, make sure path is reversible
			// (i.e. start sector < end sector)
			if ($path->getEndSectorID() < $sector->getSectorID()) {
				$endSector = SmrSector::getSector($sector->getGameID(), $path->getEndSectorID());
				$path = self::findDistanceToX($sector, $endSector, true);
				if ($path === false) {
					throw new Exception('Unable to find reverse path');
				}
				$path->reversePath();
			}

		}
		return $path;
	}

	/**
	 * Returns the shortest path from $sector to $x as a Path object.
	 * The resulting path prefers neighbors in their order in SmrSector->links,
	 * (i.e. up, down, left, right).
	 *
	 * @param mixed $x If the string 'Distance', then distances to all visited sectors will
	 *                 be returned. Otherwise, must be a type implemented by SmrSector::hasX,
	 *                 and will only return distances to sectors for which hasX returns true.
	 *
	 * @return ($useFirst is true ? Smr\Path|false : array<int, Smr\Path>)
	 */
	public static function findDistanceToX(mixed $x, SmrSector $sector, bool $useFirst, AbstractSmrPlayer $needsToHaveBeenExploredBy = null, AbstractSmrPlayer $player = null, int $distanceLimit = 10000, int $lowLimit = 0, int $highLimit = 100000): array|Path|false {
		$warpAddIndex = TURNS_WARP_SECTOR_EQUIVALENCE - 1;

		$checkSector = $sector;
		$gameID = $sector->getGameID();
		$distances = [];
		$sectorsTravelled = 0;
		$visitedSectors = [];
		$visitedSectors[$checkSector->getSectorID()] = true;

		$distanceQ = [];
		for ($i = 0; $i <= TURNS_WARP_SECTOR_EQUIVALENCE; $i++) {
			$distanceQ[] = [];
		}
		//Warps first as a slight optimisation due to how visitedSectors is set.
		if ($checkSector->hasWarp() === true) {
			$d = new Path($checkSector->getSectorID());
			$d->addWarp($checkSector->getWarp());
			$distanceQ[$warpAddIndex][] = $d;
		}
		foreach ($checkSector->getLinks() as $nextSector) {
			$visitedSectors[$nextSector] = true;
			$d = new Path($checkSector->getSectorID());
			$d->addLink($nextSector);
			$distanceQ[0][] = $d;
		}
		$maybeWarps = 0;
		while ($maybeWarps <= TURNS_WARP_SECTOR_EQUIVALENCE) {
			$sectorsTravelled++;
			if ($sectorsTravelled > $distanceLimit) {
				break;
			}
			$distanceQ[] = [];
			$q = array_shift($distanceQ);
			if (count($q) === 0) {
				$maybeWarps++;
				continue;
			}
			$maybeWarps = 0;
			while (($distance = array_shift($q)) !== null) {
				$checkSectorID = $distance->getEndSectorID();
				$visitedSectors[$checkSectorID] = true; // This is here for warps, because they are delayed visits if we set this before the actual visit we'll get sectors marked as visited long before they are actually visited - causes problems when it's quicker to walk to the warp exit than to warp there.
																// We still need to mark walked sectors as visited before we go to each one otherwise we get a huge number of paths being checked twice (up then left, left then up are essentially the same but if we set up-left as visited only when we actually check it then it gets queued up twice - nasty)
				if ($checkSectorID >= $lowLimit && $checkSectorID <= $highLimit) {
					$checkSector = SmrSector::getSector($gameID, $checkSectorID);
					// Does this sector satisfy our criteria?
					if ($x == 'Distance' || (($needsToHaveBeenExploredBy === null || $needsToHaveBeenExploredBy->hasVisitedSector($checkSector->getSectorID())) === true
							&& $checkSector->hasX($x, $player) === true)) {
						if ($useFirst === true) {
							return $distance;
						}
						$distances[$checkSector->getSectorID()] = $distance;
					}
					//Warps first as a slight optimisation due to how visitedSectors is set.
					if ($checkSector->hasWarp() === true) {
						if (!isset($visitedSectors[$checkSector->getWarp()])) {
							$cloneDistance = clone($distance);
							$cloneDistance->addWarp($checkSector->getWarp());
							$distanceQ[$warpAddIndex][] = $cloneDistance;
						}
					}
					foreach ($checkSector->getLinks() as $nextSector) {
						if (!isset($visitedSectors[$nextSector])) {
							$visitedSectors[$nextSector] = true;

							$cloneDistance = clone($distance);
							$cloneDistance->addLink($nextSector);
							$distanceQ[0][] = $cloneDistance;
						}
					}
				}
			}
		}
		if ($useFirst === true) {
			return false;
		}
		return $distances;
	}

	/**
	 * @param array<int, \SmrPort> $ports
	 * @param array<int, bool> $races
	 * @return array<int, array<int, Smr\Path>>
	 */
	public static function calculatePortToPortDistances(array $ports, array $races, int $distanceLimit = 10000, int $lowLimit = 0, int $highLimit = 100000): array {
		$distances = [];
		foreach ($ports as $port) {
			$sectorID = $port->getSectorID();
			if ($races[$port->getRaceID()] && $sectorID >= $lowLimit && $sectorID <= $highLimit) {
				$distances[$sectorID] = self::findDistanceToOtherPorts($port->getSector(), $distanceLimit, $lowLimit, $highLimit);
			}
		}
		return $distances;
	}

	/**
	 * @return array<int, Smr\Path>
	 */
	public static function findDistanceToOtherPorts(SmrSector $sector, int $distanceLimit = 10000, int $lowLimit = 0, int $highLimit = 100000): array {
		return self::findDistanceToX('Port', $sector, false, null, null, $distanceLimit, $lowLimit, $highLimit);
	}

}
