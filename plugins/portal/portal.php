<?php

Class Portal extends Plugin
{

	function response_obj($obj, $roomtype, $criteria) {
		if($roomtype == 'portal') {
			
			$url = Option::get('portal', $criteria);
			if($url) {
			
				$update = new StdClass();
				$update->status = 0;
				$update->type = 'message'; //'canvasstart';
				$update->channel = "{$roomtype}:{$criteria}";
				$update->data = 'ok';
				$update->msgtime = date('Y-m-d H:i:s');
				$update->user_id = 0;
				$update->cssclass = 'canvas';
				$update->user_to = 0;
				$update->received = 0;
				$update->username = '';
				$update->nickname = '';
	//			$update->js = 'console.log("creating portal iframe");$("#portal").append(\'<iframe id="portal_pg" src="http://pd.sol.rockriverstar.com/" style="width:99%;height: 99%;border: none;"></iframe>\');';
	
				$response = array($update);
				$obj->response = $response;
				$obj->useportal = array('pd' => 
					array(
						'id' => $criteria,
						'content' => '<iframe id="portal_' . substr(md5($criteria), 6,10) . '" src="' . $url . '"></iframe>',
						'classes' => 'resize',
					),
				);
			}
		}
		return $obj;
	}
	
	function chanbar_menu($add, $room, $roomtype) {
		$roomhtml = '';
		if($roomtype == 'portal') {
			list($type, $name) = explode(':', $room->name, 2);
			$url = Option::get('portal', $name);
			$roomhtml .= '<li><a href="#" onclick="$(\'#portal_' . substr(md5($name), 6,10) . '\').attr(\'src\', \'' . $url . '\');return false;">Reset</a></li>';
		}
		return $roomhtml;		
	}
	
	
	function header($args)
	{
		echo <<< HEADER
		<script type="text/javascript" src="/plugins/retcon/retcon.js"></script>
HEADER;
	}

	
}

?>