<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8" />
	<title>Dragonpolls - New poll</title>
	<link rel="stylesheet" href="styles/style.css" />
</head>
<body>
<?php

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

$auth_token = null;
if (isset($_SESSION['polls_auth_token'])) {
	$auth_token = $_SESSION['polls_auth_token'];
}
if ($auth_token == null) { //Not yet authorized
	header('Location: https://wedro.online/dragonpolls/index.php');
}
if (isset($_POST['submit'])) {
	$question = $_POST['question'];
	$answers = $_POST['option'];
	$duration = 1;
	if (isset($_POST['totalDuration'])) {
		$duration = $_POST['totalDuration'];
	} else {
		$duration = $_POST['duration_minutes'] + $_POST['duration_hours']*60 + $_POST['duration_days']*60*24;
	}
	$anonymous = isset($_POST['anonymous']);
	$public = isset($_POST['public']);
	
	$contenttype = 'application/json';
	$method = 'POST';
	$endpoint = 'https://api.pnut.io/v0/polls';
	$type = 'io.pnut.core.poll';
	$options = array();
	foreach ($answers as $answer) {
		$o = array();
		if ($answer != "") {
			$o['text'] = $answer;
			$options[] = $o;
		}
	}
	$parameters = array('prompt' => $question, 'type' => $type, 'options' => $options, 'duration' => $duration, 'is_public' => $public, 'is_anonymous' => $anonymous);
	$new_poll = get_data($endpoint, $parameters, $method, $contenttype);
	$success = isset($new_poll->meta, $new_poll->meta->code) && $new_poll->meta->code == 201;
	#$success = true;
	if ($success) {
		echo 'Poll creation succesful!.<a href="index.php?poll='.$new_poll->data->id.'" class="link-button">Go to poll</a><br>';
		echo '<form method="POST">';
		echo '<textarea rows="4" cols="50" name="postText" maxlength="256">I created a new poll:
'.$question.'
https://wedro.online/dragonpolls/index.php?poll='.$new_poll->data->id.'
		</textarea><br>
		<input type="hidden" name="pollID" value="'.$new_poll->data->id.'">
		<input type="hidden" name="pollToken" value="'.$new_poll->data->poll_token.'">
		<input type="submit" name="postSubmit" value="Post to pnut" class="link-button">
		</form>';
		#echo '<script>window.location.replace("index.php?poll='.$new_poll->data->id.'");</script>';
		die();
	} else {
		die("Error creating poll: ".json_encode($new_poll));
	}
}

if (isset($_POST['postSubmit'])) {
	echo '<script>console.log("Posting poll");</script>';
	$text = $_POST['postText'];
	$endpoint = 'https://api.pnut.io/v0/posts';
	$raw_value = array('+io.pnut.core.poll' => array('poll_id' => $_POST['pollID'], 'poll_token' => $_POST['pollToken']));
	$raw = array(array('type' => 'io.pnut.core.poll-notice', 'value' => $raw_value));
	$parameters = array('text' => $text, 'raw' => $raw);
	$poll_post = get_data($endpoint, $parameters, 'POST');
	$success = isset($poll_post->meta, $poll_post->meta->code) && $poll_post->meta->code == 201;
	if ($success) {
		echo 'Poll creation succesful!.<a href="index.php?poll='.$_POST['pollID'].'" class="link-button">Go to poll</a><br>';
	} else {
		die("Error creating poll: ".json_encode($poll_post));
	}
}

function redirect($url) {
	header("Location: ".$url);
	echo '<meta http-equiv="refresh" content="0;url='.$url.'">';
	echo '<script>window.location.replace("'.$url.'");</script>Redirecting to <a href="'.$url.'">'.$url.'</a>';
}

?>
<script>
	function validatePoll() {
		let maxlength = 256;
		let minAnswers = 2;
		let maxAnswers = 10;
		let minDuration = 1;
		let maxDuration = 20160;
		let isValid = document.getElementById('question').value.length <= maxlength && document.getElementById('question').value.length > 0;
		let numAnswers = 0;
		let options = document.getElementById('options').getElementsByTagName('input');
		for (var i = 0; i < options.length; i++) {
			isValid = isValid && options[i].value != undefined && options[i].value.length <= maxlength;
			if (options[i].value.length > 0) {
				numAnswers++;
			}
		}
		isValid = isValid && numAnswers >= minAnswers && numAnswers <= maxAnswers;

		let totalDuration = 0;
		totalDuration += parseInt(document.getElementById('duration_minutes').value);
		totalDuration += parseInt(document.getElementById('duration_hours').value) * 60;
		totalDuration += parseInt(document.getElementById('duration_days').value) * 60 * 24;
		if (totalDuration > maxDuration) {
			isValid = false;
			alert('Maximal allowed duration is ' + maxDuration + ' minutes (' + (maxDuration/60/24) + ' days). Yours is ' + (totalDuration-maxDuration) + ' too long!');
		}
		if (totalDuration < minAnswers) {
			isValid = false;
			alert('Minimal duration is ' + minDuration + ' minutes');
		}
		document.getElementById('totalDuration').value = totalDuration;
		return isValid;
	}
</script>
<form onsubmit="return validatePoll();" method="POST">
	<table>
		<tr>
			<td><label for="question">Question: </label></td>
			<td><input type="text" name="question" id="question" maxlength="256" size="80" required></td>
		</tr>
		<tr>
			<td valign="top">Options:</td>
			<td id="options">
				<input type="text" name="option[]" maxlength="256" size="80" required><br>
				<input type="text" name="option[]" maxlength="256" size="80" required><br>
				<input type="text" name="option[]" maxlength="256" size="80"><br>
				<input type="text" name="option[]" maxlength="256" size="80"><br>
				<input type="text" name="option[]" maxlength="256" size="80"><br>
				<input type="text" name="option[]" maxlength="256" size="80"><br>
				<input type="text" name="option[]" maxlength="256" size="80"><br>
				<input type="text" name="option[]" maxlength="256" size="80"><br>
				<input type="text" name="option[]" maxlength="256" size="80"><br>
				<input type="text" name="option[]" maxlength="256" size="80"><br>
			</td>
		</tr>
		<tr>
			<td valign="top"><label for="duration_days">Duration: </label></td>
			<td>
				Days: <input type="number" name="duration_days" id="duration_days" max="14" min="0" size="5" value="0">
				Hours: <input type="number" name="duration_hours" id="duration_hours" max="23" min="0" value="0">
				Minutes: <input type="number" name="duration_minutes" id="duration_minutes" max="59" min="0" value="1">
			</td>
		</tr>
		<tr>
			<td><label for="anonymous">Anonymous: </label></td>
			<td><input type="checkbox" name="anonymous" id="anonymous"></td>
		</tr>
		<tr>
			<td><label for="public">Public: </label></td>
			<td><input type="checkbox" name="public" id="public"></td>
		</tr>
		<tr>
			<td>
				<input type="hidden" name="totalDuration" id="totalDuration">
				<input type="submit" name="submit" class="link-button">
			</td>
		</tr>
	</table>
</form>
