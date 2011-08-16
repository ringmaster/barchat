<?php

Class StatsPlugin extends Plugin
{
	
	function commands($cmds){
		$cmds[1]['stats'] = array('%^/statss$%i', array($this, '_stats'), CMD_LAST);
		$cmds[1]['killstats'] = array('%^/killstats$%i', array($this, '_killstats'), CMD_LAST);
		return $cmds;
	}
	
	function _stats($params){
		$user = $params['user'];
		$channel = $params['channel'];
		
		$message = '<div id="statdata">Loading stats...</div>';
		$js = '
$("#statdata").load("/ajax/stats");
if(intervals.stats) window.clearInterval(intervals.stats);
intervals.stats = window.setInterval(function(){$("#statdata").load("/ajax/stats");}, 10000);
$("#statdata").click(function(){$("#statdata").load("/ajax/stats");});
';

		DB::get()->query("INSERT INTO presence (data, user_id, type, cssclass, user_to, channel) VALUES (:msg, :user_id, 'system', 'ok', :user_to, '')", array('msg' => 'Updating stats added to drawer.', 'user_id' => 0, 'user_to' => $user->id));
		
		DB::get()->query("DELETE FROM drawers where indexed = 'stats' AND channel = :channel and user_id=:user_id", array('channel'=>$channel, 'user_id' => $user->id));
		DB::get()->query(
			"INSERT INTO drawers (user_id, channel, message, js, indexed) VALUES (:user_id, :channel, :message, :js, 'stats');",
			array(
				'user_id' => $user->id,
				'channel' => $channel,
				'message' => $message,
				'js' => $js,
			)
		);
		
		return true;
	}

	function _killstats($params){
		$user = $params['user'];
		$channel = $params['channel'];

		DB::get()->query("DELETE FROM drawers where indexed = 'stats' AND channel = :channel and user_id=:user_id", array('channel'=>$channel, 'user_id' => $user->id));
		
		$js = <<< KILLSTATS
bareffect(function(){if(intervals.stats) window.clearInterval(intervals.stats);});
KILLSTATS;

		DB::get()->query("INSERT INTO presence (data, user_id, type, cssclass, user_to, channel, js) VALUES (:msg, :user_id, 'system', 'ok', :user_to, '', :js)", array('msg' => 'Closed the stats drawer.', 'user_id' => 0, 'user_to' => $user->id, 'js' => $js));
		
		return true;
	}
	
	function _pingdom_data() {
		$apikey = 'eb457d7a6669e5cbd41f5ca613ef1df9';
				
		$wsdl = 'https://ws.pingdom.com/soap/PingdomAPI.wsdl';
		$client = new SoapClient($wsdl);
		
		if(!isset($_SESSION['pingdomsid'])) {
			$response = $client->Auth_login($apikey, array('username'=>'hosting@rockriverstar.com', 'password'=>'DYyYzljYmQzO'));
			if($response->status == 0) {
				$sid = $response->sessionId;
				$_SESSION['pingdomsid'] = $sid;
			}
		}
		if(isset($_SESSION['pingdomsid'])) {
			$sid = $_SESSION['pingdomsid'];
			$response = $client->Report_getCurrentStates($apikey, $_SESSION['pingdomsid']);
		}
		if($response->status == 0) {
			return $response->currentStates;
		}
		return false;
	}
	
	function _report_data() {
		$servers = DB::get()->assoc("SELECT name, value FROM options WHERE grouping = 'stats servers'");
		foreach($servers as $name => $value) {
			$servers[$name] = json_decode(file_get_contents($value));
		}
		return $servers;
	}
		
	function ajax_stats() {
		if($response = $this->_pingdom_data()) {
			echo '<table class="stats pingdom" style="float:left;"><thead><tr><th colspan="2">Pingdom</th></tr></thead><tbody>';
			$min = 9999999;
			$max = 10;
			foreach($response as $state) {
				$min = min($state->responseTime, $min);
				$max = max($state->responseTime, $max);
			}
			foreach($response as $state) {
				echo '<tr><th style="border-bottom:2px solid white;">';
				echo $state->checkName;
				echo '</th><td>';
				$since = floor(($state->responseTime - $min) * 100 / ($max - $min));
				if($state->checkState == 'CHECK_UP') {
					$color = $this->_hsv_rgb(0.3 - 0.3 * min($state->responseTime,2000) / 2000, 0.7, 0.7);
				}
				else {
					$color = $this->_hsv_rgb(0.0, 1.0, 1.0);
					$since = 100;
				}
				echo '<div style="background-color:' . $color . ';height:100%;width:' . $since . 'px;">' . sprintf('%.0f', $state->responseTime) . 'ms</div>';
				echo '</td></tr>';
			}
			echo '</tbody></table>';
		}
		
		if($response = $this->_report_data()) {
			foreach($response as $server => $values) {
				echo '<table class="stats report" style="float:left;"><thead><tr><th colspan="3">' . $server . '</th></tr></thead><tbody>';
				foreach($values as $k => $v) {
					echo '<tr><td>';
					echo $k;
					echo '</td>';
					echo '<td>';
					if($k == 'load') {
						$color = $this->_hsv_rgb(0.3 - 0.3 * min($v,1), 0.7, 0.7);
						echo '<div class="" style="background-color:' . $color . ';height:100%;width:100px;">' . $v . '</div>';
					}
					elseif(strpos($v, '.') !== false) {
						$color = $this->_hsv_rgb(0.3 - 0.3 * (10-min($v,10))/10, 0.7, 0.7);
						echo '<div class="" style="background-color:' . $color . ';height:100%;width:' . (100-$v) . 'px;">' . $v . '</div>';
					}
					else {
						echo $v;
					}
					echo '</td>';
					echo '</tr>';
				}
				echo '</tbody></table>';
			}
		}
		
		echo '<div><a href="#" onclick="$(this).parents(\'.drawers\').remove();send(\'/killstats\');return false;">Close stats</a><div>';
		die();
	}

	function _hsv_rgb ($H, $S, $V)  // HSV Values:Number 0-1 
	{                                 // RGB Results:Number 0-255 
		$RGB = array(); 
	
		if($S == 0) 
		{ 
			$R = $G = $B = $V * 255; 
		} 
		else 
		{ 
			$var_H = $H * 6; 
			$var_i = floor( $var_H ); 
			$var_1 = $V * ( 1 - $S ); 
			$var_2 = $V * ( 1 - $S * ( $var_H - $var_i ) ); 
			$var_3 = $V * ( 1 - $S * (1 - ( $var_H - $var_i ) ) ); 
	
			if       ($var_i == 0) { $var_R = $V     ; $var_G = $var_3  ; $var_B = $var_1 ; } 
			else if  ($var_i == 1) { $var_R = $var_2 ; $var_G = $V      ; $var_B = $var_1 ; } 
			else if  ($var_i == 2) { $var_R = $var_1 ; $var_G = $V      ; $var_B = $var_3 ; } 
			else if  ($var_i == 3) { $var_R = $var_1 ; $var_G = $var_2  ; $var_B = $V     ; } 
			else if  ($var_i == 4) { $var_R = $var_3 ; $var_G = $var_1  ; $var_B = $V     ; } 
			else                   { $var_R = $V     ; $var_G = $var_1  ; $var_B = $var_2 ; } 
	
			$R = $var_R * 255; 
			$G = $var_G * 255; 
			$B = $var_B * 255; 
		} 
	
		return sprintf('%02x%02x%02x', $R, $G, $B);
	} 
	
}

?>
