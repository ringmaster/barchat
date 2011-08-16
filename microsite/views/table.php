<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
  <head>
  <meta http-equiv="content-type" content="text/html; charset=utf-8">
  <meta name="generator" content="MicroSite">
  <title>Microsite - Edit a Page</title>
	<style type="text/css">
	textarea {
		width: 400px;
		height: 4em;
	}
	tfoot td {
		text-align: right;
	}
	</style>
  </head>
  <body>

	<table style="border:1px solid #eeeeee;">
	<tbody>
	<?php 
foreach($view->vars as $k => $v) {
	if(in_array($k, array())) {
		continue;
	}
	echo '<tr><th>' . $k . '</th><td style="border-left:1px solid #eeeeee;">' . htmlspecialchars($v) . '</td></tr>';
}
?>
	</tbody>
	</table>
	
  </body>
</html>

