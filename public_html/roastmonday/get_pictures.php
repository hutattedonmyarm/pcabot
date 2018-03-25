<?php

function write_log($string, $level="INFO") {
	file_put_contents('logs/'.date('Y-m-d').'.log', '['.$level.'] '.date('H:i:s').' '.$string.PHP_EOL, FILE_APPEND | LOCK_EX);	
}

$image_dir = 'img';
$keywords = array('picture', 'avatar');
get_pictures($image_dir, $keywords);

function get_pictures($image_dir, $keywords) {
	write_log('Fetching pictures');
	$r = json_decode(file_get_contents("https://api.pnut.io/v0/posts/tags/thememonday?count=100"));
	if ($r->meta->code != 200) {
		write_log('Error fetching pictures: '.json_encode($r->meta), "ERROR");
		return;
	}
	$posts = $r->data;
	$themetags = array();
	if (!file_exists($image_dir)) {
		mkdir($image_dir);
	}
	foreach ($posts as $post) {
		if (isset($post->content)) {
			$content = $post->content;
			foreach ($post->content->entities->tags as $hashtag) {
				$tag = mb_strtolower($hashtag->text);
				if (strpos($tag, 'monday') !== false && $tag != 'thememonday' && !in_array($tag, $themetags)) {
					$themetags[] = $tag;
				}
			}
		}
	}
	$tag_string = implode(',', $themetags);
	write_log("Found possible thememondays: ".$tag_string);
	$posts = [];
	foreach ($keywords as $keyword) {
		$r = json_decode(file_get_contents("https://api.pnut.io/v0/posts/search?tags=".$tag_string.'&q='.$keyword.'&count=100'));
		if ($r->meta->code != 200) {
			write_log('Error fetching posts for #'.$keyword.': '.json_encode($r->meta), "ERROR");
			continue;
		}
		$posts = array_merge($posts,$r->data);
	}

	foreach ($posts as $post) {
		$user = $post->user;
		if (!isset($user)) {
			continue;
		}
		$content = $user->content;
		if (!isset($content)) {
			continue;
		}
		$user_id = $user->id;
		$post_id = $post->id;
		$username = $user->username;
		$avatar_link = $user->content->avatar_image->link;
		if (!isset($avatar_link)) {
			continue;
		}
		$img_header = get_headers($avatar_link,1);
		if (strpos($img_header[0], "200") === false && trpos($img_header[0], "OK")) {
			write_log("Error fetching avatar header: ".json_encode($img_header),"ERROR");
		}
     	$ext = 'jpg';
     	switch ($img_header['Content-Type']) {
     		case 'image/png':
     			$ext = 'png';
     			break;
     		case 'image/bmp':
     			$ext = 'bmp';
     			break;
     		case 'image/gif':
     			$ext = 'gif';
     			break;
     		case 'image/jpeg':
     		default:
     			$ext = 'jpg';
     			break;
     	}
     	$exp = explode('/', $avatar_link);
		$img_name = array_pop($exp).'.'.$ext;
		if (!file_exists($image_dir.'/'.$img_name)) {
	     	write_log('Fetching avatar from '.$avatar_link.' to '.$image_dir.'/'.$img_name);
	     	$avatar = file_get_contents($avatar_link);
			$fp = fopen($image_dir.'/'.$img_name, "w");
			fwrite($fp, $avatar);
			fclose($fp);	
		} else {
			write_log($image_dir.'/'.$img_name.' already exists. Skipping.');
		}
		$user_dict = array('username' => $username, 'pic_url' => $avatar_link, 'pic_file' => $img_name, 'post_id' => $post_id);
		foreach ($post->content->entities->tags as $tag_obj) {
			$tag = mb_strtolower($tag_obj->text);
			if (!in_array($tag, $themetags)) {
				continue;
			}
			$tag_dict = array();
			$json_file = $tag.'.json';
			if (file_exists($json_file)) {
				$tag_dict = json_decode(file_get_contents($json_file), true);
			}
			if (array_key_exists($user->id, $tag_dict)) {
				write_log('@'.$username.' already has posted a new avatar for #'.$tag.' before. Skipping');
				continue;
			}
			write_log('Saving avatar for #'.$tag.' and user '.$user->id);
			$tag_dict[$user_id] = $user_dict;
			$fp = fopen($json_file, "w");
			fwrite($fp, json_encode($tag_dict));
			fclose($fp);
		}
	}
}

?>