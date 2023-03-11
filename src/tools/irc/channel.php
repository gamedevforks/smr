<?php declare(strict_types=1);

use Smr\Database;

/**
 * @param resource $fp
 */
function channel_join($fp, string $rdata): bool {

	if (preg_match('/^:(.*)!(.*)@(.*)\sJOIN\s:(.*)\s$/i', $rdata, $msg)) {

		$nick = $msg[1];
		$user = $msg[2];
		$host = $msg[3];
		$channel = $msg[4];

		echo_r('[JOIN] ' . $nick . '!' . $user . '@' . $host . ' joined ' . $channel);

		$db = Database::getInstance();

		// check if we have seen this user before
		$dbResult = $db->read('SELECT * FROM irc_seen WHERE nick = :nick AND channel = :channel', [
			'nick' => $db->escapeString($nick),
			'channel' => $db->escapeString($channel),
		]);

		if ($dbResult->hasRecord()) {
			$dbRecord = $dbResult->record();
			// existing nick?
			$seen_id = $dbRecord->getInt('seen_id');

			$seen_count = $dbRecord->getInt('seen_count');
			$seen_by = $dbRecord->getNullableString('seen_by');

			if ($seen_count > 1) {
				fwrite($fp, 'PRIVMSG ' . $channel . ' :Welcome back ' . $nick . '. While being away ' . $seen_count . ' players were looking for you, the last one being ' . $seen_by . EOL);
			} elseif ($seen_count > 0) {
				fwrite($fp, 'PRIVMSG ' . $channel . ' :Welcome back ' . $nick . '. While being away ' . $seen_by . ' was looking for you.' . EOL);
			}

			$db->update(
				'irc_seen',
				[
					'signed_on' => time(),
					'signed_off' => 0,
					'user' => $user,
					'host' => $host,
					'seen_count' => 0,
					'seen_by' => null,
					'registered' => null,
				],
				['seen_id' => $seen_id],
			);

		} else {
			// new nick?
			$db->insert('irc_seen', [
				'nick' => $nick,
				'user' => $user,
				'host' => $host,
				'channel' => $channel,
				'signed_on' => time(),
			]);

			if ($nick != IRC_BOT_NICK) {
				fwrite($fp, 'PRIVMSG ' . $channel . ' :Welcome, ' . $nick . '! Most players are using Discord (' . DISCORD_URL . ') instead of IRC, but the two platforms are linked by discordbot. Anything you say here will be relayed to the Discord channel and vice versa.' . EOL);
			}
		}

		// check if player joined alliance chat
		channel_op_notification($fp, $rdata, $nick, $channel);

		return true;
	}

	return false;
}

/**
 * @param resource $fp
 */
function channel_part($fp, string $rdata): bool {

	// :Azool!Azool@coldfront-F706F7E1.co.hfc.comcastbusiness.net PART #smr-irc :
	// :SomeGuy!mrspock@coldfront-DD847655.dip.t-dialin.net PART #smr-irc
	if (preg_match('/^:(.*)!(.*)@(.*)\sPART\s(.*?)\s/i', $rdata, $msg)) {

		$nick = $msg[1];
		$user = $msg[2];
		$host = $msg[3];
		$channel = $msg[4];

		echo_r('[PART] ' . $nick . '!' . $user . '@' . $host . ' ' . $channel);

		// database object
		$db = Database::getInstance();

		$dbResult = $db->read('SELECT * FROM irc_seen WHERE nick = :nick AND channel = :channel', [
			'nick' => $db->escapeString($nick),
			'channel' => $db->escapeString($channel),
		]);

		// exiting nick?
		if ($dbResult->hasRecord()) {

			$seen_id = $dbResult->record()->getInt('seen_id');

			$db->update(
				'irc_seen',
				['signed_off' => time()],
				['seen_id' => $seen_id],
			);

		} else {
			// we don't know this one, but who cares? he just left anyway...
		}

		return true;
	}

	return false;
}
