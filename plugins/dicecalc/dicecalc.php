<?php

include 'dicecalc/calc.php';

class DiceCalcPlugin extends Plugin
{
	
	function commands($cmds){
		$cmds[1]['calc'] = array('%^/(calc|roll)\s+(?P<query>.+)$%i', array($this, '_calc'), CMD_LAST);
		return $cmds;
	}
	
	function _calc($params){
		$user = $params['user'];
		$channel = $params['channel'];
		$query = $params['query'];
		
		$query = html_entity_decode($query);
		
		$calc = new Calc($query);
		
		echo htmlspecialchars($expression) . "\n";
		echo $calc->infix() . " = " . $calc->calc() . "\n";

		$output = Utils::cmdout($params);
		$output .= $calc->infix() . " = " . $calc->calc();
		
		Status::create()
			->data($output)
			->user_id($user->id)
			->channel($channel)
			->insert();
		
		return true;
	}
	
}
?>