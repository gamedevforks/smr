<?php declare(strict_types=1);

		$session = Smr\Session::getInstance();
		$var = $session->getCurrentVar();
		$player = $session->getPlayer();

		$player->deleteMission($var['MissionID']);

		Page::create('current_sector.php')->go();
