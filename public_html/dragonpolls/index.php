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
$auth_token = null;
if (isset($_SESSION['polls_auth_token'])) {
	$auth_token = $_SESSION['polls_auth_token'];
}
if ($auth_token == null) { //Not yet authorized
	$client_id = 'kAA6Qzi6ErYcqg12ljZCGie_9u3GVXwv';
	$redirect_uri = 'https://wedro.online/dragonpolls/index.php';
	if (isset($_GET['code'])) { //Auth token received by pnut
		$client_secret = file_get_contents('../../clientsecret_dragonpolls');
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
		$_SESSION['polls_auth_token'] = $resp['access_token'];
		header('Location: https://wedro.online/dragonpolls/index.php');
	} else { //Ask user to authorize
		echo '<a href="https://pnut.io/oauth/authenticate?client_id='.$client_id.'&redirect_uri='.urlencode($redirect_uri).'&scope=polls,write_post&response_type=code">Authorize with pnut.io</a>';
		die();
		#header('Location: https://pnut.io/oauth/authenticate?client_id='.$client_id.'&redirect_uri='.urlencode($redirect_uri).'&scope=write_post,update_profile&response_type=code');
	}
}
?>