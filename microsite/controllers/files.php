<?php

class FilesController {
	
	
	function index($path){
		$files = DB::get()->results("SELECT files.*, users.username FROM files INNER JOIN users ON users.id = files.user_id ORDER BY filedate DESC limit 15;");
		
		$components = array(
			'files' => $files,
		);
		
		$v = new View($components);
		$v->render('files');
	}
	
	function upload($path){
		$access = DB::get()->assoc("SELECT name, value FROM options WHERE grouping = 'Amazon Web Services'");

		$s3 = new S3($access['AWS Access Key ID'], $access['AWS Secret Access Key']);

		$bucketname = $access['S3 Bucket Name'];
		
		$filename = $_FILES['uploaded']['name'];
		$s3filename = $this->_safestring(Auth::user()->username) . '/' . date('YmdHis') . '/' . $filename;
		
		preg_match('%\.(\w+)$%', $filename, $matches);
		$filetype = $matches[1];

		$s3->putObject(S3::inputFile($_FILES['uploaded']['tmp_name']), $bucketname, $s3filename, S3::ACL_PUBLIC_READ, array(), array("Content-Type" => "application/octet-stream", "Content-Disposition" => "attachment; filename=" . urlencode($filename) . ';'));
		//echo "Put {$filename} to {$bucketname} at {$s3filename}\n";
		
		$url = "http://{$bucketname}.s3.amazonaws.com/{$s3filename}";
		
		DB::get()->query("INSERT INTO files (user_id, filename, filesize, filetype, url) VALUES (:user_id, :filename, :filesize, :filetype, :url);", array('user_id'=>Auth::user_id(), 'filename' => $filename, 'filesize' => $_FILES['uploaded']['size'], 'filetype' => $filetype, 'url' => $url));
		$filenumber = DB::get()->lastInsertId();
		echo <<< RELOAD_FILES
atbottom = isVisible($('#notices tr:last-child'));
$('#filelisting').load('/files/filelist', function(){
	$('body').css('margin-bottom', $('#command').height() + 15);
	do_scroll();
});
send('/file {$filenumber}');
RELOAD_FILES;
	}
	
	function _safestring($string){
		return strtolower(preg_replace('%\W+%', '', $string));
	}
	
	function filelist($path){
		$files = DB::get()->results("SELECT files.*, users.username FROM files INNER JOIN users ON users.id = files.user_id ORDER BY filedate DESC limit 15;");
		$components = array(
			'files' => $files,
		);
		
		$v = new View($components);
		$v->render('filelist');
	}
	
	function get($path){
		$filenumber = intval($_GET['file']);
		
		$file = DB::get()->row('SELECT * FROM files WHERE id = :id', array('id' => $filenumber));
		
		if($file) {
			header('Content-Type: application/octet-stream');
			header('Content-Disposition: attachment; filename=' . urlencode($file->filename) . ';');
			
			$url = parse_url($file->url);
			$bucket = str_replace('.s3.amazonaws.com', '', $url['host']);
			
			$access = DB::get()->assoc("SELECT name, value FROM options WHERE grouping = 'Amazon Web Services'");
			$s3 = new S3($access['AWS Access Key ID'], $access['AWS Secret Access Key']);
			$uri = $s3->getAuthenticatedURL($bucket, trim($url['path'], '/'), 3600);

			//readfile($file->url);
			header('location: ' . $uri);

			exit();
		}
	}

}
?>