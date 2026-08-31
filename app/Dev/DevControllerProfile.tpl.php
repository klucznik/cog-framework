<?php declare(strict_types=1);

use Cog\BaseApplication;
use Cog\Database\Database;

/** @var int $databaseIndex */
/** @var string $referrer */
/** @var array $profileArray */
/** @var int $count */
/** @var float $totalTime */
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<title>Development Framework - Database Profiling Tool</title>
	<style>
		html { height: 100%; }
		body {
			font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif, "Apple Color Emoji", "Segoe UI Emoji", "Segoe UI Symbol";
			font-size: 16px;
			margin: 0;
			line-height: 1.7;
		}

		#content { padding: 15px 20px 0 20px; margin-bottom: 20px; }

		#headerContainer {
			overflow: hidden;
			padding-bottom: 5px;
			width: 100%;
		}
		#headerBorder { border-bottom: 1px solid #35544b; }
		#header {
			background-color: #45645b;
			color: #ffffff;
			border-top: 1px solid #35544b;
			border-bottom: 1px solid #648a80;
			font-size: 16px;
			overflow: hidden;
			height: 100%;
			text-shadow: 1px 1px 2px rgba(0, 50, 11, 0.7);
		}
		#header #hleft {
			float: left;
			padding: 10px 0 10px 20px;
			line-height: 24px;
		}
		#header .hbig {
			font-size: 21px;
			font-weight: bold;
		}
		#header .hsmall { font-size: 14px; margin-bottom: 4px; display: inline-block; }
		#header #hright {
			float: right;
			font-size: 14px;
			padding: 10px 10px 10px 0;
			text-align: right;
		}

		.title {
			font-size: 18px;
			font-weight: bold;
		}

		pre {
			font-family: 'Lucida Console', 'Courier New', Courier, monospaced;
			font-size: 14px;
			line-height: 16px;
			margin: 12px 0;
			display: inline-block;
		}
		pre[hidden] { display: none; }

		pre code {
			font-size: 13px;
			margin: 0;
			padding: 10px;
			display: block;

			background-color: #f0f0f0;
			border: 1px solid #d8d8d8;
			box-shadow: 0 0 3px rgba(0, 0, 0, 0.15);
		}

		.function {
			font-weight: bold;
		}

		.function_details {
			color: #444;
			font-size: 15px;
		}

		.function_details.slow {
			color: #F00;
		}
		.function_details.sluggish {
			color: #ffd324;
		}

		.smallbutton {
			border: 1px solid #aaa;
			background-color: #f6f6f6;
			color: #000;
			padding: 2px 5px;
			font-family: inherit;
			font-size: 15px;
			font-weight: 400;
			line-height: inherit;
			cursor: pointer;
		}
		.smallbutton:hover { background-color: #c1e2ac; border-color: #69ca32; outline: 0; }

		.clear {
			clear: both;
			font-size: 0;
		}

		.query {
			padding: 16px 0;
			border-top: 1px solid #d8d8d8;
		}
	</style>
</head>
<body>
	<div id="headerContainer">
		<div id="headerBorder">
			<div id="header">
				<div id="hleft">
					<span class="hsmall">Development Framework <?= BaseApplication::FRAMEWORK_VERSION ?></span><br/>
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
		<button type="button" class="smallbutton" data-toggle-all="show">Show All</button>
		<button type="button" class="smallbutton" data-toggle-all="hide">Hide All</button>
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
					<button type="button" class="smallbutton" data-toggle="query<?= $index ?>">Show</button>
				</div>
				<div class="function_details">
					<b>File: </b><?= htmlspecialchars((string) $file) ?>; &nbsp;&nbsp;<b>Line: </b><?= htmlspecialchars((string) $line) ?>
				</div>
				<pre id="query<?= $index ?>" hidden><code><?= htmlspecialchars((string) $query) ?></code></pre>

				<?php if ($time) { ?>
				<div class="function_details<?= $time >= 5 ? ($time >= 10 ? ' slow' : ' sluggish') : '' ?>">
					<b>Time:</b> <?= $time ?>ms
				</div>
				<?php } ?>
			</div>
<?php } ?>
	</div>

	<script>
		const setQueryVisible = (pre, visible) => {
			pre.hidden = !visible;

			const button = document.querySelector(`[data-toggle="${pre.id}"]`);
			if (button) {
				button.textContent = visible ? 'Hide' : 'Show';
			}
		};

		const setAllQueriesVisible = (visible) => {
			for (const pre of document.querySelectorAll('.query pre')) {
				setQueryVisible(pre, visible);
			}
		};

		document.addEventListener('click', (event) => {
			const toggle = event.target.closest('[data-toggle]');
			if (toggle) {
				const pre = document.getElementById(toggle.dataset.toggle);
				setQueryVisible(pre, pre.hidden);
				return;
			}

			const toggleAll = event.target.closest('[data-toggle-all]');
			if (toggleAll) {
				setAllQueriesVisible(toggleAll.dataset.toggleAll === 'show');
			}
		});

		<?php if ($count <= 5) { ?>
		setAllQueriesVisible(true);
		<?php } ?>
	</script>
</body>
</html>
