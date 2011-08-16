<?php

Class EaglesPlugin extends Plugin
{
	
	function commands($cmds){
		$cmds[1]['eagles'] = array('%^/(eagles)\b%i', array($this, '_eagles'), CMD_LAST);
		return $cmds;
	}
	
	function _eagles($params){
		$user = $params['user'];
		$channel = $params['channel'];

		$rmsg = $params['eagles'];
		if(trim($rmsg) == '') {
			$rmsg = '';
		}
		

		$schedule = array(
			array("week"=>"Playoffs", "date"=>"January 9", "team"=>"Green Bay", "stadium"=>"Lincoln Financial Field", "time"=>"4:30 PM", "tv"=>"FOX", "score" => "21 - 16", "result" => "loss"),
			array("week"=>"1", "date"=>"September 11", "team"=>"St. Louis Rams", "stadium"=>"Edward Jones Dome", "time"=>"1:00 PM", "tv"=>"FOX"),
			array("week"=>"2", "date"=>"September 18", "team"=>"Atlanta Falcons", "stadium"=>"Georgia Dome", "time"=>"8:20 PM", "tv"=>"NBC"),
			array("week"=>"3", "date"=>"September 25", "team"=>"New York Giants", "stadium"=>"Lincoln Financial Field", "time"=>"1:00 PM", "tv"=>"FOX"),
			array("week"=>"4", "date"=>"October 2", "team"=>"San Fransisco 49ers", "stadium"=>"Lincoln Financial Field", "time"=>"1:00 PM", "tv"=>"FOX"),
			array("week"=>"5", "date"=>"October 9", "team"=>"Buffalo Bills", "stadium"=>"Ralph Wilson Stadium", "time"=>"1:00 PM", "tv"=>"FOX"),
			array("week"=>"6", "date"=>"October 16", "team"=>"Washington Redskins", "stadium"=>"FedEx Field", "time"=>"1:00 PM", "tv"=>"FOX"),
			array("week"=>"7", "date"=>"October 23","team"=>"Bye","stadium"=>"","time"=>"","tv"=>""),
			array("week"=>"8", "date"=>"October 30", "team"=>"Dallas Cowboys", "stadium"=>"Lincoln Financial Field", "time"=>"8:20 PM", "tv"=>"NBC"),
			array("week"=>"9", "date"=>"November 7", "team"=>"Chicago Bears", "stadium"=>"Lincoln Financial Field", "time"=>"8:30 PM", "tv"=>"ESPN"),
			array("week"=>"10", "date"=>"November 13", "team"=>"Arizona Cardinals", "stadium"=>"Lincoln Financial Field", "time"=>"1:00 PM", "tv"=>"FOX"),
			array("week"=>"11", "date"=>"November 20", "team"=>"New York Giants", "stadium"=>"New Meadowlands Stadium", "time"=>"8:20 PM", "tv"=>"NBC"),
			array("week"=>"12", "date"=>"November 27", "team"=>"New England Patriots", "stadium"=>"Lincoln Financial Field", "time"=>"4:15 PM", "tv"=>"CBS"),
			array("week"=>"13", "date"=>"December 1", "team"=>"Seattle Seahawks", "stadium"=>"Qwest Field", "time"=>"8:20 PM", "tv"=>"NFL Network"),
			array("week"=>"14", "date"=>"December 11", "team"=>"Miami Dolphins", "stadium"=>"Sun Life Stadium", "time"=>"1:00 PM", "tv"=>"FOX"),
			array("week"=>"15", "date"=>"December 18", "team"=>"New York Jets", "stadium"=>"Lincoln Financial Field", "time"=>"4:15 PM", "tv"=>"CBS"),
			array("week"=>"16", "date"=>"December 24", "team"=>"Dallas Cowboys", "stadium"=>"Cowboys Stadium", "time"=>"4:15 PM", "tv"=>"FOX"),
			array("week"=>"17", "date"=>"January 1", "team"=>"Washington Redskins", "stadium"=>"Lincoln Financial Field", "time"=>"1:00 PM", "tv"=>"FOX")
		);
		
		$announcement = "";
		foreach ($schedule as $key => $row) {
			if (time() < strtotime($row['date'])) {
				if ($row['team'] == "Bye") {
					$announcement .= "<div class='next_game'>Week Off</div>";
				} else {
					$announcement .= "<div class='next_game'><div class='label'>Next Game: </div> <div class='date'>".$row['date'] . "</div> <div class='time'>" . $row['time'] . "</div> <div class='team'>" . $row['team'] . "</div> <div class='stadium'>" . $row['stadium']. "</div> <div class='tv'>" . $row['tv']."</div></div>";
				}
				break;
			} else {
				if ($row['team'] == "Bye") {
					$announcement = "<div class='last_game'>Week Off</div>";
				} else {
					$announcement = "<div class='last_game'><div class='label'>Last Game: </div> <div class='score ".$row['result']."'>".$row['score'] . "</div> <div class='date'>".$row['date'] . "</div> <div class='time'>" . $row['time'] . "</div> <div class='team'>" . $row['team'] . "</div> <div class='stadium'>" . $row['stadium']. "</div> <div class='tv'>" . $row['tv']."</div></div>";
				}
			}
		}
		
		
		$rmsg = $announcement;
		$rmsg .= "<br><br>";
		$rmsg .= '<button onclick="play(\'/plugins/eagles/eagles.mp3\');">Play</button>';
		$js = 'bareffect(function(){play("/plugins/eagles/eagles.mp3");});';

		Status::create()
			->data($rmsg)
			->user_id($user->id)
			->cssclass('eagles')
			->channel($channel)
			->js($js)
			->insert();

		return true;
	}
	
	function header($args)
	{
		echo <<< HEADER
<link href="/plugins/eagles/eagles.css" type="text/css" rel="stylesheet">
HEADER;
	}

}
?>