<?php foreach($names as $name): ?>
	<div class="onename <?php echo (($name->active) ? 'active' : (is_null($name->active) ? 'absent' : 'inactive')) . ' ' . ($name->loggedin ? 'online' : 'offline'); ?>" id="user_presence_<?php echo $name->id; ?>">
	<a href="#" onclick="joinRoom('office:<?php echo $name->username; ?>');return false;">
	<?php if($name->nickname != '' && $name->nickname != $name->username): ?>
		<?php echo $name->nickname; ?> <small> (<?php echo $name->username; ?>)</small>
	<?php else: ?>
		<?php echo $name->username; ?>
	<?php endif; ?>
	</a>
	<?php if(!$name->loggedin): ?>
		<small class="lastactive"><?php echo Utils::time_between(strtotime($name->lastping)); ?></small>
	<?php endif; ?>
	<?php if($name->status != ''): ?>
		<small class="userstatus"> &rarr; <?php echo $name->status; ?></small>
	<?php endif; ?>
	<?php if(count(array_diff($name->channels, $yourein))): ?>
	<br/>
	<span class="channels">
	<?php foreach($name->channels as $channel): ?>
	<?php if(!in_array($channel, $yourein)): ?>
	<a href="#" onclick="joinRoom('<?php echo addslashes($channel); ?>');return false;"><?php echo $channel; ?></a> 
	<?php endif; ?>
	<?php endforeach; ?>
	</span>
	<?php endif; ?>
	</div>
<?php endforeach; ?>
<div class="checkpoll"></div>