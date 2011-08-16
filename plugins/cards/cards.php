<?php

Class CardsPlugin extends Plugin
{
	
	function shuffle(){
		$deck = 'C1C2C3C4C5C6C7C8C9C0CJCQCKS1S2S3S4S5S6S7S8S9S0SJSQSKH1H2H3H4H5H6H7H8H9H0HJHQHKD1D2D3D4D5D6D7D8D9D0DJDQDK';
		$deck = str_split($deck, 2);
		shuffle($deck);
		return $deck;
	}
	
	function commands($cmds){
		$cmds[1]['deal'] = array('%^/(deal)\b%i', array($this, '_card'), CMD_LAST);
		return $cmds;
	}
	
	function _card($params){
		$user = $params['user'];
		$channel = $params['channel'];
		$card = $params['card'];

		$output = '<div class="slash">/card ' . htmlspecialchars($card) . '</div><div class="hand">';
		$deck = $this->shuffle();
		$cards = array_slice($deck, 0, 5);
		foreach($cards as $card) {
			$output .= '<div class="card ' . $card . '">' . $card . '</div>';
		}
		$output .= '</div>';
		
		DB::get()->query("INSERT INTO presence (data, user_id, channel, cssclass) VALUES (:msg, :user_id, :channel, 'cards')", array('msg' => $output, 'user_id' => $user->id, 'channel' => $channel));
		return true;
	}
	
	function header($args)
	{
		echo <<< HEADER
<link href="/plugins/cards/cards.css" type="text/css" rel="stylesheet">
HEADER;
	}

}
?>