<?php declare(strict_types=1);

if (!isset($var['race_id'])) {
	SmrSession::updateVar('race_id', $player->getRaceID());
}
$raceID = $var['race_id'];

$template->assign('PageTopic', 'Ruling Council Of ' . Globals::getRaceName($raceID));
$template->assign('RaceID', $raceID);

Menu::council($raceID);

// check for relations here
require_once(get_file_loc('council.inc.php'));
modifyRelations($raceID);
checkPacts($raceID);
