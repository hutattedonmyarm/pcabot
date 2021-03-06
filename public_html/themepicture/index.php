<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8" />
	<title>Thememonday picture helper</title>
	<link rel="stylesheet" href="styles/style.css" />
</head>
<body>
<?php
#$_SESSION['auth_token'] = 'DOLORSITAmEThjvsKrb0BSIB_agtmhYJluujm3jZEVwDKloyCmDjv-vF18Lsxuv1j3YfRMNoySgbf30irzQhGx5Fk4RJMt_dSO';
$auth_token = null;
if (isset($_SESSION['auth_token'])) {
	$auth_token = $_SESSION['auth_token'];
}
if ($auth_token == null) { //Not yet authorized
	$client_id = '4sVqQCD04JmqhlnO6m8NFiEI8vHcGgQs';
	#$redirect_uri = 'https://themepic.wedro.online/pnutauth.php';
	$redirect_uri = 'https://wedro.online/themepicture/index.php';
	if (isset($_GET['code'])) { //Auth token received by pnut
		$client_secret = file_get_contents('../../clientsecret_themepic');
		$code = $_GET['code'];
		$postdata = http_build_query(
			array(
				'client_id' => $client_id,
				'client_secret' => $client_secret,
				'code' => $code,
				'redirect_uri' => $redirect_uri,
				'grant_type'=> 'authorization_code'
			)
		);
		$context_options = array (
			'http' => array (
			   'method' => 'POST',
			   'header'=> "Content-type: application/x-www-form-urlencoded\r\n",
			   'content' => $postdata
			)
		);
		$context = stream_context_create($context_options);
		$resp = json_decode(file_get_contents('https://api.pnut.io/v0/oauth/access_token', false, $context), true);
		$_SESSION['auth_token'] = $resp['access_token'];
		header('Location: https://wedro.online/themepicture/index.php');
	} else { //Ask user to authorize
		echo '<a href="https://pnut.io/oauth/authenticate?client_id='.$client_id.'&redirect_uri='.urlencode($redirect_uri).'&scope=update_profile&response_type=code">Authorize with pnut.io</a>';
		die();
		#header('Location: https://pnut.io/oauth/authenticate?client_id='.$client_id.'&redirect_uri='.urlencode($redirect_uri).'&scope=write_post,update_profile&response_type=code');
	}
}
$result_color = "white";
$result_display = "none";
$result_text = "";
$profile = get_data('https://api.pnut.io/v0/users/me');
$username = $profile['data']['username'];
$saved_tokens = json_decode(file_get_contents('../../themepic_tokens.json'), true);
if ($saved_tokens == null) {
	$saved_tokens = [];
}
$saved_token = "";
if (array_key_exists($username, $saved_tokens)) {
	$saved_token = $saved_tokens[$username];
}
if ($saved_token != $_SESSION['auth_token']) {
	//Save new token
	$saved_tokens[$username] = $_SESSION['auth_token'];
	file_put_contents('../../themepic_tokens.json', json_encode($saved_tokens));
}
$picture_title = "Current picture:";
$files = glob('./pictures/'.$username.'*');
$current_picture = $profile['data']['content']['avatar_image']['link'];
$display_warning = 'none';
if(count($files) > 0) {
	$picture_title = "Thememonday picture:";
	$display_warning = '';
}
if (isset($_POST['submit'])) {
	$duration = $_POST['duration'];
	if ($duration == null || $duration <= 0) {
		$duration = 3;
	}
	$path = './pictures/'.$username.'_'.Date('Y-m-d H:m:s', strtotime('+'.$duration.' days'));
	if(count($files) > 0) {
		unlink($files[0]);
	}
	if ($current_picture != null) {
		copy($current_picture, $path);
	}
	$uploadfile = $_FILES['pic']['tmp_name'];
	$finfo = finfo_open(FILEINFO_MIME_TYPE);
	$mime = finfo_file($finfo, $uploadfile);
	finfo_close($finfo);
	$ch = curl_init();
	$headers[] = 'Authorization: Bearer '.$_SESSION['auth_token'];
	$data = array('avatar' => curl_file_create($uploadfile, $mime));
	curl_setopt($ch, CURLOPT_URL, 'https://api.pnut.io/v0/users/me/avatar');
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_POST, 1);
	curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
	curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
	$result = json_decode(curl_exec($ch), true);
	$result_display = "";
	if ($result['meta']['code'] == 200) {
		$current_picture = $result['data']['content']['avatar_image']['link'];
		$result_color = "green";
		$result_text = "Successfully set temporary profile picture for ".$duration." days";
	} else {
		$result_color = "";
		$result_text = "Error setting temporary picture :( ".print_r(json_encode($result));
	}
}
?>
<div class="container">
	<div class="center" style="background-color: <?php echo $result_color; ?>;color: white; display: <?php echo $result_display;?>">
		<?php echo $result_text; ?>
	</div>
	<div class="center"><?php echo $picture_title ?></div>
	<div class="center">
<?php
echo '<img id="current-picture" src="'.$current_picture.'" />';

function get_data($endpoint, $parameters=array(), $method='GET', $contenttype='application/x-www-form-urlencoded', $force=false) {
	$postdata = http_build_query($parameters);
	if ($contenttype == 'application/json') {
		$postdata = json_encode($parameters);
	}
	$context_options = array (
    'http' => array (
       'method' => $method,
       'header'=> "Content-type: ".$contenttype."\r\n"
            		.'Authorization: Bearer '.$_SESSION['auth_token'],
       'content' => $postdata
    )
	);
	$sapi_type = php_sapi_name();
	if (!$force && (substr($sapi_type, 0, 3) == 'cli' || empty($_SERVER['REMOTE_ADDR'])) && $method=='POST') {
		write_log("Running from shell instead of server. Debug mode assumed. Not submitting POST requests to server!", "DEBUG");
		write_log("Would have posted if run on a server: ".$postdata, "DEBUG");
		return;
	}
	$context = stream_context_create($context_options);
	$response = file_get_contents($endpoint, false, $context);
	$resp_dict = json_decode($response, true);
	$response_code = $resp_dict['meta']['code'];
	#Success
	if ($response_code >= 200 && $response_code <= 208) {
		return $resp_dict;	
	} else {
		die();
	}
}
?>
	</div>
	<div id="overwrite-warning" class="center" style="background-color: red;color: white; display: <?php echo $display_warning;?>">
		Warning: Overwriting current temporary picture. Your profile picture will still restore to the original after the specified amount of days has passed
	</div>
	<form method="POST" enctype="multipart/form-data" action="">
		<div class="center">Reset to current after X days:<br /> <input type="number" name="duration"></div>
		<div class="center"><input type="file" name="pic" required></div>
		<div class="center"><input type="submit" name="submit"></div>
	</form>
</div>
</body>
</html>
