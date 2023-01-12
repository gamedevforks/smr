<?php declare(strict_types=1);

namespace Smr\Pages\Player\Rankings;

use Exception;
use Smr\AbstractPlayer;
use Smr\Alliance;
use Smr\Database;
use Smr\Menu;
use Smr\Page\PlayerPage;
use Smr\Page\ReusableTrait;
use Smr\Request;
use Smr\Template;

class AllianceVsAlliance extends PlayerPage {

	use ReusableTrait;

	public string $file = 'rankings_alliance_vs_alliance.php';

	/**
	 * @param array<int> $versusAllianceIDs
	 */
	public function __construct(
		private ?int $detailsAllianceID = null,
		private ?array $versusAllianceIDs = null
	) {}

	public function build(AbstractPlayer $player, Template $template): void {
		$template->assign('PageTopic', 'Alliance VS Alliance Rankings');

		Menu::rankings(1, 4);
		$db = Database::getInstance();
		$container = new self($this->detailsAllianceID);
		$template->assign('SubmitHREF', $container->href());

		$this->versusAllianceIDs ??= Request::getIntArray('alliancer', []);
		$this->detailsAllianceID ??= Request::getInt('alliance_id', $player->getAllianceID());

		// Get list of alliances that have kills or deaths
		$activeAlliances = [];
		$dbResult = $db->read('SELECT * FROM alliance WHERE game_id = ' . $db->escapeNumber($player->getGameID()) . ' AND (alliance_deaths > 0 OR alliance_kills > 0) ORDER BY alliance_kills DESC, alliance_name');
		foreach ($dbResult->records() as $dbRecord) {
			$allianceID = $dbRecord->getInt('alliance_id');
			$activeAlliances[$allianceID] = Alliance::getAlliance($allianceID, $player->getGameID(), false, $dbRecord);
		}
		$template->assign('ActiveAlliances', $activeAlliances);

		// Get list of alliances to display (max of 5)
		// These must be a subset of the active alliances
		if (count($this->versusAllianceIDs) === 0) {
			$alliance_vs_ids = array_slice(array_keys($activeAlliances), 0, 4);
			$alliance_vs_ids[] = 0;
		} else {
			$alliance_vs_ids = $this->versusAllianceIDs;
		}

		$alliance_vs = [];
		foreach ($alliance_vs_ids as $curr_id) {
			$curr_alliance = Alliance::getAlliance($curr_id, $player->getGameID());
			$container = new self($curr_id, $this->versusAllianceIDs);
			$style = '';
			if (!$curr_alliance->isNone() && $curr_alliance->hasDisbanded()) {
				$style = 'class="red"';
			}
			if ($player->getAllianceID() == $curr_id) {
				$style = 'class="bold"';
			}
			$alliance_vs[] = [
				'ID' => $curr_id,
				'DetailsHREF' => $container->href(),
				'Name' => $curr_alliance->isNone() ? 'No Alliance' : $curr_alliance->getAllianceDisplayName(),
				'Style' => $style,
			];
		}
		$template->assign('AllianceVs', $alliance_vs);

		$alliance_vs_table = [];
		foreach ($alliance_vs_ids as $curr_id) {
			$curr_alliance = Alliance::getAlliance($curr_id, $player->getGameID());
			foreach ($alliance_vs_ids as $id) {
				$row_alliance = Alliance::getAlliance($id, $player->getGameID());
				$showRed = (!$curr_alliance->isNone() && $curr_alliance->hasDisbanded()) ||
				           (!$row_alliance->isNone() && $row_alliance->hasDisbanded());
				$showBold = $curr_id == $player->getAllianceID() || $id == $player->getAllianceID();
				$style = '';
				if ($curr_id == $id && !$row_alliance->isNone()) {
					$value = '--';
					if ($showRed) {
						$style = 'class="red"';
					} elseif ($showBold) {
						$style = 'class="bold"';
					}
				} else {
					$dbResult = $db->read('SELECT kills FROM alliance_vs_alliance
								WHERE alliance_id_2 = ' . $db->escapeNumber($curr_id) . '
									AND alliance_id_1 = ' . $db->escapeNumber($id) . '
									AND game_id = ' . $db->escapeNumber($player->getGameID()));
					$value = $dbResult->hasRecord() ? $dbResult->record()->getInt('kills') : 0;
					if ($showRed && $showBold) {
						$style = 'class="bold red"';
					} elseif ($showRed) {
						$style = 'class="red"';
					} elseif ($showBold) {
						$style = 'class="bold"';
					}
				}
				$alliance_vs_table[$curr_id][$id] = [
					'Value' => $value,
					'Style' => $style,
				];
			}
		}
		$template->assign('AllianceVsTable', $alliance_vs_table);

		// Show details for a specific alliance
		$main_alliance = Alliance::getAlliance($this->detailsAllianceID, $player->getGameID());
		$mainName = $main_alliance->isNone() ? 'No Alliance' : $main_alliance->getAllianceDisplayName();
		$template->assign('DetailsName', $mainName);

		$kills = [];
		$dbResult = $db->read('SELECT * FROM alliance_vs_alliance
					WHERE alliance_id_1 = ' . $db->escapeNumber($this->detailsAllianceID) . '
						AND game_id = ' . $db->escapeNumber($player->getGameID()) . ' ORDER BY kills DESC');
		foreach ($dbResult->records() as $dbRecord) {
			$id = $dbRecord->getInt('alliance_id_2');
			$alliance_name = match (true) {
				$id > 0 => Alliance::getAlliance($id, $player->getGameID())->getAllianceDisplayName(),
				$id == 0 => 'No Alliance',
				$id == ALLIANCE_VS_FORCES => '<span class="yellow">Forces</span>',
				$id == ALLIANCE_VS_PLANETS => '<span class="yellow">Planets</span>',
				$id == ALLIANCE_VS_PORTS => '<span class="yellow">Ports</span>',
				default => throw new Exception('Unknown alliance ID: ' . $id),
			};

			$kills[] = [
				'Name' => $alliance_name,
				'Kills' => $dbRecord->getInt('kills'),
			];
		}
		$template->assign('Kills', $kills);

		$deaths = [];
		$dbResult = $db->read('SELECT * FROM alliance_vs_alliance
					WHERE alliance_id_2 = ' . $db->escapeNumber($this->detailsAllianceID) . '
						AND game_id = ' . $db->escapeNumber($player->getGameID()) . ' ORDER BY kills DESC');
		foreach ($dbResult->records() as $dbRecord) {
			$id = $dbRecord->getInt('alliance_id_1');
			$alliance_name = match (true) {
				$id > 0 => Alliance::getAlliance($id, $player->getGameID())->getAllianceDisplayName(),
				$id == 0 => 'No Alliance',
				$id == ALLIANCE_VS_FORCES => '<span class="yellow">Forces</span>',
				$id == ALLIANCE_VS_PLANETS => '<span class="yellow">Planets</span>',
				$id == ALLIANCE_VS_PORTS => '<span class="yellow">Ports</span>',
				default => throw new Exception('Unknown alliance ID: ' . $id),
			};

			$deaths[] = [
				'Name' => $alliance_name,
				'Deaths' => $dbRecord->getInt('kills'),
			];
		}
		$template->assign('Deaths', $deaths);
	}

}
