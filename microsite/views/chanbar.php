<ul>
<?php echo $roomhtml; ?>
<li id="add_chan"><a href="#" class="button">add channel</a><div class="submenu"><ul><li>New: <input type="text" id="new_chan"></li><?php echo $channelhtml; ?></ul></div></li>
<li id="search"><a href="#" class="button">search</a><div class="submenu"><ul><li>Search: <input type="text" id="searchbox"
	onfocus="$(this).parents('.submenu').css({display: 'block'});"
	onblur="$(this).parents('.submenu').css({display: null});"
	onkeypress="if(event.keyCode==13){dosearch($(this).val());;$(this).val('');return false;}"
></li></ul></div></li>
<li id="volume">
	<a href="#" id="v_ding" class="button">ding</a>
	<div class="submenu">
		<ul><li><a href="#" onclick="setmute('ding');return false;"><img src="/css/images/status_online.png"> ding</a></li>
		<li><a href="#" onclick="setmute('squelch');return false;"><img src="/css/images/status_away.png"> squelch</a></li>
		<li><a href="#" onclick="setmute('mute');return false;"><img src="/css/images/status_busy.png"> mute</a></li></ul>
	</div>
</li>

<li id="settings" class="option"><a href="#" class="button">settings</a></li>
<li id="files" class="option"><a href="#" class="button">files</a></li>

</ul>
<br style="clear:both;">