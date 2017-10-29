<?php
$redirect_uri = 'http://wedro.online/pnutauth.php';
if (isset($_GET['code'])) {
	$code = $_GET['code'];
	$postdata = http_build_query(
		array(
			'client_id' => 'RwHDh73PtU0It4DdKhwh2GEagBoO1ELD',
			'client_secret' => 'sRyV8OwPM1hm8BSbtoKVRVpscRfb4tif',
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
	file_put_contents('../access_token', $resp['access_token']);
	header('Location: http://wedro.online/Check_PCA.php');
} else {
	header('Location: https://pnut.io/oauth/authenticate?client_id=RwHDh73PtU0It4DdKhwh2GEagBoO1ELD&redirect_uri='.urlencode($redirect_uri).'&scope=write_post&response_type=code');
}
?>