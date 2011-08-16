<?php
class DrupalPlugin extends Plugin
{
	
	function commands($cmds){
		$cmds[1]['module'] = array('%^/(module)\s+(?P<name>.+)$%i', array($this, '_module'), CMD_LAST);
		$cmds[1]['api'] = array('%^/(api)\s+(?P<name>.+)$%i', array($this, '_api'), CMD_LAST);
		return $cmds;
	}
	
	function _module($params){
		$user = $params['user'];
		$name = $params['name'];
		$channel = $params['channel'];

		$output = Utils::cmdout($params);

		$thtml = file_get_contents('http://drupal.org/search/apachesolr_multisitesearch/' . urlencode($name) . '?filters=ss_meta_type:module');
		
		$content = SimpleHTML::str_get_html($thtml);
		$dts = $content->find('dt[class=title]');
		$modules = '';
		$count = 0;
		foreach($dts as $dt) {
			$a = $dt->find('a',0);
			$modules .= '<li><a href="'.$a->href.'" target="_blank">' . $a->innertext . '</a></li>';
			if(++$count > 9) break;
		}
		if($modules != '') {
			$output .= '<ul style="margin-left:30px;">' . $modules . '</ul>';
		}
		else {
			$output .= '<p>No results.</p>';
		}
		Status::create()
			->data($output)
			->user_id($user->id)
			->cssclass('drupal module')
			->channel($channel)
			->insert();
	
		return true;	
	}

	
	function _api($params){
		$user = $params['user'];
		$name = $params['name'];
		$channel = $params['channel'];

		$output = Utils::cmdout($params);

		$searchurl = 'http://api.drupal.org/api/search/6/' . urlencode($name);
		$thtml = file_get_contents( $searchurl );
		
		$content = SimpleHTML::str_get_html($thtml);
		$tbody = $content->find('table[class=sticky-enabled]',0);
		if(!is_object($tbody)) {
			$code = $content->find('.active code', 0);
			$output .= '<code style="font-size: 1.5em;line-height: 2em"><a target="_blank" href="' . $searchurl . '">Reference</a>:' . $code->innertext . '</code>';
		}
		else {
			$trs = $tbody->find('tr');
			$modules = '';
			$count = 0;
			array_shift($trs);
			foreach($trs as $tr) {
				$tds = $tr->find('td');
				$a = $tds[0]->find('a',0);
				$modules .= '<li><a target="_blank" href="http://drupal.org/' .$a->href . '">' . $a->innertext . '</a>';
				//	$modules .= htmlspecialchars($tds[2]->innertext);
				$modules .= '</li>';
				if(++$count > 9) break;
			}
			if($modules != '') {
				$output .= '<ul style="margin-left:30px;">' . $modules . '</ul>';
			}
			else {
				$output .= '<p>No results.</p>';
			}
		}
		Status::create()
			->data($output)
			->user_id($user->id)
			->cssclass('drupal api')
			->channel($channel)
			->insert();
	
		return true;	
	}
		
}

?>