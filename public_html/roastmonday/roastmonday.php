<style>
img{
    width:100%;
    max-width:300px;
}
</style>
<?php

function write_log($string, $level="INFO") {
	file_put_contents('logs/'.date('Y-m-d').'.log', '['.$level.'] '.date('H:i:s').' '.$string.PHP_EOL, FILE_APPEND | LOCK_EX);	
}

$filter = "isJson";
$files = array_filter(scandir('.'), $filter);
$image_dir = 'img';

if (!isset($_GET['theme'])) {
	echo 'Thememondays:<br />';
	echo '<ul>';
	foreach ($files as $file) {
		echo '<li><a href="?theme='.$file.'">#'.explode('.', $file)[0].'</a></li>';
	}
	echo '</ul>';
	die();
}
$view_theme = $_GET['theme'];
$participants = json_decode(file_get_contents($view_theme));
$tag = explode('.', $view_theme)[0];
echo '<a href="?">Overview</a><hr>';
echo '<a href="https://beta.pnut.io/tags/'.$tag.'">#'.$tag.'</a><table>';
$ids = array();
foreach ($participants as $user) {
	$ids[] = $user->post_id;
}
write_log('Fetching posts: '.implode(',', $ids));
$r = json_decode(file_get_contents('https://api.pnut.io/v0/posts?ids='.implode(',', $ids)));
if ($r->meta->code != 200) {
	write_log('Error fetching posts ('.implode(',', $ids).'): '.json_encode($r->meta), "ERROR");
	return;
}
$posts = $r->data;
if (count($ids) != count($posts)) {
	write_log('Error: Found '.count($posts).' new avatar posts, but requested'.count($ids), 'ERROR');
}
$idx = 0;
foreach ($participants as $user) {
	echo '<tr>';
	echo '<td><a href="https://pnut.io/@'.$user->username.'">@'.$user->username.'</a></td>';
	$img = $image_dir.'/'.$user->pic_file;
	if (!file_exists($img)) {
		$img = $user->pic_url;
	}
	echo '<td><img src='.$img.'></td>';
	echo '<td><a href="https://beta.pnut.io/@'.$user->username.'/posts/'.$user->post_id.'">#'.$user->post_id.'</a><br />'.$posts[$idx]->content->html.'</td>';
	echo '</tr>';
	$idx++;
}
echo '</table>';

function isJson($var) {
	return endsWith($var, ".json");
}

function endsWith($haystack, $needle) {
    $length = strlen($needle);

    return $length === 0 || 
    (substr($haystack, -$length) === $needle);
}

?>