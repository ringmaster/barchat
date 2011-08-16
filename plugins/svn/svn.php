<?php

Class SVN extends Plugin
{
	
	function commands($cmds){
		$cmds[1]['svn_create'] = array('%^/svn\s+create\s+(?P<reponame>\S+)?$%i', array($this, '_svn_create'), CMD_LAST);

		return $cmds;
	}
	
	function _svn_create($params) {
		$reponame = $params['reponame'];
		$channel = $params['channel'];
		$user = Auth::user();
		
		if(preg_match('%^\w+$%', $reponame)) {
			$repodir = '/var/svn/repos/' . $reponame;
			$output = '';
			$output .= "\n" . shell_exec('svnadmin create ' . $repodir);
			$output .= "\n" . shell_exec('chmod -R g+w ' . $repodir);
			$output .= "\n" . shell_exec('ln -s /var/svn/repos/barchat/hooks/post-commit ' . $repodir . '/hooks/post-commit');
			$output .= "\n" .shell_exec('svn mkdir --username rrs --password 1dc6e78c12a7ec76a349bc63730b85aa -m "Initial directory creation" https://sol.rockriverstar.com/svn/' . $reponame . '/trunk https://sol.rockriverstar.com/svn/' . $reponame . '/tags https://sol.rockriverstar.com/svn/' . $reponame . '/branches 2>&1');
			Status::create()
				->data(Utils::cmdout($params) . 'Created repo "' . $reponame . '" at https://sol.rockriverstar.com/svn/' . $reponame . '<br/><pre>' . str_replace("\n", '<br>', trim($output)) . '</pre>')
				->user_id($user->id)
				->channel($channel)
				->cssclass('svn')
				->insert();
		}
		else {
			Status::create()
				->data('Sorry, repo names must not already exist and must not contain spaces.')
				->user_id($user->id)
				->type('system')
				->cssclass('error')
				->user_to($user->id)
				->insert();
		}
		
		return true;
	}
	
	function autocomplete($auto, $cmd){
		$auto[] = "/svn create \treponame";
		return $auto;
	}
	
}

?>
