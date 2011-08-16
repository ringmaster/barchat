<?php

class Retcon extends Plugin
{
	
	function header($args)
	{
		echo <<< HEADER
		<script type="text/javascript" src="/plugins/retcon/retcon.js"></script>
HEADER;
	}

	
	function commands($cmds){
		$cmds[1]['retcon'] = array('%^/retcon\s+(?P<status>\d+)\s+(?P<query>.+)$%i', array($this, '_retcon'), CMD_LAST);
		return $cmds;
	}
	
	function _retcon($params){

    include_once "Text/Diff.php";
    include_once "Text/Diff/Renderer.php";
    include_once "Text/Diff/Renderer/inline.php";

		$user = $params['user'];
		$channel = $params['channel'];
		$query = $params['query'];
		$status = $params['status'];

		/*		
		$output = Utils::cmdout($params);
		$output .= htmlspecialchars($query);
		
		Status::create()
			->data($output)
			->user_id($user->id)
			->channel($channel)
			->insert();
		   
		//*/
		
		$statusok = DB::get()->assoc("SELECT status, data FROM presence WHERE user_id = :user_id AND type = 'message' AND data <> '' AND status = :status ORDER BY msgtime DESC LIMIT 10", array('user_id' => $user->id, 'status' => $status));
		
		if($statusok) {

			$data = reset($statusok);
			$diff = &new Text_Diff(explode("\n",$data), explode("\n",htmlspecialchars_decode($query)));
			$renderer = &new Text_Diff_Renderer_inline();
    	$replacement = $renderer->render($diff);
			$replacement = addslashes($replacement);
    	$replacement = str_replace("\n", '\n', $replacement);
			
			$js = <<< REPLJS
retcon({$status}, '{$replacement}');
REPLJS;
	
			Status::create()
				->user_id($user->id)
				->js($js)
				->channel($channel)
				->insert();
		}		
		
		return true;
	}
	

	
		
}

?>