<?php

class QueuePlugin extends Plugin {
		
	function commands($cmds){
		$cmds[1]['queues'] = array('%^/queues?$%i', array($this, '_queues'), CMD_LAST);
		$cmds[1]['qchat'] = array('%^/qchat\s+(?P<name>.+?)(?:\s+(?P<reason>.+))?$%i', array($this, '_qchat'), CMD_LAST);
		$cmds[1]['cchat'] = array('%^/cchat\s+(?P<name>.+?)?$%i', array($this, '_cchat'), CMD_LAST);
		$cmds[1]['qcall'] = array('%^/qcall\s+(?P<name>.+?)(?:\s+(?P<reason>.+))?$%i', array($this, '_qcall'), CMD_LAST);
		$cmds[1]['ccall'] = array('%^/ccall\s+(?P<name>.+?)?$%i', array($this, '_ccall'), CMD_LAST);
		return $cmds;
	}
	
	function _queues($params) {
		$user = $params['user'];

		$drawers = DB::get()->results("SELECT user_id, username, indexed, added FROM drawers inner join users on users.id = drawers.user_id WHERE indexed LIKE 'queue_%' order by user_id asc, added asc;");
		$out = '';
		foreach($drawers as $drawer) {
			if(preg_match('#_([^_]+)_(\d+)$#', $drawer->indexed, $matches)) {
				if($matches[2] == $user->id) {
					$out .= "<li>" .'<a href="#" onclick="send(\'/c' . $matches[1] . ' ' . $drawer->username . '\');return false;" style="margin-right:5px;"><img src="';
					switch($matches[1]) {
						case 'chat':
							$out .= '/plugins/queue/user_comment_delete.png';
							break;
						default:
							$out .= '/plugins/queue/phone_delete.png';
							break;
					}
					$out .= '"></a>'. "{$matches[1]} queued with {$drawer->username} <small>" . date('M j, Y h:ia', strtotime($drawer->added)) . "</small></li>";
				}
			}
		}
		if($out == '') {
			$out = 'You have no active queues.';
		}
		else {
			$out = '<ul>' . $out . '</ul>';
		}
		$out = Utils::cmdout($params) . $out;

		Status::create()
			->data($out)
			->type('system')
			->user_to($user->id)
			->cssclass('ok')
			->insert();
		
		return true;
	}

	function _qchat($params) {
		$user = $params['user'];
		$channel = $params['channel'];
		$target = $params['name'];
		$reason = $params['reason'];
		$user_to = $params['presence']->_userstr($target);
		
		if(!$user_to) {
			Status::create()
				->data('That is not a valid username.')
				->type('system')
				->user_to($user->id)
				->cssclass('error')
				->insert();
		}
		elseif(strlen($reason) > 50) {
			Status::create()
				->data('Your reason for the chat is too long.  Use less than 50 characters.')
				->type('system')
				->user_to($user->id)
				->cssclass('error')
				->insert();
		}
		else {

			Status::create()
				->data('Queued chat with ' . $user_to->username)
				->type('system')
				->user_to($user->id)
				->cssclass('ok')
				->insert();
			Status::create()
				->data($user->username . ' has requested a chat.')
				->type('system')
				->user_to($user_to->id)
				->cssclass('ok')
				->js('queuer = function () {q = $(\'.queue\'); if(q.length > 0) {window.setTimeout(queuer, 60000);play(\'buzz\');}}
queuer();')
				->insert();

			$reason2 = trim($reason) != '' ? " about {$reason}" : '';
			$msg = '<a title="Chat with ' . $user->username . ' now" href="#" style="margin-right: 5px;" onclick="send(\'/msg ' . $user->username . ' I am now free to chat' . htmlspecialchars($reason2) . '\');return closedrawer({$drawer_id});"><img src="/plugins/queue/user_comment.png"></a>';
			$msg .= '<a title="Reject ' . $user->username . '\'s chat request" href="#" style="margin-right: 5px;" onclick="send(\'/msg ' . $user->username . ' Chat queue ' . htmlspecialchars($reason2) . ' rejected.\');return closedrawer({$drawer_id});"><img src="/plugins/queue/user_comment_delete.png"></a>';
			$msg .= '<small>' . date('h:ia') . '</small> Chat request from <em>' . $user->username . '</em>';
			if(trim($reason) != '') {
				$msg .= ': <small>' . htmlspecialchars($reason) . '</small>';
			}

			DB::get()->query("DELETE FROM drawers WHERE user_id = :user_id and indexed = :indexed;", array('user_id' => $user_to->id, 'indexed' => 'queue_chat_' . $user->id));
						
			DB::get()->query("INSERT INTO drawers (user_id, message, indexed, cssclass) VALUES (:user_id, :msg, :indexed, 'queue');", array('user_id' => $user_to->id, 'msg' => $msg, 'indexed' => 'queue_chat_' . $user->id));
			
		}
		return true;
	}

	function _cchat($params) {
		$user = $params['user'];
		$channel = $params['channel'];
		$target = $params['name'];
		$user_to = $params['presence']->_userstr($target);
		
		if(!$user_to) {
			Status::create()
				->data('That is not a valid username.')
				->type('system')
				->user_to($user->id)
				->cssclass('error')
				->insert();
		}
		else {

			$drawer = DB::get()->val("SELECT id FROM drawers WHERE user_id = :user_id and indexed = :indexed;", array('user_id' => $user_to->id, 'indexed' => 'queue_chat_' . $user->id));
			
			if($drawer) {

				Status::create()
					->data('Canceled queued chat with ' . $user_to->username)
					->type('system')
					->user_to($user->id)
					->cssclass('ok')
					->insert();
	
				Status::create()
					->data($user->username . ' has canceled their queued chat request.')
					->type('system')
					->user_to($user_to->id)
					->cssclass('ok')
					->insert();
			}
			else {
				Status::create()
					->data('You have no chat queued with ' . $user_to->username)
					->type('system')
					->user_to($user->id)
					->cssclass('error')
					->insert();
			}

			DB::get()->query("DELETE FROM drawers WHERE user_id = :user_id and indexed = :indexed;", array('user_id' => $user_to->id, 'indexed' => 'queue_chat_' . $user->id));
		}
		return true;
	}

	function _qcall($params) {
		$user = $params['user'];
		$channel = $params['channel'];
		$target = $params['name'];
		$reason = $params['reason'];
		$user_to = $params['presence']->_userstr($target);
		
		if(!$user_to) {
			Status::create()
				->data('That is not a valid username.')
				->type('system')
				->user_to($user->id)
				->cssclass('error')
				->insert();
		}
		elseif(strlen($reason) > 50) {
			Status::create()
				->data('Your reason for the call is too long.  Use less than 50 characters.')
				->type('system')
				->user_to($user->id)
				->cssclass('error')
				->insert();
		}
		else {

			Status::create()
				->data('Queued call to ' . $user_to->username)
				->type('system')
				->user_to($user->id)
				->cssclass('ok')
				->insert();
			Status::create()
				->data($user->username . ' has requested a call.')
				->type('system')
				->user_to($user_to->id)
				->cssclass('ok')
				->js('queuer = function () {q = $(\'.queue\'); if(q.length > 0) {window.setTimeout(queuer, 60000);play(\'buzz\');}}
queuer();')
				->insert();

			$msg = '<a title="Call ' . $user->username . ' now" href="#" style="margin-right: 5px;" onclick="send(\'/call ' . $user->username . '\');return closedrawer({$drawer_id});"><img src="/plugins/queue/phone.png"></a>';
			$reason2 = trim($reason) != '' ? "({$reason})" : '';
			$msg .= '<a title="Reject ' . $user->username . '\'s call request" href="#" style="margin-right: 5px;" onclick="send(\'/msg ' . $user->username . ' Call queue ' . htmlspecialchars($reason2) . ' rejected.\');return closedrawer({$drawer_id});"><img src="/plugins/queue/phone_delete.png"></a>';
			$msg .= '<small>' . date('h:ia') . '</small> Call request from <em>' . $user->username . '</em>';
			if(trim($reason) != '') {
				$msg .= ': <small>' . htmlspecialchars($reason) . '</small>';
			}

			DB::get()->query("DELETE FROM drawers WHERE user_id = :user_id and indexed = :indexed;", array('user_id' => $user_to->id, 'indexed' => 'queue_call_' . $user->id));
						
			DB::get()->query("INSERT INTO drawers (user_id, message, indexed, cssclass) VALUES (:user_id, :msg, :indexed, 'queue');", array('user_id' => $user_to->id, 'msg' => $msg, 'indexed' => 'queue_call_' . $user->id));
			
		}
		return true;
	}

	function _ccall($params) {
		$user = $params['user'];
		$channel = $params['channel'];
		$target = $params['name'];
		$user_to = $params['presence']->_userstr($target);
		
		if(!$user_to) {
			Status::create()
				->data('That is not a valid username.')
				->type('system')
				->user_to($user->id)
				->cssclass('error')
				->insert();
		}
		else {

			$drawer = DB::get()->val("SELECT id FROM drawers WHERE user_id = :user_id and indexed = :indexed;", array('user_id' => $user_to->id, 'indexed' => 'queue_call_' . $user->id));
			
			if($drawer) {

				Status::create()
					->data('Canceled queued call to ' . $user_to->username)
					->type('system')
					->user_to($user->id)
					->cssclass('ok')
					->insert();
	
				Status::create()
					->data($user->username . ' has canceled their queued call request.')
					->type('system')
					->user_to($user_to->id)
					->cssclass('ok')
					->insert();
			}
			else {
				Status::create()
					->data('You have no call queued to ' . $user_to->username)
					->type('system')
					->user_to($user->id)
					->cssclass('error')
					->insert();
			}

			DB::get()->query("DELETE FROM drawers WHERE user_id = :user_id and indexed = :indexed;", array('user_id' => $user_to->id, 'indexed' => 'queue_call_' . $user->id));
		}
		return true;
	}

	function autocomplete($auto, $cmd){
		$auto[] = "/qcall {\$nickname} \treason";
		$auto[] = "/ccall {\$nickname}";
		$auto[] = "/qchat {\$nickname} \treason";
		$auto[] = "/cchat {\$nickname}";
		$auto[] = "/queues";
		return $auto;
	}
	
}

?>