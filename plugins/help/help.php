<?php

class HelpPlugin extends Plugin {
	
	function commands($cmds){
		$cmds[1]['help'] = array('%^/help(?:\s+::(?P<helpid>\d+)(?:\s+(?P<helpsearch>.+))?)?$%i', array($this, '_help'), CMD_LAST);
		$cmds[1]['helpsearch'] = array('%^/help(?:\s+(?P<helpsearch>.+))?$%i', array($this, '_helpsearch'), CMD_LAST);
		return $cmds;
	}
	
	function _help($params) {
		$user = $params['user'];
		$channel = $params['channel'];
		$helpid = intval($params['helpid']);
		$helpsearch = $params['helpsearch'];

		$server = Option::get('projectsdot', 'server');
		$pduser = Option::get('projectsdot', 'user');
		$pdpass = Option::get('projectsdot', 'pass');
		$database = Option::get('projectsdot', 'database');
		$site = Option::get('projectsdot', 'site');
		
		$db = new DB("mysql:host={$server->value};dbname={$database->value}", $pduser->value, $pdpass->value, 'projectsdot');
		if($helpid == 0) {
			$helplinks = $db->results("select n.nid, n.title, b.weight from node n, node_revisions r, book b where n.vid = r.vid and b.vid = n.vid and n.type = 'book' and b.parent = 0 order by b.weight;");
			$title = 'Help Index';
			$body = 'Select a topic on the left to learn more.';
		}
		else {
			$helplinks = $db->results("select n.nid, n.title, b.weight from node n, node_revisions r, book b where n.vid = r.vid and b.vid = n.vid and n.type = 'book' and b.parent = :helpid order by b.weight;", array('helpid' => $helpid));
			$node = $db->row("select r.title, r.body, n.nid, b.parent from node n, node_revisions r, book b where n.vid = r.vid and b.vid = n.vid and n.type = 'book' and n.nid = :helpid;", array('helpid' => $helpid));
			$parentnode = $db->row("select n.nid, n.title, b.weight from node n, node_revisions r, book b where n.vid = r.vid and b.vid = n.vid and n.type = 'book' and n.nid = :helpid;", array('helpid' => $node->parent));

			if($parentnode) {
				$parentnode->title = '&uarr; ' . $parentnode->title;
			}
			else {
				$parentnode = new stdClass();
				$parentnode->title = '&uarr; Top';
				$parentnode->nid = 0;
				$parentnode->weight = -10;
			}
			array_unshift($helplinks, $parentnode);
			$body = nl2br($node->body);
			$title = $node->title . ' <a href="' . $site->value . '/node/' . $node->nid . '" target="_blank">&rarr;</a>';
		}
		
		$links = '';
		if($helpsearch != '') {
			$links .= '<li><a href="#' . $link->nid . '" onclick="send(\'/help ' . htmlspecialchars($helpsearch) . '\');return false;">&uarr; Search: ' . htmlspecialchars($helpsearch) . '</a></li>';
		}
		
		foreach($helplinks as $link) {
			$links .= '<li><a href="#' . $link->nid . '" onclick="send(\'/help ::' . $link->nid;
			if($helpsearch != '') {
				$links .= ' ' . htmlspecialchars($helpsearch);
			}
			$links .= '\');return false;">' . $link->title . '</a></li>';
		}
		
		$msg = '<a href="#" class="close" onclick="return closedrawer({$drawer_id});">close this drawer</a>
<div id="helplinks" style="width:30%;float:left;height:200px;overflow-y:auto;overflow-x:hidden;"><ul>' . $links . '</ul></div>
<div id="helpbody" style="width:70%;height:200px;overflow:auto;float:left;"><h3>' . $title . '</h3>' . $body . '</div>';
		DB::get()->query("DELETE FROM drawers WHERE indexed = 'help' and user_id = :user_id;", array('user_id' => $user->id));
		DB::get()->query("INSERT INTO drawers (user_id, message, indexed, cssclass) VALUES (:user_id, :msg, 'help', 'help');", array('user_id' => $user->id, 'msg' => $msg));

		$msg = 'Removed the "' . htmlspecialchars($name) . '" calendar.';
		$obj = new StdClass();
		$obj->laststatus = 0;
		$obj->js = "refreshDrawers();";
		echo json_encode($obj);
		die();

		return true;
	}

		
	function _helpsearch($params) {
		$user = $params['user'];
		$channel = $params['channel'];
		$helpsearch = $params['helpsearch'];

		$server = Option::get('projectsdot', 'server');
		$pduser = Option::get('projectsdot', 'user');
		$pdpass = Option::get('projectsdot', 'pass');
		$database = Option::get('projectsdot', 'database');
		$site = Option::get('projectsdot', 'site');
		
		$db = new DB("mysql:host={$server->value};dbname={$database->value}", $pduser->value, $pdpass->value, 'projectsdot');

		$helplinks = $db->results("select n.nid, n.title, b.weight from node n, node_revisions r, book b where n.vid = r.vid and b.vid = n.vid and n.type = 'book' and r.body like CONCAT('%', :crit, '%');", array('crit' => $helpsearch));
		$title = 'Help Search';
		$body = 'The book topics on the left contained the search phrase "' . htmlspecialchars($helpsearch) . '".';
		
		foreach($helplinks as $link) {
			$links .= '<li><a href="#' . $link->nid . '" onclick="send(\'/help ::' . $link->nid;
			if($helpsearch != '') {
				$links .= ' ' . htmlspecialchars($helpsearch);
			}
			$links .= '\');return false;">' . $link->title . '</a></li>';
		}
		
		$msg = '<a href="#" class="close" onclick="return closedrawer({$drawer_id});">close this drawer</a><div id="helplinks" style="width:30%;float:left;height:200px;overflow-y:auto;overflow-x:hidden;"><ul>' . $links . '</ul></div>
<div id="helpbody" style="width:70%;height:200px;overflow:auto;float:left;"><h3>' . $title . '</h3>' . $body . '</div>';
		DB::get()->query("DELETE FROM drawers WHERE indexed = 'help' and user_id = :user_id;", array('user_id' => $user->id));
		DB::get()->query("INSERT INTO drawers (user_id, message, indexed, cssclass) VALUES (:user_id, :msg, 'help', 'help');", array('user_id' => $user->id, 'msg' => $msg));

		$msg = 'Removed the "' . htmlspecialchars($name) . '" calendar.';
		$obj = new StdClass();
		$obj->laststatus = 0;
		$obj->js = "refreshDrawers();";
		echo json_encode($obj);
		die();

		return true;
	}
	
	function autocomplete($auto, $cmd){
		$auto[] = "/help";
		$auto[] = "/help \toptional topic";
		return $auto;
	}

}

?>