<?php
/**
 * ThunderBoard
 *
 * AJAXify the whole board and speed up page loads.
 *
 * @package ThunderBoard
 * @author  Shade <shade-@outlook.com>
 * @license MIT
 * @version beta 6
 */

if (!defined('IN_MYBB')) {
	die('Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.');
}

if (!defined("PLUGINLIBRARY")) {
	define("PLUGINLIBRARY", MYBB_ROOT."inc/plugins/pluginlibrary.php");
}

$GLOBALS['reload_rules'] = [
    'general' => 'MyBB.init()',
    'inline_edit' => 'inlineEditor.init()',
    'inline_moderation' => 'inlineModeration.init()',
    'post' => 'Post.init()',
    'rating' => 'Rating.init()',
    'report' => 'Report.init()',
    'thread' => 'Thread.init()'
];

function thunderboard_info()
{
	return [
		'name'          =>  'ThunderBoard',
		'description'   =>  'Speed up same-page changes with TurboLinks.',
		'website'       =>  'https://www.mybboost.com/forum-thunderboard',
		'author'        =>  'Shade',
		'version'       =>  'beta 6',
		'compatibility' =>  '18*',
	];
}

function thunderboard_is_installed()
{
    global $cache;

	$info = thunderboard_info();
    $installed = $cache->read("shade_plugins");
    if ($installed[$info['name']]) {
        return true;
    }
}

function thunderboard_install()
{
	global $mybb, $cache, $lang, $PL, $db, $reload_rules;

	$lang->load('thunderboard');

	$PL or require_once PLUGINLIBRARY;

    // Add settings
    $PL->settings('thunderboard', $lang->thunderboard_settings_title, $lang->thunderboard_settings_description, [
		'usergroups_allowed' => [
			'title' => $lang->thunderboard_settings_usergroups_allowed,
			'description' => $lang->thunderboard_settings_usergroups_allowed_desc,
			'optionscode' => 'groupselect',
			'value' => -1
		]
	]);

    $PL->cache_update('thunderboard_reload_scripts', $reload_rules);

	// Add the plugin to cache
    $info = thunderboard_info();
    $pluginsCache = $cache->read('shade_plugins');
    $pluginsCache[$info['name']] = [
        'title' => $info['name'],
        'version' => $info['version']
    ];
    $cache->update('shade_plugins', $pluginsCache);
}

function thunderboard_uninstall()
{
	global $cache, $PL, $db;

	$PL or require_once PLUGINLIBRARY;

    // Remove settings
    $PL->settings_delete('thunderboard');

	$PL->cache_delete('thunderboard_reload_scripts');

	// Remove the plugin from cache
	$info = thunderboard_info();
    $pluginsCache = $cache->read('shade_plugins');
    unset($pluginsCache[$info['name']]);
    $cache->update('shade_plugins', $pluginsCache);
}

$plugins->add_hook('pre_output_page', 'thunderboard_replace', 1000);

if (defined("IN_ADMINCP")) {

	$plugins->add_hook("admin_config_menu", "thunderboard_admin_config_menu");
	$plugins->add_hook("admin_config_action_handler", "thunderboard_admin_config_action_handler");

}

function thunderboard_replace(&$contents)
{
	if (!in_array(THIS_SCRIPT, ['usercp.php', 'private.php', 'modcp.php', 'forumdisplay.php', 'showthread.php'])) {
    	return false;
	}

	global $mybb, $templates, $cache, $PL;

    $PL or require_once PLUGINLIBRARY;

	$hash = ($mybb->input['pid']) ? '#pid' . (int) $mybb->input['pid'] : '';

	// Fix for header: location manipulations
	header("Turbolinks-Location: http" . (isset($_SERVER['HTTPS']) ? 's' : '') . '://' . "{$_SERVER['HTTP_HOST']}{$_SERVER['REQUEST_URI']}{$hash}");

	// Check if we need to reload templates
	$pluginsCache = $cache->read('shade_plugins');

	if ($pluginsCache['ThunderBoard']['templates_last_updated'] > $mybb->cookies['tb-templates']) {
		my_unsetcookie('tb-templates');
	}

    // Reload scripts?
    $reload = [];

    if ($_SERVER['HTTP_TURBOLINKS_REFERRER']) {

        $scriptsToCheckForReload = (array) $PL->cache_read('thunderboard_reload_scripts');
        foreach ($scriptsToCheckForReload as $file => $function) {

            if (stripos($contents, $file . '.js') !== false) {
                $reload[] = $function . ';';
            }

        }

    }

    if ($reload) {

        $reload = implode("\n\t", $reload);

        $contents = str_replace('</body>', <<<HTML
    <script type="text/javascript">
        {$reload}
    </script>
</body>
HTML
, $contents);

    }

    $contents = str_replace('</head>', <<<HTML
    <script type="text/javascript" src="{$mybb->asset_url}/jscripts/turbolinks.min.js?v=0.6"></script>
    <script type="text/javascript" src="{$mybb->asset_url}/jscripts/thunderboard.min.js?v=0.6"></script>
</head>
HTML
, $contents);

	return $contents;
}

function thunderboard_admin_config_menu($sub_menu)
{
	global $lang;

	if (!$lang->thunderboard) {
		$lang->load('thunderboard');
	}

	$sub_menu[] = [
		"id" => "thunderboard",
		"title" => $lang->thunderboard,
		"link" => "index.php?module=config-thunderboard"
	];

	return $sub_menu;
}

function thunderboard_admin_config_action_handler($actions)
{
	$actions['thunderboard'] = [
		"active" => "thunderboard",
		"file" => "thunderboard.php"
	];

	return $actions;
}
