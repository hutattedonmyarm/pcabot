<?php
$dir_cur = dirname(__FILE__);
$dir_root = dirname(dirname($dir_cur));
$files = array_diff(scandir($dir_cur.'/pictures/'), array('.', '..'));
$saved_tokens = json_decode(file_get_contents($dir_root.'/themepic_tokens.json'), true);
if ($saved_tokens == null) {
	die();
}
write_log("Checking for thememonday picture resets...");
$saved_token = "";
foreach ($files as $picture) {
	$pic = 'pictures/'.$picture;
	write_log("Checking ".$picture);
	$file_name_components = explode('_', $picture);
	$datestring = $file_name_components[1];
	$username = $file_name_components[0];
 	$diff = time() - strtotime($datestring);
 	if ($diff >= 0) { #In the past, reset to old picture
 		write_log("Resetting to old picture");
 		#Grab auth token
 		if (array_key_exists($username, $saved_tokens)) {
			$saved_token = $saved_tokens[$username];
		} else {
			break;
		}
		#Reset picture
		$finfo = finfo_open(FILEINFO_MIME_TYPE);
		$mime = finfo_file($finfo, $pic);
		finfo_close($finfo);
		$ch = curl_init();
		$headers[] = 'Authorization: Bearer '.$saved_token;
		$data = array('avatar' => curl_file_create($pic, $mime));
		curl_setopt($ch, CURLOPT_URL, 'https://api.pnut.io/v0/users/me/avatar');
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		$result = json_encode(curl_exec($ch), true);
		write_log($result);
		#Delete file
		unlink($pic);
 	} else {
 		write_log("Not yet ready to reset");
 	}
}
function write_log($string, $level="INFO") {
	$dir_root = dirname(dirname(dirname(__FILE__)));
	file_put_contents($dir_root.'/logs/'.date('Y-m-d').'.log', '['.$level.'] '.date('H:i:s').' '.$string.PHP_EOL, FILE_APPEND | LOCK_EX);	
}
?>