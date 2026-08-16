<?php require __DIR__ . '/../../../../prepend.inc.php';

use Cog\Database\Database;
use Cog\Type;

//Exit gracefully if called directly or profiling data is missing.
if (!isset($_POST['databaseIndex'], $_POST['profileData'], $_POST['referrer'])) {
	exit('Nothing to profile. No Database Profiling data received.');
}

$databaseIndex = (int) $_POST['databaseIndex'];
$profileData = $_POST['profileData'];
$referrer = $_POST['referrer'];

$profileArray = json_decode(base64_decode($profileData), true);
$profileArray = Type::cast($profileArray, Type::ARRAY);
$count = count($profileArray);

$totalTime = 0;
foreach($profileArray as $index => $profile) {
	if (isset($profile['timeInfo']['total time'])) {
		$totalTime += floatval($profile['timeInfo']['total time']);
	}
}
?>
<!DOCTYPE html>
<html>
<head>
	<title>Development Framework - Database Profiling Tool</title>
	<style>@import url("/assets/css/corepage.css");</style>
	<script>
		function Toggle(strWhatId, strButtonId) {
			var obj = document.getElementById(strWhatId);
			var button = document.getElementById(strButtonId);

			if (obj && button) {
				if (obj.style.display == "inline-block") {
					obj.style.display = "none";
					button.innerHTML = "Show";
				}
				else {
					obj.style.display = "inline-block";
					button.innerHTML = "Hide";
				}
			}
			return false;
		}

		function ShowAll() {
			for (var i = 1; i <= <?= $count ?>; i++) {
				var query = document.getElementById('query' + i);
				var button = document.getElementById('button' + i);
				query.style.display = "inline-block";
				button.innerHTML = "Hide";
			}
			return false;
		}

		function HideAll() {
			for (var i = 1; i <= <?= $count ?>; i++) {
				var query = document.getElementById('query' + i);
				var button = document.getElementById('button' + i);
				query.style.display = "none";
				button.innerHTML = "Show";
			}
			return false;
		}
	</script>
</head>
<body>
	<div id="container">
		<div id="headerContainer">
			<div id="headerBorder">
				<div id="header">
					<div id="hleft">
						<span class="hsmall">Development Framework <?= \App\CogApplication::FRAMEWORK_VERSION ?></span><br/>
						<span class="hbig">Database Profiling Tool</span>
					</div>
					<div id="hright">
						<b>Database Index:</b> <?= $databaseIndex ?>&nbsp;&nbsp;
						<b>Database Type:</b> <?= Database::$databases[$databaseIndex]->adapter ?><br/>
						<b>Database Server:</b> <?= Database::$databases[$databaseIndex]->server ?>&nbsp;&nbsp;
						<b>Database Name:</b> <?= Database::$databases[$databaseIndex]->database ?><br/>
						<b>Profile Generated From:</b> <?= htmlspecialchars($referrer) ?>
					</div>
					<div class="clear"></div>
				</div>
			</div>
		</div>
	</div>

	<div id="content">
		<span class="title">
<?php
		switch ($count) {
			case 0: echo '<b>There were no queries that were performed.</b>'; break;
			case 1: echo '<b>There was 1 query that was performed.</b>'; break;
			default: printf('<b>There were %s queries that were performed.</b>', $count); break;
		}
?>
			<br/><?= $totalTime * 1000 ?>ms
		</span>
		<br/>
		<br/>
		<a href="#" onClick="return ShowAll()" class="smallbutton">Show All</a>
		<a href="#" onClick="return HideAll()" class="smallbutton">Hide All</a>
		<br/>
		<br/>
<?php
		foreach($profileArray as $index => $profile) {
			$index = intval($index);
			$backtrace = $profile['backtrace'];
			$query = $profile['query'];

			$args = array_key_exists('args', $backtrace) ? $backtrace['args'] : [];
			$class = $backtrace['class'] ?? null;
			$type = $backtrace['type'] ?? null;
			$function = $backtrace['function'] ?? null;
			$file = $backtrace['file'] ?? null;
			$line = $backtrace['line'] ?? null;
			$calledBy = $class . $type . $function . '(' . implode(', ', $args) . ')';
			$time = null;
			if (isset($profile['timeInfo']['total time'])) {
				$time = floatval($profile['timeInfo']['total time']) * 1000;
			}
?>
			<div class="query">
				<div class="function">
					<?= htmlspecialchars($calledBy) ?>
					<a href="#" onClick="return Toggle('query<?= $index ?>', 'button<?= $index ?>')" id="button<?= $index ?>" class="smallbutton">Show</a>
				</div>
				<div class="function_details">
					<b>File: </b><?= htmlspecialchars($file) ?>; &nbsp;&nbsp;<b>Line: </b><?= htmlspecialchars($line) ?>
				</div>
				<pre id="query<?= htmlspecialchars($index) ?>" style="display: none"><code><?= htmlspecialchars($query) ?></code></pre>

				<?php if ($time) { ?>
				<div class="function_details<?= $time >= 5 ? ($time >= 10 ? ' slow' : ' sluggish') : '' ?>">
					<b>Time:</b> <?= $time ?>ms
				</div>
				<?php } ?>
			</div>
<?php } ?>

<?php if ($count <= 5) { ?>
	<script>ShowAll();</script>
<?php } ?>
</body>
</html>
