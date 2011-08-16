<?php

class MapPlugin extends Plugin
{
	
	function commands($cmds){
		$cmds[1]['map'] = array('%^/(map)\s+(?P<query>.+)$%i', array($this, '_map'), CMD_LAST);
		return $cmds;
	}
	
	function _map($params){
		$user = $params['user'];
		$channel = $params['channel'];

		$status = DB::get()->val('SELECT max(status) FROM presence');
		$sid = $status . '__' . rand(1000, 9999);
		$output = '<div class="slash">/map ' . htmlspecialchars($params['query']) . '</div>';
		$output .= '<div id="map' . $sid . '" style="width:100%;height:300px;"></div><script type="text/javascript">showAddress("' . addslashes($params['query']) . ' ", $(\'#map' . $sid . '\')[0])</script>';

		DB::get()->query("INSERT INTO presence (data, user_id, channel, cssclass) VALUES (:msg, :user_id, :channel, 'map')", array('msg' => $output, 'user_id' => $user->id, 'channel' => $channel));
		return true;
	}
	
	function header($args)
	{
		$mapsapikey = 'ABQIAAAApeGLl9kUv5rXVftlE_FDrBRkCbeXjbqPLK8Z47spbPDOcZojBhTO1piepwWEV40aL2ZAnoNoWag3xA'; //DB::get()->val("SELECT value FROM options WHERE name = 'Map API Key' AND grouping = 'Google API';");
		echo <<< HEADER
		<script src="http://maps.google.com/maps?file=api&amp;v=2&amp;sensor=false&amp;key={$mapsapikey}" type="text/javascript"></script>
HEADER;
	}
	
	function autocomplete($auto, $cmd){
		$auto[] = "/map \tlocation address or placename";
		return $auto;
	}
	
	function get_options($options){
		$options[] = array('Google API', 'Map API Key');
		return $options;
	}
	
}
?>
