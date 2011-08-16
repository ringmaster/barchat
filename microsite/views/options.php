<iframe id="optionstarget" name="optionstarget" src="about:blank" style="display:none;"></iframe>
<form method="post" action="/options/save" target="optionstarget">

<table id="optionstable" style="width:100%;">
<tr class="sectionrow"><td colspan="2"><h2>System Options</h2></td></tr>
<?php if(count($system)> 0): ?>
<?php foreach($system as $option): ?>
<?php if($option->grouping != $grouping): $grouping = $option->grouping; ?>
<tr class="grouprow"><td colspan="2"><h3><?php echo $grouping; ?></h3></td></tr>
<?php endif; ?>

<tr class="optionrow" style="height: 2.0em;"><td style="white-space: nowrap;"><?php echo $option->name; ?></td>
<td style="width:70%; height: 1.2em;">
<?php if($option->ispassword) : ?>
<input type="password" name="option[<?php echo $option->id; ?>]" value="<?php echo str_repeat('*', strlen($option->value)); ?>" style="width:100%;">
<?php elseif($option->istoggle) : ?>
<input type="checkbox" name="option[<?php echo $option->id; ?>]" value="1" <?php echo intval($option->value) ? 'checked="checked"' : ''; ?>>
<?php else : ?>
<input type="text" name="option[<?php echo $option->id; ?>]" value="<?php echo htmlspecialchars($option->value); ?>" style="width:100%;">
<?php endif; ?>
</td></tr>
<?php endforeach; ?>
<?php endif; ?>

<?php if(count($user)> 0): ?>
<tr class="sectionrow"><td colspan="2"><h2>User Options</h2></td></tr>
<?php foreach($user as $option): ?>
<?php if($option->grouping != $grouping): $grouping = $option->grouping; ?>
<tr class="grouprow"><td colspan="2"><h3><?php echo $grouping; ?></h3></td></tr>
<?php endif; ?>

<tr class="optionrow"><td style="white-space: nowrap;"><?php echo $option->name; ?></td>
<td style="width:70%; height: 1.2em;">
<?php if($option->ispassword) : ?>
<input type="password" name="option[<?php echo $option->id; ?>]" value="<?php echo str_repeat('*', strlen($option->value)); ?>" style="width:100%;">
<?php elseif($option->istoggle) : ?>
<input type="checkbox" name="option[<?php echo $option->id; ?>]" value="1" <?php echo intval($option->value) ? 'checked="checked"' : ''; ?>>
<?php else : ?>
<input type="text" name="option[<?php echo $option->id; ?>]" value="<?php echo htmlspecialchars($option->value); ?>" style="width:100%;">
<?php endif; ?>
</td></tr>
<?php endforeach; ?>
<?php endif; ?>

</table>

<input type="submit" value="Save Settings" style="margin-top: 15px;">

</form>