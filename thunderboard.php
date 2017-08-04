<?php
	
define('IN_MYBB', 1);

require_once dirname(__FILE__)."/inc/init.php";

$PL or require_once PLUGINLIBRARY;

$templates = (array) $PL->cache_read('thunderboard_templates');
$output = [];

foreach ($templates as $page => $template) {
	
	$output[] = [
		'page' => $page,
		'template' => $template['template']
	];
	
}

if ($output) {
	echo json_encode($output);
}
else {
	echo json_encode([
		'error' => 'No templates currently set.'
	]);
}

exit;