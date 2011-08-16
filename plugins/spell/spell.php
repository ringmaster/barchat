<?php
class SpellPlugin extends Plugin
{
	
	function commands($cmds){
		$cmds[1]['spell'] = array('%^/sp(ell)?$%i', array($this, '_spell'), CMD_LAST);
		return $cmds;
	}
	
	function _spell($params) {
		$user = $params['user'];
		$channel = $params['channel'];
		
		$presence = DB::get()->row('SELECT status, data FROM presence WHERE user_id = :user_id AND data <> "" ORDER BY msgtime DESC LIMIT 1', array('user_id' => $user->id));
		
		$data = $presence->data;
		$words = preg_split('%\W+%', $data);
		$words = array_unique($words);
		$words = array_combine($words, $words);

		$pspell_link = pspell_new("en");
		foreach($words as $word) {
			if(!pspell_check($pspell_link, $word)) {
				$suggestions = pspell_suggest($pspell_link, $word);
				if(count($suggestions) >0 ) {
					$presence->data = str_replace($word, reset($suggestions), $presence->data);
				}
				else {
					$presence->data = str_replace($word, '<s>' . $word . '</s>', $presence->data);
				}
			}
		}
		
		if($presence->data == $data) {
			Immediate::ok('No spelling corrections.', $user);
		}
		else {
		
	    include_once "Text/Diff.php";
	    include_once "Text/Diff/Renderer.php";
	    include_once "Text/Diff/Renderer/inline.php";
	
			$diff = &new Text_Diff(explode("\n", $data), explode("\n",htmlspecialchars_decode($presence->data)));
			$renderer = &new Text_Diff_Renderer_inline();
	  	$replacement = $renderer->render($diff);
			$replacement = addslashes($replacement);
	  	$replacement = str_replace("\n", '\n', $replacement);
	
			$js = <<< REPLJS
retcon({$presence->status}, '{$replacement}');
REPLJS;
	
			Status::create()
				->user_id($user->id)
				->js($js)
				->channel($channel)
				->cssclass('spell')
				->insert();
		}
		
		return true;
	}

	function autocomplete($auto, $cmd){
		$auto[] = "/spell";
		return $auto;
	}

}

?>