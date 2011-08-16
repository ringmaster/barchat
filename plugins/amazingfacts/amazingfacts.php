<?php

class AmazingfactsPlugin extends Plugin {
	
	function reload($user){
		
		$lastfacts = DB::get()->val("SELECT value FROM options WHERE name='facts' and grouping = 'misc'");
		if($lastfacts < mktime(0, 0, 0)) {
			DB::get()->query("SELECT * FROM drawers WHERE indexed = 'facts'");
			$users = DB::get()->col("SELECT id FROM users");
			
			$zip = new ZipArchive;
			if ($zip->open(dirname(__FILE__) . '/facts.zip') === TRUE) {
				$facts = explode("\n", $zip->getFromName('facts.txt'));
				$zip->close();
				$fact = '<div class="factoidtext">' . $facts[date('z')] . '</div>';
			}
			else {
				$content = file_get_contents('http://www.mentalfloss.com/amazingfactgenerator/?p=' . date('z'));
				$content = SimpleHTML::str_get_html($content);
				$fact = '<div class="factoidtext">FAIL:' . $content->find('.amazing_fact_body p', 0)->innertext . '</div>';
			}
			
			$msg = '<a href="#" class="close" onclick="return closedrawer({$drawer_id});">close this drawer</a>' . $fact;
			
			foreach($users as $user_id) {
				DB::get()->query("INSERT INTO drawers (user_id, message, indexed, cssclass) VALUES (:user_id, :msg, 'facts', 'factoid');", array('user_id' => $user_id, 'msg' => $msg));
			}
			DB::get()->query("DELETE FROM options WHERE name = 'facts' AND grouping = 'misc'");
			DB::get()->query("INSERT INTO options (name, grouping, value) VALUES ('facts', 'misc', :value);", array('value' => mktime(0, 0, 0)));
		}
		
		return $user;
	}
	
}

?>