<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8" />
	<title>Pnut.io PCA progression history</title>
	<link rel="stylesheet" href="styles/style_index.css" />
</head>
<body>
	<p>Recent PCA progressions:</p>
	<?php
	$history_file = 'history.json';
	if (!file_exists($history_file)) {
		echo "None :( </body></html>";
		die();
	}
	$recent_changes = json_decode(file_get_contents($history_file), true);
	if (count($recent_changes) == 0) {
		echo "None :( </body></html>";
		die();
	}
	?>
	<table>
		<tbody>
			<tr>
				<th>User</th>
				<th>PCA</th>
				<th>Post ID</th>
				<th>Date</th>
			</tr>
			<?php
			foreach ($recent_changes as $entry) {
				$user = '<a href="https://pnut.io/'.$entry['user'].'">'.$entry['user'].'</a>';
				$pca = $entry['pca'];
				$datetime = new DateTime($entry['date']);
				$date = $datetime->format('Y-m-d H:i:s P');
				$post_id = '<a href="https://posts.pnut.io/'.$entry['post_id'].'">'.$entry['post_id'].'</a>';
				echo '<tr><td>'.$user.'</td><td>'.$pca.'</td><td>'.$post_id.'</td><td>'.$date.'</td></tr>';
			}
			?>
		</tbody>
	</table>
</body>
</html>