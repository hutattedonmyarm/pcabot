<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8" />
	<title>Pnut.io PCA progression history</title>
	<link rel="stylesheet" href="styles/style_index.css" />
	<script>
		function sortTable(n) {
			var table, rows, switching, i, x, y, shouldSwitch, dir, switchcount = 0;
			table = document.getElementById("progressionTable");
			switching = true;
			dir = "asc";
			while (switching) {
				switching = false;
				rows = table.getElementsByTagName("tr");
				for (i = 1; i < (rows.length - 1); i++) {
					shouldSwitch = false;
					x = rows[i].getElementsByTagName("td")[n];
					y = rows[i + 1].getElementsByTagName("td")[n];
					if (dir == "asc") {
						if (x.innerHTML.toLowerCase() > y.innerHTML.toLowerCase()) {
							shouldSwitch= true;
							break;
						}
					} else if (dir == "desc") {
						if (x.innerHTML.toLowerCase() < y.innerHTML.toLowerCase()) {
							shouldSwitch= true;
							break;
						}
					}
				}
				if (shouldSwitch) {
					rows[i].parentNode.insertBefore(rows[i + 1], rows[i]);
				  	if (switchcount == 0) {
					  	var headers = Array.from(rows[0].getElementsByTagName("th"));
					  	headers.forEach(function(element) {
					  		element.innerHTML = element.innerHTML.replace('▲', '');
					  		element.innerHTML = element.innerHTML.replace('▼', '');
					  	});
					  	headers[n].innerHTML += dir == 'asc' ? '▲' : '▼';
					}
					switching = true;
					switchcount ++; 
				} else {
					if (switchcount == 0 && dir == "asc") {
				   	dir = "desc";
				   	switching = true;
					}
				}
			}
		}
	</script>
</head>
<body onload="sortTable(3)">
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
	<table id="progressionTable">
		<tbody>
			<tr>
				<th onclick="sortTable(0)">User</th>
				<th onclick="sortTable(1)">PCA</th>
				<th onclick="sortTable(2)">Post ID</th>
				<th onclick="sortTable(3)">Date</th>
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