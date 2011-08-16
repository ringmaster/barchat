<?php

class KarmaPlugin extends Plugin {
		
	function commands($cmds){
		$cmds[2]['karma'] = array('/^(?P<word>.+?)\s*(?P<karma>(?:\+\+|\-\-|~~|\?\?))$/i', array($this, '_karma'), CMD_LAST);
		return $cmds;
	}
	
	function _karma($params){
		$user = $params['user'];
		$channel = $params['channel'];
		$matches = $params['matches'];

		$word = trim(strtolower($matches['word']), '"');
		$md5w = md5($word);
		$points = DB::get()->row('select sum(karma) as s, sum(abs(karma)) as b, sum(karma / abs(karma)) as v, std(karma) as d, var_pop(karma) as v from karma where word = ?', array($word));
	
		$lastvote = DB::get()->row('SELECT time_to_sec(timediff(now(),lastvote)) as t, karma FROM karma WHERE word = ? and user_id = ?', array($word, $user->id));
		if($lastvote && $lastvote->t < 30) {
			Immediate::create()
				->laststatus()
				->js("\$('#mainscroller .karma_{$md5w} .inner.active .voters').html('You are voting too fast!  Wait " . (30 - $lastvote->t) . " seconds.').effect('highlight', {}, 1500);");
			return true;
		}
		if($lastvote && abs($lastvote->karma) > $points->b - abs($lastvote->karma) && $points->v > 2) {
			Immediate::create()
				->laststatus()
				->js("\$('#mainscroller .karma_{$md5w} .inner.active .voters').html('You have voted more than everyone else <em>combined</em>.  You win!').effect('highlight', {}, 1500);");
			return true;
		}
				
		$kpoints = $points->s;
		
		if(DB::get()->val('SELECT count(word) FROM karma WHERE word = ? and user_id = ?', array($word, $user->id)) == 0) {
			switch($matches['karma']) {
				case '++':
					$kpoints++;
					$bmsg = 'Added one karma point to "' . htmlspecialchars($matches['word']) . '", totaling ' . $kpoints . ' points.';
					DB::get()->query('INSERT INTO karma (word, karma, user_id, direction) VALUES (?, ?, ?, 1)', array($word, 1, $user->id));
					break;
				case '--':
					$kpoints--;
					$bmsg = 'Subtracted one karma point from "' . htmlspecialchars($matches['word']) . '", totaling ' . $kpoints . ' points.';
					DB::get()->query('INSERT INTO karma (word, karma, user_id, direction) VALUES (?, ?, ?, -1)', array($word, -1, $user->id));
					break;
				case '~~':
					$kpoints--;
					$bmsg = 'You didn\'t vote on "' . htmlspecialchars($matches['word']) . '" to begin with.  The total is still ' . $kpoints . ' points.';
					DB::get()->query('INSERT INTO karma (word, karma, user_id, direction) VALUES (?, ?, ?, 0)', array($word, -1, $user->id));
					break;
				case '??':
					$bmsg = '"' . htmlspecialchars($matches['word']) . '" has ' . $kpoints . ' karma points -- you have not voted.';
					if($points->b == 0) {
						return false;
					}
					break;
			}
		}
		else {
			$oldkarma = DB::get()->val('SELECT karma FROM karma WHERE word = ? and user_id = ?', array($word, $user->id));
			switch($matches['karma']) {
				case '++':
					$kpoints++;
					$bmsg = 'Added one karma point to "' . htmlspecialchars($matches['word']) . '", totaling ' . $kpoints . ' points.';
					DB::get()->query('UPDATE karma SET karma = karma + 1, lastvote = now(), direction = 1  WHERE word = ? and user_id = ?', array($word, $user->id));
					break;
				case '--':
					$kpoints--;
					$bmsg = 'Subtracted one karma point from "' . htmlspecialchars($matches['word']) . '", totaling ' . $kpoints . ' points.';
					DB::get()->query('UPDATE karma SET karma = karma - 1, lastvote = now(), direction = -1 WHERE word = ? and user_id = ?', array($word, $user->id));
					break;
				case '~~':
					$kpoints -= $oldkarma;
					$bmsg = 'Removing your vote on "' . htmlspecialchars($matches['word']) . '", now totaling ' . $kpoints . ' points.';
					DB::get()->query('DELETE FROM karma WHERE word = ? and user_id = ?', array($word, $user->id));
					break;
				case '??':
					$bmsg = '"' . htmlspecialchars($matches['word']) . '" has ' . $kpoints . ' karma points -- you have already voted (' . $oldkarma . ').';
					break;
			}
		}
		
		$points = DB::get()->row('select sum(karma) as s, sum(karma / abs(karma)) as v, std(karma) as d, var_pop(karma) as v from karma where word = ?', array($word));

		$hword = htmlspecialchars($word);
		$bmsg = '<div class="word">' . $hword . '</div><button class="voteup" onclick="send(\'' . addslashes($hword) . '++\');">++</button><button class="votedown" onclick="send(\'' . addslashes($hword) . '--\');">--</button>';
		
		$bmsg = '<div class="inner">loading</div>';
		
		Status::create()
			->data($bmsg)
			->channel($channel)
			->type('notice')
			->cssclass('karma karma_' . md5($word))
			->js('apply_karma("' . $md5w . '", "' . $hword . '");')
			->insert();
			
		return true;
	}
	
	function blend_hex($from, $to, $pos = 0.5) 
	{ 
		// 1. Grab RGB from each colour 
		list($fr, $fg, $fb) = sscanf($from, '%2x%2x%2x'); 
		list($tr, $tg, $tb) = sscanf($to, '%2x%2x%2x'); 
	     
		// 2. Calculate colour based on frational position 
		$r = (int) ($fr - (($fr - $tr) * $pos)); 
		$g = (int) ($fg - (($fg - $tg) * $pos)); 
		$b = (int) ($fb - (($fb - $tb) * $pos)); 
	     
		// 3. Format to 6-char HEX colour string 
		return sprintf('%02x%02x%02x', $r, $g, $b); 
	}  
		
	function ajax_karma()
	{
		$word = trim(strtolower($_POST['word']));
		$points = DB::get()->row('select count(karma) as c, sum(abs(karma)) as b, max(karma) as mx, min(karma) as mn, avg(karma) as a, sum(karma) as s, sum(karma / abs(karma)) as v, std(karma) as d, var_pop(karma) as v from karma where word = ?', array($word));

		$hword = htmlspecialchars($word);
		$bmsg = '<div class="buttons"><button class="voteup" onclick="send(\'' . addslashes($hword) . '++\');">++</button><button class="votedown" onclick="send(\'' . addslashes($hword) . '--\');">--</button></div>';
		
		$tops = DB::get()->results("select sum(k.karma) as s, abs(sum(k.karma)) as b, k.word from karma k inner join karma k2 on k2.word = k.word and k2.lastvote > '" . date('Y-m-d', strtotime('-1 week')) . "' group by k.word order by b desc limit 5;");

		if($points->a > 0) {
			$to = '33ff00';
		}
		else {
			$to = 'ff3300';
		}
		$rgb_hex = $this->blend_hex('444444', $to, abs($points->a) / max(abs($points->mx), abs($points->mn)));
		
		$bmsg .= '<div class="word"><span style="color:#' . $rgb_hex . ';">' .$hword . '</span><small>' . intval($points->a) . ' average</small><small>' . $points->s . ' sum votes</small><small>' . $points->c . ' voting</small>';
		foreach($tops as $topword) {
			if($topword->word == $word) {
				$bmsg .= '<small><img src="/plugins/karma/star.png">Popular!</small>';
			}
		}
		$bmsg .= '</div>';
		
		$voters = DB::get()->results('select * from karma inner join users on karma.user_id = users.id where word = :word order by lastvote desc limit 5', array(':word' => $word));
		
		$voters = array_reverse($voters);
		
		$bmsg .= '<div class="voters">Recent voters:';
		$first = 'first';
		foreach($voters as $voter) {
			if(abs($voter->karma) > $points->b - abs($voter->karma)) {
				$first .= ' voteleader';
			}
			$bmsg .= ' <span class="voter ' . $first . '" title="'. $voter->karma . ' votes">';
			$bmsg .= $voter->username;
			if($voter->direction > 0) {
				$bmsg .= '<span class="upvote">++</span>';
			}
			elseif($voter->direction == 0) {
				$bmsg .= '<span class="novote">~~</span>';
			}
			else {
				$bmsg .= '<span class="downvote">--</span>';
			}
			$bmsg .= '</span>';
			$first = '';
		}
		$bmsg .= '</div>';
		
		$bmsg .= '<script type="text/javascript">$(function(){$(".karma_' . md5($word) . ' .word").effect("highlight", {}, 1500);});</script>';

	
		echo $bmsg;
	}
	
}

?>
