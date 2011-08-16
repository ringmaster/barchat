<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
  <head>
	  <meta http-equiv="content-type" content="text/html; charset=utf-8">
	  <meta name="generator" content="MicroSite">
	  <title><?php echo $title; ?></title>
	  <link rel="shortcut icon" id="favicon" href="/css/images/favicon.png" type="image/png" />
		<link rel="stylesheet" type="text/css" href="http://yui.yahooapis.com/3.0.0/build/cssreset/reset-min.css">
		<link rel="stylesheet" type="text/css" href="http://yui.yahooapis.com/3.0.0/build/cssfonts/fonts-min.css"> 
		<link rel="stylesheet" type="text/css" href="css/smoothness/jquery-ui-1.7.2.custom.css">
		<meta name="viewport" content="height=device-height, width=device-width, user-scalable=no" />
		<style type="text/css">
		@import url(/css/sp.css?time=<?php echo time(); ?>);
		</style>
		<script type="text/javascript" src="http://ajax.googleapis.com/ajax/libs/jquery/1.4.1/jquery.min.js"></script>
		<script type="text/javascript" src="/js/jqueryui.1.7.2.js"></script>
		<script type="text/javascript" src="/js/jquery.easing.1.3.js"></script>
		<script type="text/javascript" src="/js/jquery.path.js"></script>
		<!--script type="text/javascript" src="http://www.appelsiini.net/download/jquery.jeditable.mini.js"></script-->
		<script type="text/javascript" src="/js/sp.js"></script>
		<script type="text/javascript" src="/js/blocktea.js"></script>
		<script type="text/javascript" src="/js/swfupload.js"></script> 
		<!--script type="text/javascript" src="/js/jquery.scrollTo-min.js"></script-->
		<script type="text/javascript" src="/js/AC_RunActiveContent.js"></script>
		<script type="text/javascript" src="/js/barchat.namespaced.pack.js"></script>
		<script type="text/javascript" src="/js/codepress/codepress.js"></script>
		<script type="text/javascript" src="/js/syntax/scripts/shCore.js"></script>
		<script type="text/javascript" src="/js/syntax/scripts/shBrushBash.js"></script>
		<script type="text/javascript" src="/js/syntax/scripts/shBrushCss.js"></script>
		<script type="text/javascript" src="/js/syntax/scripts/shBrushDiff.js"></script>
		<script type="text/javascript" src="/js/syntax/scripts/shBrushJScript.js"></script>
		<script type="text/javascript" src="/js/syntax/scripts/shBrushPhp.js"></script>
		<script type="text/javascript" src="/js/syntax/scripts/shBrushPlain.js"></script>
		<script type="text/javascript" src="/js/syntax/scripts/shBrushSql.js"></script>
		<script type="text/javascript" src="/js/syntax/scripts/shBrushXml.js"></script>
		<script type="text/javascript" src="/js/fancybox/jquery.mousewheel-3.0.2.pack.js"></script>
		<script type="text/javascript" src="/js/fancybox/jquery.fancybox-1.3.0.pack.js"></script>
		<script type="text/javascript" src="/js/jquery.cssrule.js"></script>
		
		<link type="text/css" rel="stylesheet" href="/js/syntax/styles/shCore.css"/>
		<link type="text/css" rel="stylesheet" href="/js/syntax/styles/shThemeDefault.css"/>

		<script type="text/javascript">
			SyntaxHighlighter.config.clipboardSwf = '/js/syntax/scripts/clipboard.swf';
			var cur_chan = '<?php echo $cur_chan; ?>';
		</script>
	
		<script type="text/javascript">$P = new PHP_JS();</script>
		<?php Plugin::call('header'); ?>
  </head>
  <body>
<div id="drawer"><table></table></div>
<div id="mainscroller">
	
<table id="notices" class="notices"></table>
<div id="portal"></div>

<script type="text/javascript">
	var user_id = <?php echo $user_id; ?>;
	var username = '<?php echo $username; ?>';
	var nickname = '<?php echo $nickname; ?>';
	var ios = false;
	var sp_key_md5 = '<?php echo md5($session_key); ?>';
	if (AC_FL_RunContent == 0) {
		alert("This page requires AC_RunActiveContent.js.");
	} else {
		AC_FL_RunContent(
			'codebase', 'http://download.macromedia.com/pub/shockwave/cabs/flash/swflash.cab#version=8,0,0,0',
			'width', '1',
			'height', '1',
			'src', '/js/ding',
			'quality', 'high',
			'pluginspage', 'http://www.macromedia.com/go/getflashplayer',
			'align', 'middle',
			'play', 'true',
			'loop', 'true',
			'scale', 'showall',
			'wmode', 'window',
			'devicefont', 'false',
			'id', 'ding',
			'bgcolor', '#ffffff',
			'name', 'ding',
			'menu', 'true',
			'allowFullScreen', 'false',
			'allowScriptAccess','always',
			'movie', '/js/ding',
			'salign', ''
			); //end AC code
	}
</script>
<noscript>
	<div id="dingobj">
	<object classid="clsid:d27cdb6e-ae6d-11cf-96b8-444553540000" codebase="http://download.macromedia.com/pub/shockwave/cabs/flash/swflash.cab#version=8,0,0,0" width="250" height="20" id="ding">
	<param name="allowScriptAccess" value="always" />
	<param name="allowFullScreen" value="false" />
	<param name="movie" value="/js/ding.swf" /><param name="quality" value="high" /><param name="bgcolor" value="#ffffff" /><embed src="/js/ding.swf" quality="high" bgcolor="#ffffff" width="250" height="20" name="ding" align="middle" allowScriptAccess="always" allowFullScreen="false" type="application/x-shockwave-flash" pluginspage="http://www.macromedia.com/go/getflashplayer" />
	</object>
	</div>
</noscript>
		<div id="more_arrow"><div></div></div>
		
		<div id="command">
			<table id="commandbutton_wrap">
				<tbody>
				<tr>
					<td id="commandline_status">
					</td>
					<td id="commandline_wrap">
						<div id="command_size" style="position:absolute;top:-1000000px;width:95%;padding:5px;font-family:arial,helvetica,clean,sans-serif;"></div>
						<textarea id="commandline" style="font-family:arial,helvetica,clean,sans-serif;"></textarea>
						<div id="richedit"><div id="editorcontrols"><label>Language: <select id="language" onChange="$('#commandline').val(cp.getCode());cp.edit('commandline', $(this).val());">
							<option value="php">PHP</option>
							<option value="javascript">Javascript</option>
							<option value="css">CSS</option>
							<option value="html">HTML</option>
							<option value="text">Plaintext</option>
							<option value="XSL">XSL</option>
							<option value="decorecss">Room CSS</option>
							<option value="decorhtm">Room HTML</option>
						</select></label></div><div id="editor"></div></div>
					</td>
					<td id="command_buttons">
						<button id="toggle_code">Code</button><button id="send_msg" onClick="send_click();">Send</button>
					</td>
				</tr>
				</tbody>
			</table>
			<div id="chanbar">
				<?php echo $chanbar; ?>
			</div>
			<div class="dock">
				<div id="names" class="acc dockable"></div>
				<div id="uploads" class="acc dockable"></div>
				<div id="options" class="acc"></div>
			</div>
		</div>

		</div>
		<div id="holiday"></div>
		<div id="office"></div>
		<div id="rightpanel" class="toolpanel collapsed" oldwidth="200"><div class="toolport"><?php echo $widgets; ?></div></div>
  </body>
</html>
