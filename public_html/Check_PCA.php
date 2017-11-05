<?php

function write_log($string, $level="INFO") {
	file_put_contents('../logs/'.date('Y-m-d').'.log', '['.$level.'] '.date('H:i:s').' '.$string.PHP_EOL, FILE_APPEND | LOCK_EX);	
}

class User {
   public $user_name;
   public $name;
   public $number_posts;
   public $following;
   public $follows_bot;
   public $last_club_notification = "";

	private static function clean_api_response($api_reponse) {
		$data = array();
   	if (array_key_exists('data', $api_reponse)) {
			$data = $api_reponse['data'];
   	} else {
   		$data = $api_reponse;
   	}
   	return $data;
	}

   public function __construct($api_reponse) {
   	$data = self::clean_api_response($api_reponse);
   	$this->user_name = '@'.$data['username'];
		$this->number_posts = preg_replace('/\s+/', '', $data['counts']['posts']);
		$this->follows_bot = $data['follows_you'];
		$this->name = array_key_exists('name', $data) ? $data['name'] : '';
   }

   public static function get_users_from_api_reponse($api_reponse) {
   	$data = self::clean_api_response($api_reponse);
   	$users = array();
   	#Single user
   	if (count(array_filter(array_keys($data), 'is_string')) > 0) {
			$users[] = new User($data);
		} else { #Multiple users
			foreach ($data as $key => $value) {
				$users[] = new User($value);
			}
		}
		return $users;
   }

   public function get_highest_pca($clubs) {
   	$key = $this->get_next_pca_key($clubs)-1;
   	if ($key == -1) {
   		return array();
   	}
   	return $clubs[$key];
   }

   public function get_next_pca($clubs) {
   	return $clubs[$this->get_next_pca_key($clubs)];
   }

   private function get_next_pca_key($clubs) {
   	if (preg_replace('/\s+/', '', $clubs[0]['post_count']) > $this->number_posts) {
   		return 0;
   	}
   	foreach ($clubs as $key => $club) {
   		if (preg_replace('/\s+/', '', $club['post_count']) > $this->number_posts) {
   			return $key;
   		}
   	}
   }
}

class API {
	public $access_token = '';
	private static $api_endpoint = 'https://api.pnut.io/v0';
	public $max_posttext_length = 256;
	private static $settings_file_location = '../user_settings.json';
	private static $settings = [];
	private static $default_notification_text = 'Congratulations {user.username}, you are now a member of #{pca.name} {pca.emoji} ({pca.postcount} posts)! Next: {nextpca.emoji} at {nextpca.postcount} posts';
	private static $notification_tokens = ['{user.username}', '{user.name}', '{pca.name}', '{pca.emoji}', '{pca.postcount}', '{nextpca.name}', '{nextpca.emoji}', '{nextpca.postcount}', '{posts_to_pca}'];

	public function init() {
		$this->max_posttext_length = $this->get_data('https://api.pnut.io/v0/sys/config')['data']['post']['max_length'];
		if (!file_exists(self::$settings_file_location)) {
			file_put_contents(self::$settings_file_location, json_encode([]));
			write_log("No user settings file found. Creating empty one");
		}
		self::$settings = json_decode(file_get_contents(self::$settings_file_location), true);
		if (condition) {
			# code...
		}
		write_log("User settings fille loaded");
	}

	private function get_data($endpoint, $parameters=array(), $method='GET', $contenttype='application/x-www-form-urlencoded') {
		write_log("Making request to pnut API at ".$endpoint);
		$postdata = http_build_query($parameters);
		$context_options = array (
        'http' => array (
           'method' => $method,
           'header'=> "Content-type: ".$contenttype."\r\n"
                		.'Authorization: Bearer '.$this->access_token,
           'content' => $postdata
        )
    	);
    	$sapi_type = php_sapi_name();
    	if ((substr($sapi_type, 0, 3) == 'cli' || empty($_SERVER['REMOTE_ADDR'])) && $method=='POST') {
    		write_log("Running from shell instead of server. Debug mode assumed. Not submitting POST requests to server!", "DEBUG");
    		write_log("Would have posted if run on a server: ".$postdata, "DEBUG");
    		return;
		}
    	$context = stream_context_create($context_options);
    	$response = file_get_contents($endpoint, false, $context);
    	$resp_dict = json_decode($response, true);
    	write_log("Got server response. Meta: ".json_encode($resp_dict['meta']));
    	$response_code = $resp_dict['meta']['code'];
    	#Success
    	if ($response_code >= 200 && $response_code <= 208) {
    		return $resp_dict;	
    	} else {
    		write_log("Received error-response from server. ".json_encode($resp_dict['meta']), "ERROR");
    		die();
    	}
	}

	public function get_messages($clubs) {
		$num_unread_endpoint = self::$api_endpoint.'/users/me/channels/num_unread/pm';
		$num_unread_pms = $this->get_data($num_unread_endpoint)['data'];
		$messages = []; //Keys: username, values: message-array of messages by the user
		write_log($num_unread_pms.' unread PMs');
		if ($num_unread_pms > 0) {
			$channels_endpoint = self::$api_endpoint.'/users/me/channels/subscribed?include_read=0,channel_types=io.pnut.core.pm';
			$channels = $this->get_data($channels_endpoint);
			foreach ($channels['data'] as $channel) {
				write_log("Channel: ".$channel['id'], "DEBUG");
				$messages_endpoint = self::$api_endpoint.'/channels/'.$channel['id'].'/messages?include_deleted=0&include_html=0&include_client=0';
				$unread_messages = $this->get_data($messages_endpoint);
				$msg = [];
				$sender = "";
				foreach ($unread_messages['data'] as $message) {
					$message_text = $message['content']['text'];
					$sender = $message['user']['username'];
					$msg[] = $message['content']['text'];
					write_log('Message from '.$sender.': '.$message_text, "DEBUG");
				}
				$messages[$sender] = $msg;
			}
			/*
			 * Message command syntax:
			 * "Help" or "?" => prints help
			 * Things to include in help text:
			 * - Available commands and syntax
			 * - Variables
			 * - 'username' includes the '@', pca names (current and next) don't incldue the #
			 * "Get notification text" => replies with notification text
			 * "Reset notification text" => resets to default
			 * "Set notification text" followed by a space and then the text. Replies with sample text. Available tokens see class var: 
			 */	
		}
		return $messages;
	}

	private function build_notification_text($user, $clubs, $current_pca, $next_pca) {
		$notification_text = 'Congratulations {user.username}, you are now a member of #{pca.name} {pca.emoji} ({pca.postcount} posts)! Next: {nextpca.emoji} at {nextpca.postcount} posts';
		$token_values = [$user->user_name, $user->name, $current_pca['pca'], $current_pca['emoji'], $current_pca['post_count'], $next_pca['pca'], $next_pca['emoji'], $next_pca['post_count'], ($next_pca['post_count'] - $user->number_posts)];
		if (array_key_exists($user->user_name, self::$settings)) {
			write_log($user->user_name." has custom settings: ".json_encode(self::$settings[$user->username]));
			$notification_text = self::$settings[$user->username];
		} else {
			write_log($user->user_name." does not have custom settings!");
		}
		foreach (self::$notification_tokens as $index => $token) {
			$notification_text = str_replace($token, $token_values[$index], $notification_text);
		}
		return $notification_text;
	}
	public function write_post($posttext, $reply_to=-1) {
		$post_endpoint = self::$api_endpoint.'/posts';
		$parameters = array('text' => mb_strimwidth($posttext, 0, $this->max_posttext_length, ""));
		if ($reply_to != -1) {
			$parameters['reply_to'] = $reply_to;
		}		
		$this->get_data($post_endpoint, $parameters, 'POST');
	}
	
	public function get_user($user_id) {
		$user_endpoint = self::$api_endpoint.'/users/'.$user_id;
		return new User($this->get_data($user_endpoint));
	}

	public function get_bot_followers() {
		$before_id = null;
		$users = array();
		do {
			write_log("Getting followers before id: ".$before_id);
			$followers_endpoint = self::$api_endpoint.'/users/me/followers';
			if ($before_id != null) {
				$followers_endpoint .='?before_id='.$before_id;	
			}
			$followers_data = $this->get_data($followers_endpoint,["before_id" => $before_id]);
			$before_id = $followers_data['meta']['min_id'];
			$users = array_merge($users, User::get_users_from_api_reponse($followers_data));
		} while($followers_data['meta']['more'] == '1');
		return $users;
	}

	public function notify_user($user, $clubs) {
		#Get current PCA
		$current_pca_dict = $user->get_highest_pca($clubs);
		$current_pca = "";
		if (array_key_exists('pca', $current_pca_dict)) {
			$current_pca = $current_pca_dict['pca'];
		} else { #If key doesn't exist, user hasn't reached a club yet
			return;
		}
		$last_notification = $user->last_club_notification;
		$next_pca = $user->get_next_pca($clubs);
		if ($current_pca != $last_notification) { #Havent't notified about the current club yet
			#Stitch together the post itself
			$this->build_notification_text($user, $clubs, $current_pca_dict, $next_pca);
			$text_components = array();
			$posttext = "Congratulations ".$user->user_name.", you are now a member of #".preg_replace('/\s+/', '', $current_pca).' '.$current_pca_dict['emoji'];
			$text_components[] = ' ('.$current_pca_dict['post_count'].'+ posts)!';
			$text_components[] = ' Next: '.$next_pca['emoji'].' at '.$next_pca['post_count'].' posts';
			foreach ($text_components as $component) {
				if (strlen($posttext) < ($this->max_posttext_length - strlen($component))) {
					$posttext .= $component;
				}
			}
			/*
			 * Steps to write post as a reply to the post which made a user enter a new club:
			 * 1) Get number of user posts (we already have that info)
			 * 2) Substract pca post count value from number posts. This will give us an offset
			 * 3) Get their posts with ?count=OFFSET&include_html=0&include_counts=0&include_client=0
			 * 4) Check if counts match. If not, get more of their posts with before_id set to the relevant ID
			 * 5) Repeat 4 until we have the correct post
			 * 6) Reply to it
			 */
			$pca_offset = ($user->number_posts - $current_pca_dict['post_count']) + 1;
			$before_id = -1;
			do {
				$ep = self::$api_endpoint.'/users/'.$user->user_name.'/posts?count='.$pca_offset.'&include_html=0&include_counts=0&include_client=0&include_deleted=1';
				$response = $this->get_data($ep, array());
				$pca_offset -= count($response['data']);
				$before_id = $response['meta']['min_id'];
				write_log("Received ".count($response['data'])." posts", "DEBUG");
			} while ($pca_offset > 0);
			$reply_to = $response['data'][count($response['data'])-1]['id'];
			if (!isset($reply_to) || $reply_to == 0) {
				$reply_to = -1;
			}
			$deleted = $response['data'][count($response['data'])-1]['is_deleted'] == 'true';
			$log_string = "Post that made them reach ".preg_replace('/\s+/', '', $current_pca).": ".$reply_to.'. Deleted?: ';
			$log_string .= $deleted ? 'yes' : 'no';
			$this->write_post($posttext, $reply_to);
			$user->last_club_notification = $current_pca;
			write_log($posttext);
			write_log($log_string);
			$now = DateTime::createFromFormat('U.u', microtime(true));
			$recent_changes_dict = ['date' => $response['data'][count($response['data'])-1]['created_at'], 'user' => $user->user_name, 'pca' => $current_pca, 'post_id' => $reply_to];
			$history_file = 'history.json';
			$inp = file_get_contents($history_file);
			$tempArray = json_decode($inp, true);
			if ($tempArray == null) {
				$tempArray = [];
			}
			array_push($tempArray, $recent_changes_dict);
			file_put_contents($history_file, json_encode($tempArray));
			#file_put_contents('history.json', json_encode($recent_changes_dict).PHP_EOL, FILE_APPEND | LOCK_EX);	
		} else { #Already notified, nothing to do
			write_log($user->user_name.' has already been notified for reaching '.$current_pca);
		}
	}
}
#Get all clubs
$clubs = json_decode(file_get_contents('http://wedro.online/pca.php'), true);
$last_notification_file = '../last_notification.json';
$last_notification_dict = array();
if (file_exists($last_notification_file)) {
	$last_notification_dict = json_decode(file_get_contents($last_notification_file), true);
	write_log("Loaded last notification info from file");
} else {
	write_log("Last notification info file could not be found");
}

#Get users
$access_token = "-1";
if (file_exists('../access_token')) {
	$access_token = file_get_contents('../access_token');
	if ($access_token == False) {
		write_log("Re-authenticating");
		header('Location: http://wedro.online/pnutauth.php');
	}
} else {
	write_log("Re-authenticating");
	header('Location: http://wedro.online/pnutauth.php');
}
$api = new API;
$api->access_token = $access_token;
$api->init();
$messages = $api->get_messages($clubs);
$followers = $api->get_bot_followers();
#Check and notify. Also build notification dict
foreach ($followers as $user) {
	if ($last_notification_dict != null && array_key_exists($user->user_name, $last_notification_dict)) {
		write_log($user->user_name.' has been notified in the past. Last time when reaching: '.$last_notification_dict[$user->user_name]);
		$user->last_club_notification = $last_notification_dict[$user->user_name];
	} else {
		write_log($user->user_name.' has never been notified in the past');
	}
	$api->notify_user($user, $clubs);
	if ($user->last_club_notification != "") {
		$last_notification_dict[$user->user_name] = $user->last_club_notification;
	} else {
		write_log($user->user_name.' will not be saved, as they have not reached a club yet');
	}
}
#Save changes
$f = fopen($last_notification_file, 'w');
fwrite($f, json_encode($last_notification_dict));
fclose($f);

?>