<?php declare(strict_types=1);

namespace Smr\Pages\Player;

use Exception;
use Smr\AbstractPlayer;
use Smr\Database;
use Smr\Page\PlayerPageProcessor;
use Smr\Request;

class AllianceRolesProcessor extends PlayerPageProcessor {

	public function __construct(
		private readonly ?int $roleID = null
	) {}

	public function build(AbstractPlayer $player): never {
		$db = Database::getInstance();

		$alliance_id = $player->getAllianceID();

		// Checkbox inputs only post if they are checked
		$unlimited = Request::has('unlimited');
		$positiveBalance = Request::has('positive');
		$changePass = Request::has('changePW');
		$removeMember = Request::has('removeMember');
		$changeMOD = Request::has('changeMoD');
		$changeRoles = Request::has('changeRoles') || ($this->roleID === ALLIANCE_ROLE_LEADER); //Leader can always change roles.
		$planetAccess = Request::has('planets');
		$mbMessages = Request::has('mbMessages');
		$exemptWith = Request::has('exemptWithdrawals');
		$sendAllMsg = Request::has('sendAllMsg');
		$viewBonds = Request::has('viewBonds');
		$opLeader = Request::has('opLeader');

		if ($unlimited) {
			$withPerDay = ALLIANCE_BANK_UNLIMITED;
		} else {
			$withPerDay = Request::getInt('maxWith');
		}
		if ($withPerDay < 0 && $withPerDay != ALLIANCE_BANK_UNLIMITED) {
			create_error('You must enter a number for max withdrawals per 24 hours.');
		}
		if ($withPerDay == ALLIANCE_BANK_UNLIMITED && $positiveBalance) {
			create_error('You cannot have both unlimited withdrawals and a positive balance limit.');
		}

		// with empty role the user wants to create a new entry
		$roleName = Request::get('role');
		if ($this->roleID === null) {
			// role empty too? that doesn't make sence
			if (empty($roleName)) {
				throw new Exception('Empty role name is not allowed');
			}

			$db->lockTable('alliance_has_roles');

			// get last id (always has one, since some roles are auto-bestowed)
			$dbResult = $db->read('SELECT MAX(role_id)
						FROM alliance_has_roles
						WHERE game_id = :game_id
							AND alliance_id = :alliance_id', [
				'game_id' => $db->escapeNumber($player->getGameID()),
				'alliance_id' => $db->escapeNumber($alliance_id),
			]);
			$role_id = $dbResult->record()->getInt('MAX(role_id)') + 1;

			$db->insert('alliance_has_roles', [
				'alliance_id' => $db->escapeNumber($alliance_id),
				'game_id' => $db->escapeNumber($player->getGameID()),
				'role_id' => $db->escapeNumber($role_id),
				'role' => $db->escapeString($roleName),
				'with_per_day' => $db->escapeNumber($withPerDay),
				'positive_balance' => $db->escapeBoolean($positiveBalance),
				'remove_member' => $db->escapeBoolean($removeMember),
				'change_pass' => $db->escapeBoolean($changePass),
				'change_mod' => $db->escapeBoolean($changeMOD),
				'change_roles' => $db->escapeBoolean($changeRoles),
				'planet_access' => $db->escapeBoolean($planetAccess),
				'exempt_with' => $db->escapeBoolean($exemptWith),
				'mb_messages' => $db->escapeBoolean($mbMessages),
				'send_alliance_msg' => $db->escapeBoolean($sendAllMsg),
				'op_leader' => $db->escapeBoolean($opLeader),
				'view_bonds' => $db->escapeBoolean($viewBonds),
			]);
			$db->unlock();
		} else {
			if (empty($roleName)) {
				// if no role is given we delete that entry
				if ($this->roleID === ALLIANCE_ROLE_LEADER) {
					create_error('You cannot delete the leader role.');
				} elseif ($this->roleID === ALLIANCE_ROLE_NEW_MEMBER) {
					create_error('You cannot delete the new member role.');
				}
				$db->delete('alliance_has_roles', [
					'game_id' => $db->escapeNumber($player->getGameID()),
					'alliance_id' => $db->escapeNumber($alliance_id),
					'role_id' => $db->escapeNumber($this->roleID),
				]);
			} else {
				// otherwise we update it
				$db->write('UPDATE alliance_has_roles
							SET role = :role,
							with_per_day = :with_per_day,
							positive_balance = :positive_balance,
							remove_member = :remove_member,
							change_pass = :change_pass,
							change_mod = :change_mod,
							change_roles = :change_roles,
							planet_access = :planet_access,
							exempt_with = :exempt_with,
							mb_messages = :mb_messages,
							send_alliance_msg = :send_alliance_msg,
							op_leader = :op_leader,
							view_bonds = :view_bonds
							WHERE game_id = :game_id
								AND alliance_id = :alliance_id
								AND role_id = :role_id', [
					'alliance_id' => $db->escapeNumber($alliance_id),
					'game_id' => $db->escapeNumber($player->getGameID()),
					'role_id' => $db->escapeNumber($this->roleID),
					'role' => $db->escapeString($roleName),
					'with_per_day' => $db->escapeNumber($withPerDay),
					'positive_balance' => $db->escapeBoolean($positiveBalance),
					'remove_member' => $db->escapeBoolean($removeMember),
					'change_pass' => $db->escapeBoolean($changePass),
					'change_mod' => $db->escapeBoolean($changeMOD),
					'change_roles' => $db->escapeBoolean($changeRoles),
					'planet_access' => $db->escapeBoolean($planetAccess),
					'exempt_with' => $db->escapeBoolean($exemptWith),
					'mb_messages' => $db->escapeBoolean($mbMessages),
					'send_alliance_msg' => $db->escapeBoolean($sendAllMsg),
					'op_leader' => $db->escapeBoolean($opLeader),
					'view_bonds' => $db->escapeBoolean($viewBonds),
				]);
			}

		}
		$container = new AllianceRoles();
		$container->go();
	}

}
