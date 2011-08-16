<?php

class TwitterPlugin extends Plugin
{
	
	function commands($cmds){
		$cmds[1]['twitter'] = array('%^/(twitter)\s+(?P<query>.+)$%i', array($this, '_twitter'), CMD_LAST);
		return $cmds;
	}
	
	function _twitter($params){
		$user = $params['user'];
		$channel = $params['channel'];
		$query = $params['query'];

		$thtml = file_get_contents('http://twitter.com/' . $query);
		$content = SimpleHTML::str_get_html($thtml);
		$img = $content->find('#profile-image', 0);
		$li = $content->find('li[class=latest-status]', 0);
		$lia = $li->find('a');
		foreach($lia as $a) {
			$a->href = 'http://twitter.com/' . $a->href;
		}
		$img->width = '48px';
		$img->height = '48px';
		$img->id = '';
		$img->class = "profile_image";
		$output = '<div class="slash">/twitter ' . htmlspecialchars($query) . '</div>';
		$output .= '<div class="twitterstatus">' . $img->outertext . '<a href="http://twitter.com/' . htmlspecialchars($query) . '">' . htmlspecialchars($query) . '</a>' . $li->innertext . '<hr></div>';
		
		DB::get()->query("INSERT INTO presence (data, user_id, channel, cssclass) VALUES (:msg, :user_id, :channel, 'twitter')", array('msg' => $output, 'user_id' => $user->id, 'channel' => $channel));
		return true;
	}
	
	function header($args)
	{
		echo <<< HEADER
<style type="text/css">
.twitter .profile_image {
	float: left;
	margin-right: 10px;
}
.twitterstatus {
	border-top: 1px solid #eee;
	border-bottom: 1px solid #eee;
	padding: 10px 10px 8px;
	width: 100%;
	margin: 10px 0px 10px 10px;
	background-color: white;
	font-family: 'Lucida Grande',sans-serif;
	color: #333;
}
.twitterstatus .entry-meta {
	display: block;
	font-size: smaller;
	margin-top: 5px;
}
#notices .twitterstatus .entry-meta a:visited,
#notices .twitterstatus .entry-meta a {
	color: #333;
	text-decoration: none;
}
.twitterstatus hr {
	clear: both;
	visibility: hidden;
	height: 0px;
	margin: 0px;
}
</style>
HEADER;
	}

}
?>