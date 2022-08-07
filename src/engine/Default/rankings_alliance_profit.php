<?php declare(strict_types=1);

$template = Smr\Template::getInstance();
$session = Smr\Session::getInstance();
$player = $session->getPlayer();

$template->assign('PageTopic', 'Alliance Profit Rankings');
Menu::rankings(1, 1);

$hofCategory = ['Trade', 'Money', 'Profit'];
$rankedStats = Rankings::allianceStatsFromHOF($hofCategory, $player->getGameID());
$ourRank = 0;
if ($player->hasAlliance()) {
	$ourRank = Rankings::ourRank($rankedStats, $player->getAllianceID());
	$template->assign('OurRank', $ourRank);
}

$template->assign('Rankings', Rankings::collectAllianceRankings($rankedStats, $player));

$numAlliances = count($rankedStats);
[$minRank, $maxRank] = Rankings::calculateMinMaxRanks($ourRank, $numAlliances);

$template->assign('FilteredRankings', Rankings::collectAllianceRankings($rankedStats, $player, $minRank, $maxRank));

$template->assign('FilterRankingsHREF', Page::create('rankings_alliance_profit.php')->href());
