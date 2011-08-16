<?php

class Projects extends Plugin
{

	function commands($cmds){
		$cmds[1]['case'] = array('%^:(?P<project>\w+)\s+(?P<criteria>.+)$%i', array($this, '_case'), CMD_LAST);
		$cmds[1]['casenumber'] = array('%^:(?P<project>)(?P<criteria>\d+)$%i', array($this, '_case'), CMD_LAST);
		$cmds[1]['caselist'] = array('%^:(?P<project>\w+)$%i', array($this, '_caselist'), CMD_LAST);
		$cmds[1]['projectstats'] = array('%^/stats(?:\s+(?P<nickname>.+))?$%i', array($this, '_stats'), CMD_LAST);
		$cmds[1]['casenumbers'] = array('%(?<!\w)#(?P<casenumber>\d+)\b%', array($this, '_casenumber'), CMD_FORWARD);
		return $cmds;
	}

	function response_obj($obj, $roomtype, $criteria) {
	/*
		if($roomtype == 'oa') {

			$criteria = '__' . $criteria;

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
			$obj->useportal = array('oa' =>
				array(
					'id' => $criteria,
					'content' => $roomtype . ' Portal ' . date('M j, Y H:i:sa'),
					'classes' => 'resize',
				),
			);
		}
*/
		return $obj;
	}
	
	function _casenumber($msg, $params) {
		preg_match_all('%(?<!\w)#\d+\b%', $msg, $matches);
		foreach($matches[0] as $match) {
			$casenumber = trim($match, '#');
			$msg = str_replace($match, '<a target="_blank" href="http://projects.rockriverstar.com/node/' . $casenumber . '">#' . $casenumber . '</a>', $msg);
		}
		return $msg;
	}

	function pdb() {
		static $db = false;
		if(!$db) {
			//$db = new DB('mysql:host=localhost;dbname=oa', 'oa', 'anotherCrappyProjectsDot', 'projects');
			$db = new DB('mysql:host=sol.rockriverstar.com;dbname=oa', 'oa', 'anotherCrappyProjectsDot', 'projects');
		}
		return $db;
	}

	function odb() {
		static $db = false;
		if(!$db) {
			$db = new DB('mysql:host=sol.rockriverstar.com;dbname=projects_live', 'oa', 'anotherCrappyProjectsDot', 'projects');
		}
		return $db;
	}
	
	function task_filter($task_ary) {
		$sql = "
			select
				u.name,
				n.nid,
				n.created,
				n.title,
				r.body,
				ccs.case_state_name as status,
				ccs2.case_state_name as priority,
				ccs3.case_state_name as type,
				p.id as project_nid,
				np.title as project,
				p.value as purl
			from
				purl p,
				og_ancestry oa,
				node n,
				node_revisions r,
				users u,
				casetracker_case cc,
				casetracker_case cc2,
				casetracker_case cc3,
				casetracker_case_states ccs,
				casetracker_case_states ccs2,
				casetracker_case_states ccs3,
				node np
			where
				p.id = oa.group_nid
				and oa.nid = n.nid
				and n.vid = r.vid
				and n.type = 'casetracker_basic_case'
				and u.uid = n.uid
				and cc.vid = n.vid
				and cc.case_status_id = ccs.csid
				and cc2.vid = n.vid
				and cc2.case_priority_id = ccs2.csid
				and cc3.vid = n.vid
				and cc3.case_type_id = ccs3.csid
				and ccs.case_state_name <> 'Closed'
				and ccs.case_state_name <> 'Duplicate'
				and p.id = np.nid
				and n.nid = :nid
		";

		if(!$this->pdb()) {
			Status::create()
				->data('Could not connect to the projects database.')
				->type('system')
				->user_to($user->id)
				->cssclass('error')
				->channel($channel)
				->insert();
			return true;
		}

		extract($task_ary);
		if(preg_match_all('/((?P<action>fixes|closes)\s+)?(?<!\w)#(?P<casenumber>\d+)\b/sim', $task, $matches, PREG_SET_ORDER)) {
			foreach($matches as $match) {
				$case = $this->pdb()->row($sql, array('nid' => $match['casenumber']));
				$task = str_replace($match[0], $match[0] . '(' . $case->title . ')', $task);
				$notes .= 'Found at http://projects.rockriverstar.com/node/' . $case->nid . "\n";
				
				switch($match['action']) {
					case 'fixes':
						$notes .= "Marked node as fixed, and reassigned to reporter.\n";
						break;
					case 'closes':
						$notes .= "Marked node as closed.\n";
						break;
				}
			}
		}
		return array('task' => $task, 'notes' => $notes);
	}

	function _case($params) {
		$project = $params['project'];
		$criteria = $params['criteria'];
		$user = $params['user'];
		$channel = $params['channel'];


		$sql = "
			select
				u.name,
				n.nid,
				n.created,
				n.title,
				r.body,
				ccs.case_state_name as status,
				ccs2.case_state_name as priority,
				ccs3.case_state_name as type,
				p.id as project_nid,
				np.title as project,
				p.value as purl
			from
				purl p,
				og_ancestry oa,
				node n,
				node_revisions r,
				users u,
				casetracker_case cc,
				casetracker_case cc2,
				casetracker_case cc3,
				casetracker_case_states ccs,
				casetracker_case_states ccs2,
				casetracker_case_states ccs3,
				node np
			where
				p.id = oa.group_nid
				and oa.nid = n.nid
				and n.vid = r.vid
				and n.type = 'casetracker_basic_case'
				and u.uid = n.uid
				and cc.vid = n.vid
				and cc.case_status_id = ccs.csid
				and cc2.vid = n.vid
				and cc2.case_priority_id = ccs2.csid
				and cc3.vid = n.vid
				and cc3.case_type_id = ccs3.csid
				and ccs.case_state_name <> 'Closed'
				and ccs.case_state_name <> 'Duplicate'
				and p.id = np.nid
		";

		if(!$this->pdb()) {
			Status::create()
				->data('Could not connect to the projects database.')
				->type('system')
				->user_to($user->id)
				->cssclass('error')
				->channel($channel)
				->insert();
			return true;
		}

		if(is_numeric($criteria)) {
			$cases = $this->pdb()->results($sql . "and n.nid = :nid;", array('nid' => $criteria));
		}
		else {
			$cases = $this->pdb()->results($sql . "and p.value = :project order by ccs2.weight asc, ccs.weight asc, n.title asc;", array('project' => $project));
			$crit = explode(' ', strtolower($criteria));
			foreach($cases as $k => $case) {
				$target = explode(' ', strtolower($case->title . ' ' . $case->body));
				$intersect = array_intersect($crit, $target);
				if(count($intersect) != count($crit)) {
					unset($cases[$k]);
				}
			}
		}

		if(count($cases) == 0) {
			Status::create()
				->data('No cases match that criteria.')
				->type('system')
				->user_to($user->id)
				->cssclass('error')
				->channel($channel)
				->insert();
		}
		elseif(count($cases) == 1) {
			$case = reset($cases);
			$msg = Utils::cmdout($params);
			$msg .= '<table><tr><td colspan="3" class="casetitle"><span class="caseproject">In <a href="http://projects.rockriverstar.com/' . $case->purl . '" target="_blank">' . $case->project . '</a>:</span> <b><a href="http://projects.rockriverstar.com/node/' . $case->nid . '" target="_blank">' . htmlspecialchars($case->title) . '</a></b></td></tr>';
			$msg .= '<tr><td><b>Type:</b> ' . $case->type . '</td><td><b>Status:</b> ' . $case->status . '</td><td><b>Priority:</b> ' . $case->priority . '</td></tr>';
			$msg .= '<tr><td colspan="3"><div class="casesummary">' . nl2br(htmlspecialchars(trim($case->body)));
			$comments = $this->pdb()->results('SELECT * FROM comments WHERE nid = :nid ORDER BY timestamp ASC', array('nid'=>$case->nid));
			foreach((array)$comments as $comment) {
				$msg .= '<div class="casecomment"><div class="casecommentmeta"><div class="commenter" style="vertical-align:top">' . htmlspecialchars($comment->name) . '</div><div class="timestamp">' . date('Y-m-d H:i', $comment->timestamp ). '</div></div><div class="commentsummary">' . nl2br(htmlspecialchars(trim($comment->comment))) . '</div></div>';
			}
			$msg .= '</div></td></tr>';
			$msg .= '</table>';
			Status::create()
				->data($msg)
				->user_id($user->id)
				->cssclass('project')
				->channel($channel)
				->insert();
		}
		else {
			$msg = Utils::cmdout($params);
			$msg .= '<b>' . count($cases). ' Results in <a href="http://projects.rockriverstar.com/' . reset($cases)->purl . '" target="_blank">' . reset($cases)->project . '</a></b><table>';
			foreach($cases as $case) {
				$msg .= '<tr><td class="casestatus">' . $case->type . '</td><td class="casestatus">' . $case->status . '</td><td class="casestatus">' . $case->priority . '</td><td><a href="http://projects.rockriverstar.com/node/' . $case->nid . '" target="_blank" onclick="send(\':' . $case->purl . ' ' . $case->nid .'\');return false;">' . htmlspecialchars($case->title) . '</a></td></tr>';
			}
			$msg .= '</table>';
			Status::create()
				->data($msg)
				->user_id($user->id)
				->cssclass('project')
				->channel($channel)
				->insert();
		}
		return true;

	}

	function _caselist($params) {
		$project = $params['project'];
		$user = $params['user'];
		$channel = $params['channel'];

		$sql = "
			select
				u.name,
				n.nid,
				n.created,
				n.title,
				r.body,
				ccs.case_state_name as status,
				ccs2.case_state_name as priority,
				ccs3.case_state_name as type,
				p.id as project_nid,
				np.title as project,
				p.value as purl
			from
				purl p,
				og_ancestry oa,
				node n,
				node_revisions r,
				users u,
				casetracker_case cc,
				casetracker_case cc2,
				casetracker_case cc3,
				casetracker_case_states ccs,
				casetracker_case_states ccs2,
				casetracker_case_states ccs3,
				node np
			where
				p.id = oa.group_nid
				and oa.nid = n.nid
				and n.vid = r.vid
				and n.type = 'casetracker_basic_case'
				and u.uid = n.uid
				and cc.vid = n.vid
				and cc.case_status_id = ccs.csid
				and cc2.vid = n.vid
				and cc2.case_priority_id = ccs2.csid
				and cc3.vid = n.vid
				and cc3.case_type_id = ccs3.csid
				and ccs.case_state_name <> 'Closed'
				and ccs.case_state_name <> 'Duplicate'
				and p.id = np.nid
				and p.value = :project
			order by 
			 ccs2.weight asc, ccs.weight asc, n.title asc;
		";

		if(!$this->pdb()) {
			Status::create()
				->data('Could not connect to the projects database.')
				->type('system')
				->user_to($user->id)
				->cssclass('error')
				->channel($channel)
				->insert();
			return true;
		}

		$cases = $this->pdb()->results($sql, array('project' => $project));

		if(count($cases) == 0) {
			Status::create()
				->data('No cases match that project.')
				->type('system')
				->user_to($user->id)
				->cssclass('error')
				->channel($channel)
				->insert();
		}
		elseif(count($cases) == 1) {
			$case = reset($cases);
			$msg = Utils::cmdout($params);
			$msg .= '<table><tr><td colspan="3" class="casetitle"><span class="caseproject">In <a href="http://projects.rockriverstar.com/' . $case->purl . '" target="_blank">' . $case->project . '</a>:</span> <b><a href="http://projects.rockriverstar.com/node/' . $case->nid . '" target="_blank">' . htmlspecialchars($case->title) . '</a></b></td></tr>';
			$msg .= '<tr><td><b>Type:</b> ' . $case->type . '</td><td><b>Status:</b> ' . $case->status . '</td><td><b>Priority:</b> ' . $case->priority . '</td></tr>';
			$msg .= '<tr><td colspan="3"><div class="casesummary">' . nl2br(htmlspecialchars($case->body)) . '</div></td></tr></table>';
			Status::create()
				->data($msg)
				->user_id($user->id)
				->cssclass('project')
				->channel($channel)
				->insert();
		}
		else {
			$msg = Utils::cmdout($params);
			$msg .= '<b>' . count($cases). ' Results in <a href="http://projects.rockriverstar.com/' . reset($cases)->purl . '" target="_blank">' . reset($cases)->project . '</a></b><table>';
			$count = 0;
			foreach($cases as $case) {
				$msg .= '<tr><td class="casestatus">' . $case->type . '</td><td class="casestatus">' . $case->status . '</td><td class="casestatus">' . $case->priority . '</td><td><a href="http://projects.rockriverstar.com/node/' . $case->nid . '" target="_blank" onclick="send(\':' . $case->purl . ' ' . $case->nid .'\');return false;">' . htmlspecialchars($case->title) . '</a></td></tr>';
				$count++;
				if($count > 10) {
					$msg .= '<tr><td colspan="4"><a href="http://projects.rockriverstar.com/' . $case->purl . '/casetracker" target="_blank">And ' . (count($cases) - $count) . ' more cases...</a></td></tr>';
					break;
				}
			}
			$msg .= '</table>';
			Status::create()
				->data($msg)
				//->type('system')
				->user_id($user->id)
				//->user_to($user->id)
				->cssclass('project')
				->channel($channel)
				->insert();
		}
		return true;

	}

	function _stats($params) {
		$nickname = $params['nickname'];
		$user = $params['user'];
		$channel = $params['channel'];

		$statsql = "
			select
				np.title as project,
				ccs.csid as csid,
				ccs.case_state_name as status,
				count(*) as case_count,
				p.value as purl
			from
				purl p,
				og_ancestry oa,
				node n,
				node_revisions r,
				users u,
				casetracker_case cc,
				casetracker_case cc2,
				casetracker_case cc3,
				casetracker_case_states ccs,
				casetracker_case_states ccs2,
				casetracker_case_states ccs3,
				node np
			where
				p.id = oa.group_nid
				and oa.nid = n.nid
				and n.vid = r.vid
				and n.type = 'casetracker_basic_case'
				and u.uid = n.uid
				and cc.vid = n.vid
				and cc.case_status_id = ccs.csid
				and cc2.vid = n.vid
				and cc2.case_priority_id = ccs2.csid
				and cc3.vid = n.vid
				and cc3.case_type_id = ccs3.csid
				and ccs.case_state_name <> 'Closed'
				and ccs.case_state_name <> 'Duplicate'
				and p.id = np.nid
		";

		$uid = false;
		if($nickname == '*') {
			$statsql .= '
				group by
					project, status
				order by
					project, status;';
			$uid = -1;
			$sqlparams = array();
		}
		elseif($nickname != '') {
			$msg = 'The user "' . htmlspecialchars($params['nickname']) . '" does not exist.';
			$target = PresenceController::_userstr($nickname);
			if($target) {
				$msg = 'The user "' . htmlspecialchars($params['nickname']) . '" does not have a PD account associated in the options table.';
				$sql = "SELECT value FROM options WHERE grouping = 'identity' AND name = 'pduid' AND user_id = :user_id;";
				$uid = DB::get()->val($sql, array('user_id'=>$target->id));
			}
			$statsql .= '
				and cc.assign_to = :uid
				group by
					project, status
				order by
					project, status;';
			$sqlparams = array('uid' => $uid);
		}
		else {
			$msg = 'You do not have a PD account associated in the options table.';
			$uid = Option::get('identity', 'pduid');
			$statsql .= '
				and cc.assign_to = :uid
				group by
					project, status
				order by
					project, status;';
			$sqlparams = array('uid' => $uid);
		}

		if(!$uid || $uid == 0) {
			Status::create()
				->data(Utils::cmdout($params).$msg)
				->type('system')
				->cssclass('error')
				->channel($channel)
				->insert();
			return true;
		}

		if(!$this->pdb()) {
			Status::create()
				->data('Could not connect to the projects database.')
				->type('system')
				->user_to($user->id)
				->cssclass('error')
				->channel($channel)
				->insert();
			return true;
		}

		$stats = $this->pdb()->results($statsql, $sqlparams);

		if(count($stats) == 0) {
			$msg = Utils::cmdout($params);
			Status::create()
				->data($msg . 'No open cases found.')
				->type('system')
				->cssclass('stats')
				->channel($channel)
				->insert();
		}
		else {
			$msg = Utils::cmdout($params);
			foreach($stats as $stat) {
				$statuses[$stat->csid] = $stat->status;
				$projects[$stat->purl] = $stat->project;
				$count[$stat->project][$stat->status] = $stat->case_count;
			}
			$msg .= '<table><thead><tr><th>Project Name</th>';
			foreach($statuses as $status) {
				$msg .= '<th>' . $status . '</th>';
			}
			$msg .= '</tr></thead>';
			foreach($projects as $purl => $project) {
				$msg .= '<tr><th><a href="http://projects.rockriverstar.com/' . $purl . '/casetracker" target="_blank" onclick="send(\':' . $purl . '\');return false;">'.$project.'</a></th>';
				foreach($statuses as $csid => $status) {
					if(isset($count[$project][$status])) {
						$msg .= '<td><a href="http://projects.rockriverstar.com/' . $purl . '/casetracker/filter?keys=';
						if($uid > 0) {
							$msg .= '&assign_to[]=' . $uid;
						}
						$msg .= '&pid=All&case_priority_id=All&case_status_id=' . $csid . '" target="_blank">' . $count[$project][$status] . '</a></td>';
					}
					else {
						$msg .= '<td class="no_cases">&#151;</td>';
					}
				}
				$msg .= '</tr>';
			}
			$msg .= '</table>';
			Status::create()
				->data($msg)
				->user_id($user->id)
				->cssclass('stats')
				->channel($channel)
				->insert();
		}
		return true;

	}

	function autocomplete($auto, $cmd){
		if($cmd[0] == ':') {
			$projects = $this->pdb()->col('SELECT value FROM purl');
			foreach($projects as $project) {
				$auto[] = ':' . $project . " \tcriteria";
			}
		}
		$auto[] = "/stats \t{$nickname}";
		return $auto;
	}

}


?>
