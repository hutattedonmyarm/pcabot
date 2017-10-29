<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8" />
	<title>@pca_bot's log today</title>
</head>
<body>
	<p>
	<?php
		echo "Server time and date: ".date('Y-m-d H:i:s');
	?>
	</p>
	<code>
		<?php
		$logfile = '../logs/'.date('Y-m-d').'.log';
		if (file_exists($logfile)) {
			echo nl2br(file_get_contents($logfile));
		}
		?>
	</code>
</body>
</html>