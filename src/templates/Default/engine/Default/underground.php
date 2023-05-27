<?php declare(strict_types=1);

/**
 * @var array<Smr\Bounty> $AllBounties
 * @var array<Smr\Bounty> $MyBounties
 * @var Smr\Template $this
 */

?>
<p>The location appears to be abandoned, until a group of
heavily-armed figures advance from the shadows.</p>
<p>&nbsp;</p>

<?php
if (count($AllBounties) > 0) { ?>
	<div class="center">Most wanted by the Underground</div><br /><?php
	$this->includeTemplate('includes/BountyList.inc.php', ['Bounties' => $AllBounties]);
}
if (count($MyBounties) > 0) { ?>
	<div class="center">Claimable Bounties</div><br /><?php
	$this->includeTemplate('includes/BountyList.inc.php', ['Bounties' => $MyBounties]);
}

if (isset($JoinHREF)) { ?>
	<p class="center">
		<a href="<?php echo $JoinHREF; ?>" class="submitStyle">Become a smuggler</a>
	</p><?php
}
