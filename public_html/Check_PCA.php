<?php

function write_log($string, $level="INFO") {
	file_put_contents('../logs/'.date('Y-m-d').'.log', '['.$level.'] '.date('H:i:s').' '.$string.PHP_EOL, FILE_APPEND | LOCK_EX);	
}

class User {
   public $user_name;
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
	private const api_endpoint = 'https://api.pnut.io/v0';

	private function get_data($endpoint, $parameters, $method='GET', $contenttype='application/x-www-form-urlencoded') {
		$postdata = http_build_query($parameters);
		$context_options = array (
        'http' => array (
           'method' => $method,
           'header'=> "Content-type: ".$contenttype."\r\n"
                		.'Authorization: Bearer '.$this->access_token,
           'content' => $postdata
        )
    	);
    	$context = stream_context_create($context_options);
    	return json_decode(file_get_contents($endpoint, false, $context), true);
	}

	public function write_post($posttext) {
		$post_endpoint = self::api_endpoint.'/posts';
		$parameters = array('text' => $posttext);
		$this->get_data($post_endpoint, $parameters, 'POST');
	}
	
	public function get_user($user_id) {
		$user_endpoint = self::api_endpoint.'/users/'.$user_id;
		return new User($this->get_data($user_endpoint));
	}

	public function get_bot_followers() {
		$before_id = null;
		$users = array();
		do {
			write_log("Getting followers before id: ".$before_id);
			$followers_endpoint = self::api_endpoint.'/users/me/followers';
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
		$current_pca_dict = $user->get_highest_pca($clubs);
		$current_pca = "";
		if (array_key_exists('pca', $current_pca_dict)) {
			$current_pca = $current_pca_dict['pca'];
		} else {
			return;
		}
		$last_notification = $user->last_club_notification;
		$next_pca = $user->get_next_pca($clubs);
		if ($current_pca != $last_notification) {
			$user->last_club_notification = $current_pca;
			$posttext = "Congratulations ".$user->user_name.", you are now a member of #".preg_replace('/\s+/', '', $current_pca).' ('.$current_pca_dict['post_count'].'+ posts)! '.$current_pca_dict['emoji'].' Next: '.$next_pca['emoji'].' at '.$next_pca['post_count'].' posts';
			$this->write_post($posttext);
			write_log($posttext);
		} else {
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