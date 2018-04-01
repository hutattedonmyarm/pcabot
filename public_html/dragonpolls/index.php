<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8" />
	<title>Dragonpolls</title>
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
	#$redirect_uri = 'http://localhost/dragonpolls/index.php';
	if (isset($_GET['poll'])) {
		$redirect_uri .= '?poll='.$_GET['poll'];
	}
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
		#header($redirect_uri);
		echo 'Redirecting to <a href="'.$redirect_uri.'">'.$redirect_uri.'</a><script>window.location.replace("'.$redirect_uri.'");</script>';
	} else { //Ask user to authorize
		echo '<a href="https://pnut.io/oauth/authenticate?client_id='.$client_id.'&redirect_uri='.urlencode($redirect_uri).'&scope=polls,write_post&response_type=code">Authorize with pnut.io</a>';
		die();
	}
}

function get_http_response_code($url) {
    $headers = get_headers($url);
    return substr($headers[0], 9, 3);
}

function get_data($endpoint, $parameters=array(), $method='GET', $contenttype='application/x-www-form-urlencoded', $force=false) {
	//Needs cleanup!
	if ($method == "PUT") {
		$ch = curl_init($endpoint);
		curl_setopt($ch, CURLOPT_POST, true);
		$postdata = http_build_query($parameters);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $postdata);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
		$headers = array();
		$headers[] = 'Authorization: Bearer '.$_SESSION['polls_auth_token'];
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLINFO_HEADER_OUT, true);
		curl_setopt($ch, CURLOPT_HEADER, true);
		$response = curl_exec($ch);
  		$last_request = curl_getinfo($ch,CURLINFO_HEADER_OUT);
    	$http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    	curl_close($ch);
    	return $response;
        
	} else {
		$postdata = http_build_query($parameters);
		if ($contenttype == 'application/json') {
			$postdata = json_encode($parameters);
		}
		$context_options = array (
	    'http' => array (
	       'method' => $method,
	       'content' => $postdata
	    )
		);
		if ($method == "PUT") {
			$context_options['http']['header'] = 'Authorization: Bearer '.$_SESSION['polls_auth_token'];
		} else {
			$context_options['http']['header'] = "Content-type: ".$contenttype."\r\n"
	            		.'Authorization: Bearer '.$_SESSION['polls_auth_token'];
		}
		$sapi_type = php_sapi_name();
		if (!$force && (substr($sapi_type, 0, 3) == 'cli' || empty($_SERVER['REMOTE_ADDR'])) && $method=='POST') {
			write_log("Running from shell instead of server. Debug mode assumed. Not submitting POST requests to server!", "DEBUG");
			write_log("Would have posted if run on a server: ".$postdata, "DEBUG");
			return;
		}
		$context = stream_context_create($context_options);
		$response = @file_get_contents($endpoint, false, $context);
		if ($response === false) {
			$code = get_http_response_code($endpoint);
			if ($code == 404) {
				die("Poll not found");
			}
			die("Error ".$code);
		}
		$resp_dict = json_decode($response);
		$response_code = $resp_dict->meta->code;
		#Success
		if ($response_code >= 200 && $response_code <= 208) {
			return $resp_dict;	
		} else {
			die();
		}
	}
}

function get_user_by_id($id, $users) {
	foreach ($users as $user) {
		if ($user->id == $id) {
			return $user;
		}
	}
}

echo '<div class="block-wrapper"><div class="inline-wrapper"><a href="new_poll.php" class="link-button">Create new poll</a></div></div>';

if (isset($_GET['poll'])) {
	$poll_id = $_GET['poll'];
	$poll = get_data('https://api.pnut.io/v0/polls/'.$poll_id,array('include_raw' => 1))->data;
	$token = $poll->poll_token;
	$already_responded = false;
	echo '<div><div class="box">'.$poll->prompt.'</div></div>';
	if (isset($poll->user)) {
		echo '<div class="box secondary">By @'.$poll->user->username.'</div>';
	}
	echo '<div><form action="?poll='.$poll_id.'" class="box">';
	$respondents = array();
	$respondents_data;
	$respondents_request_reply;
	$respond_divs = array();
	$num_total_respondents = 0; 
	foreach ($poll->options as $option) {
		if (isset($option->respondents)) {
			$num_total_respondents += $option->respondents;
			if (isset($option->respondent_ids) && count($option->respondent_ids) > 0) {
				//TODO: Use append!
				foreach ($option->respondent_ids as $r_id) {
					$respondents[] = $r_id;
				}
			}
		}
	}
	if (count($respondents) > 0) {
		$respondents_request_reply = get_data('https://api.pnut.io/v0/users?ids='.implode(',', $respondents));
		if ($respondents_request_reply->meta->code == 200) {
			$respondents_data = $respondents_request_reply->data;
		}	
	}
	$status_text = '';
	$status = '';
	$optn_status = '';
	if (isset($poll->closed_at) && strtotime($poll->closed_at) <= time()) {
		$status_text = 'This poll has been closed since '.$poll->closed_at;
		$status = ' title="'.$status_text.'" style="display:none;"';
		$optn_status = 'disabled';
	}
	foreach ($poll->options as $option) {
		echo '<div class="option">';
		$checked = '';
		if (isset($option->is_your_response) && $option->is_your_response == true) {
			$already_responded = true;
			$checked = 'checked';
		}
		if ($already_responded === true) {
			$status_text = 'You already responded to this poll';
			$status = ' title="'.$status_text.'" style="display:none;"';
			$optn_status = 'disabled';
		}
		echo '	<input type="radio" name="answer" value="'.$option->position.'" '.$checked.' '.$optn_status.'>';
		echo '	<div class="option-wrapper">';
		echo '		<div class="option-text">'.$option->text;
		if (isset($option->respondents)) {
			$percentage = 0;
			if ($num_total_respondents != 0) {
				$percentage = round(100*$option->respondents/$num_total_respondents,2);
			}
			echo ' ('.$percentage.'%)</div>';
			if (isset($option->respondent_ids) && count($option->respondent_ids) > 0 && isset($respondents_data)) {
				echo '<div class="option-images">';
				$users_string = '';	
				foreach ($option->respondent_ids as $r_id) {
					$user = get_user_by_id($r_id, $respondents_data);
					$users_string .= '<a href="https://beta.pnut.io/@'.$user->username.'">';
					$users_string .= '<img src="'.$user->content->avatar_image->link.'" class="micro-avatar" title="@'.$user->username.'"/>';
					$users_string .= '</a>';
				}
				echo $users_string;
				echo '</div>';
			}
		} else {
			echo '</div>';
		}
		echo '	</div>';
		echo '</div>';
	}
	
	echo '<input type="hidden" value="'.$poll_id.'" name="poll">';
	echo '<div class="submit-wrapper"><input type="submit" value="Submit" class="link-button" name="Submit"'.$status.'><div class="submit-warning" id="submit-warning">'.$status_text.'</div></div>';
	echo '</form></div>';
	if (isset($_GET['Submit']) && isset($_GET['answer'])) {
		$vote = json_decode(explode("\r\n\r\n",get_data('https://api.pnut.io/v0/polls/'.$poll_id.'/response/'.$_GET['answer'], array(), "PUT"),2)[1]);
		if ($vote->meta->code != 200) {
			if ($vote->meta->code == 400) {
				die("Error: ".$vote->meta->error_message);
			}
			die("Error: ".json_encode($vote->meta));
		}
		echo '<script>window.location.replace("index.php?poll='.$poll_id.'");</script>';
	}
}
?>
</body>
</html>