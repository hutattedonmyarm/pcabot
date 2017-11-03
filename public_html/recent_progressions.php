<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8" />
	<title>Pnut.io PCA progression history</title>
	<link rel="stylesheet" href="styles/style_index.css" />
	<script>
		var startRow;
		var tableID = "progressionTable"
		var filterStyles = ["str", "<=", ">="];
		var filterStyleIdx = 0;

		function init() {
			var numTH = document.getElementsByTagName('th').length;
			startRow = (document.getElementsByTagName('tr').length * numTH) / (document.getElementsByTagName('td').length + numTH);
			sortTable(3);
		}

		function isDate(d) {
			return (d !== "Invalid Date") && !isNaN(d);
		}

		function changeIdFilterStyle(sender) {
			//console.log(sender);
			filterStyleIdx++;
			if (filterStyleIdx >= filterStyles.length) {
				filterStyleIdx = 0;
			}
			sender.textContent = filterStyles[filterStyleIdx];
			if (document.getElementById('filterPost').value != "") {
				filter();
			}
		}

		function sortTable(n) {
			var rows, switching, i, x, y, shouldSwitch, dir, switchcount = 0;
			var table = document.getElementById(tableID);
			switching = true;
			dir = "asc";
			while (switching) {
				switching = false;
				rows = table.getElementsByTagName("tr");
				for (i = startRow; i < (rows.length - 1); i++) {
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

		function filter() {
			var table = document.getElementById(tableID);
			
			//Get values to filter for
			var filterUser = document.getElementById('filterUser').value.toUpperCase();
			var filterPCA = document.getElementById('filterPCA').value.toUpperCase();
			var filterPost = document.getElementById('filterPost').value;
			var filterDateBefore = document.getElementById('filterDateBefore').value;
			var filterDateAfter = document.getElementById('filterDateAfter').value;
			
			//Only filter if entered dates are valid
			var filterBefore = new Date(filterDateBefore);
			var filterAfter = new Date(filterDateAfter);
			var doFilterBefore = isDate(filterBefore);
			var doFilterAfter = isDate(filterAfter);

			var filterArray = [filterUser, filterPCA, filterPost];
			var tr = table.getElementsByTagName("tr");
			var tableData, i, j, userData, showRow, postIdString, postID;
			var filterStyle = filterStyles[filterStyleIdx];
			//Loop through every row, starting with 
			for (i = startRow; i < tr.length; i++) {
    			tableData = tr[i].getElementsByTagName("td");
    			showRow = true;
    			postIdString = tableData[2].children[0].innerHTML;
    			postID = parseInt(postIdString);
    			switch(filterStyle) {
    				case "str":
    					showRow = showRow && ((postIdString.toUpperCase().indexOf(filterPost) > -1));
    					break;
    				case "<=":
    					showRow = showRow && !isNaN(postID) && postID <= filterPost;
    					break;
    				case ">=":
    					showRow = showRow && !isNaN(postID) && postID >= filterPost;
    					break;
    			}
	  			for (j = 0; j < tableData.length - 2; j++) {
	  				showRow = showRow && ((tableData[j].innerHTML.toUpperCase().indexOf(filterArray[j]) > -1));
	  			}
	  			var postDate = new Date(tableData[tableData.length - 1].innerHTML.split(' ')[0]);
	  			var doFilterDate = doFilterBefore || doFilterAfter;
	  			var filterBeforeHit = !doFilterBefore || (doFilterBefore && postDate <= filterBefore)
	  			var filterAfterHit = !doFilterAfter || (doFilterAfter && postDate >= filterAfter)
	  			showRow = showRow && isDate(postDate) && (!doFilterDate || (doFilterDate && filterBeforeHit && filterAfterHit));
	  			tr[i].style.display = showRow ? "" : "none";
  			}
		}
	</script>
</head>
<body onload="init()">
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
			<tr>
				<th><input type="text" id="filterUser" onpaste="filter()" onkeyup="filter()" placeholder="Filter User"></th>
				<th><input type="text" id="filterPCA" onpaste="filter()" onkeyup="filter()" placeholder="Filter PCA"></th>
				<th>
					<button onclick="changeIdFilterStyle(this)" title="Filter post ID by&#013;str: String&#013;<= and >=: Numeric">str</button>
					<input type="text" id="filterPost" onpaste="filter()" onkeyup="filter()" placeholder="Filter Post ID" style="width: 60%">
				</th>
				<th>
					<input type="date" id="filterDateBefore" onkeyup="filter()" placeholder="Posts on/before" style=" width:45%; margin-right: 1%">
					<input type="date" id="filterDateAfter" onkeyup="filter()" placeholder="Posts on/after" style=" width: 45%; margin-left: 1%">
				</th>
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