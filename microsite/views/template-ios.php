<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
  <head>
	  <meta http-equiv="content-type" content="text/html; charset=utf-8">
	  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no"/>
	  <meta name="generator" content="MicroSite">
	  <title><?php echo $title; ?></title>
	  <link rel="shortcut icon" id="favicon" href="/css/images/favicon.png" type="image/png" />
		<link rel="stylesheet" type="text/css" href="http://yui.yahooapis.com/3.0.0/build/cssreset/reset-min.css">
		<link rel="stylesheet" type="text/css" href="http://yui.yahooapis.com/3.0.0/build/cssfonts/fonts-min.css"> 
		<link rel="stylesheet" type="text/css" href="css/smoothness/jquery-ui-1.7.2.custom.css">
		<style type="text/css">
		@import url(/css/sp-ios.css);
		</style>
		<script type="text/javascript" src="/js/jquery.1.4.js"></script>
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
<div id="portal" class="portal"></div>

<script type="text/javascript">
	var user_id = <?php echo $user_id; ?>;
	var username = '<?php echo $username; ?>';
	var nickname = '<?php echo $nickname; ?>';
	var sp_key_md5 = '<?php echo md5($session_key); ?>';
	var ios = true;
</script>
<noscript>
	<div id="dingobj">
	</div>
</noscript>
		<div id="more_arrow"><div></div></div>
		
		</div>
		<div id="holiday"></div>
		<div id="office"></div>
		<div id="rightpanel" class="toolpanel collapsed" oldwidth="200"><div class="toolport"><?php echo $widgets; ?></div></div>
    <div id="command">
			<table id="commandbutton_wrap">
				<tbody>
				<tr>
					<td id="commandline_status">
					</td>
					<td id="commandline_wrap">
						<div id="command_size" style="position:relative;width:95%;padding:5px;font-family:arial,helvetica,clean,sans-serif;"></div>
						<textarea id="commandline" style="font-family:arial,helvetica,clean,sans-serif;"></textarea>
					</td>
					<td id="command_buttons">
					</td>
				</tr>
				</tbody>
			</table>
			<div class="dock">
				<div id="names" class="acc dockable"></div>
				<div id="uploads" class="acc dockable"></div>
				<div id="options" class="acc"></div>
			</div>
		</div>

  </body>
</html>
