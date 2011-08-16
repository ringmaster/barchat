<?php

Class QAPlugin extends Plugin
{
	
	function commands($cmds){
		$cmds[1]['question'] = array('%^Q:\s*(?P<question>.+?\?)(?P<answers>\s*.+?)?\??\s*$%i', array($this, '_question'), CMD_LAST);
		$cmds[1]['closequestion'] = array('%^/closequestion\s+(?P<qid>\d+)\s*$%i', array($this, '_closequestion'), CMD_LAST);
		$cmds[1]['noanswer'] = array('%^/noanswer\s+(?P<qid>\d+)\s*$%i', array($this, '_noanswer'), CMD_LAST);
		$cmds[1]['answer'] = array('%^A\[(?P<qid>\d+)\]:\s*(?P<answer>.+)$%i', array($this, '_answer'), CMD_LAST);
		return $cmds;
	}
	
	function _question($params){
		$user = $params['user'];
		$channel = $params['channel'];
		
		$question = $params['question'];
		$answers = $params['answers'];
		if($answers != '') {
			$answers = preg_split('%\s*(,\s*or\b|,|\bor\b)\s*%i', $answers);
		}

		$rmsg = htmlspecialchars($question);
		$js = '';

		DB::get()->query("INSERT INTO presence (data, type, user_id, channel, cssclass, js) VALUES (:msg, 'message', :user_id, :channel, 'question', :js)", array('msg' => $rmsg, 'user_id' => $user->id, 'channel' => $channel, 'js' => $js));
		
		$message = '<b>' . $user->nickname . ' asked:</b> ' . $rmsg . ' ' . implode(', ', $answers);
		$message .= ' <button onclick="send(\'/closequestion {$drawer_id}\');">Close Question</button>';
		
		DB::get()->query(
		"INSERT INTO drawers (user_id, channel, message, js) VALUES (:user_id, :channel, :message, :js);",
			array(
				'user_id' => $user->id,
				'channel' => $channel,
				'message' => $message,
				'js' => $js,
			)
		);
		$qid = DB::get()->lastInsertId();

		$users = DB::get()->col('SELECT user_id FROM channels WHERE name = :channel', array('channel' => $channel));
		if(is_array($answers)) {
			$message = '<b>' . $user->nickname . ' asks:</b> ' . $rmsg . ' ';
			foreach($answers as $answer) {
				$message .= ' <button onclick="send(\'A[' . $qid . ']: ' . htmlspecialchars($answer) . '\');">' . htmlspecialchars($answer) . '</button>';
			}
			$js = '';
		}
		else {
			$message = '<b>' . $user->nickname . ' asks:</b> ' . $rmsg . ' <button class="answer">Answer Now</button> <button class="sorry">Sorry, no idea.</button>';
			$range = 4 + strlen($qid);
			$js = '
$(drawer).find(".answer").click(function(){$("#commandline").val("A[' . $qid . ']:").selectRange(' . $range . ', ' . $range . ');});
$(drawer).find(".sorry").click(function(){send("/noanswer ' . $qid . '")});
';
		}
		foreach($users as $uid) {
			if($uid == $user->id) continue;
			DB::get()->query(
			"INSERT INTO drawers (user_id, channel, message, js, indexed) VALUES (:user_id, :channel, :message, :js, :qid);",
			array(
					'user_id' => $uid,
					'channel' => $channel,
					'message' => $message,
					'js' => $js,
					'qid' => 'q' . $qid,
				)
			);
		}
				
		return true;
	}
	
	function _noanswer($params) {
		$user = $params['user'];
		$channel = $params['channel'];
		$qid = $params['qid'];

		DB::get()->query('DELETE FROM drawers WHERE indexed = :qid AND user_id = :user_id', array('qid' => 'q' . $qid, 'user_id' => $user->id));

		$q = DB::get()->row('SELECT * FROM drawers WHERE id = :qid', array('qid' => $qid));
		$user_to = DB::get()->row("SELECT * FROM users WHERE id = :user_id", array('user_id' => $q->user_id));
		
		Status::create()
			->data('<div class="slash">Answer from <em>' . htmlspecialchars($user->nickname) . '</em></div>Sorry, no idea.')
			->cssclass('answer')
			->user_id($user->id)
			->channel($channel)
			->insert();
		
		return true;
	}
	
	function _answer($params) {
		$user = $params['user'];
		$channel = $params['channel'];
		$qid = $params['qid'];
		$answer = $params['answer'];

		DB::get()->query('DELETE FROM drawers WHERE indexed = :qid AND user_id = :user_id', array('qid' => 'q' . $qid, 'user_id' => $user->id));

		$q = DB::get()->row('SELECT * FROM drawers WHERE id = :qid', array('qid' => $qid));
		$user_to = DB::get()->row("SELECT * FROM users WHERE id = :user_id", array('user_id' => $q->user_id));

		$message = '<div class="slash">Answer from <em>' . htmlspecialchars($user->nickname) . '</em></div>' . htmlspecialchars($answer);

		DB::get()->query(
		"UPDATE drawers SET message = CONCAT(message, :message) WHERE id = :qid;",
		array(
				'message' => '<div class="singleanswer">' . $message . '</div>',
				'qid' => $qid,
			)
		);

		Status::create()
			->data($message)
			->cssclass('answer')
			->user_id($user->id)
			->channel($channel)
			->js('bareffect(function(){refreshDrawers()});')
			->insert();
	
		return true;
	}

	function _closequestion($params) {
		$user = $params['user'];
		$channel = $params['channel'];
		$qid = $params['qid'];
		$answer = $params['answer'];

		$users = DB::get()->col('SELECT user_id FROM drawers WHERE indexed = :qid', array('qid' => 'q' . $qid));
		DB::get()->query('DELETE FROM drawers WHERE indexed = :qid', array('qid' => 'q' . $qid));
		
		$q = DB::get()->row('SELECT * FROM drawers d inner join users u on d.user_id = u.id WHERE d.id = :qid', array('qid' => $qid));
		DB::get()->query('DELETE FROM drawers WHERE id = :qid', array('qid' => $qid));
		
		if(strpos($q->message, 'class="singleanswer"') === false) {
			$users[] = $user->id;
			foreach($users as $user_id) {
				Status::create()
					->data(htmlspecialchars($q->username) . ' has withdrawn the question.')
					->cssclass('answers')
					->user_id($user->id)
					->user_to($user_id)
					->channel($channel)
					->js('bareffect(function(){refreshDrawers()});')
					->insert();
			}
		}
		else {
			$message = $q->message;
			$message = preg_replace('%<button.+?</button>%i', '', $message);
			
			Status::create()
				->data($message)
				->cssclass('answers')
				->user_id($user->id)
				->channel($channel)
				->js('bareffect(function(){refreshDrawers()});')
				->insert();
		}
	
		return true;
	}
	
	function header($args)
	{
		echo <<< HEADER
<style type="text/css">#notices .question .content {background-image: url(/plugins/qa/q.png);background-repeat: no-repeat;padding-left: 40px;}
.singleanswer .slash { font-size: smaller; margin-left: -10px; }
.singleanswer { margin-left: 20px; }</style>
HEADER;
	}
	

}

?>