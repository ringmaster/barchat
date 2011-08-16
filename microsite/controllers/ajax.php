<?php

class AjaxController
{
	function __call($method, $path)
	{
		$method = preg_replace('%[^\w_]%', '', $method);
		Plugin::call('ajax_' . $method, $path);
	}
	
}

?>