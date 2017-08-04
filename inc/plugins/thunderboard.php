<?php
/**
 * ThunderBoard
 * 
 * Light-speed page loads for your MyBB forum.
 *
 * @package ThunderBoard
 * @author  Shade <legend_k@live.it>
 * @license MIT
 * @version beta 5
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
		'description'   =>  'Light-speed page loads for your MyBB forum.',
		'website'       =>  'http://www.mybboost.com',
		'author'        =>  'Shade',
		'version'       =>  'beta 5',
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
		'use_pjax' => [
			'title' => $lang->thunderboard_settings_use_pjax,
			'description' => $lang->thunderboard_settings_use_pjax_desc,
			'value' => 1
		],
		'pjax_timeout' => [
			'title' => $lang->thunderboard_settings_pjax_timeout,
			'description' => $lang->thunderboard_settings_pjax_timeout_desc,
			'optionscode' => 'text',
			'value' => '4000'
		],
		'use_minifier' => [
			'title' => $lang->thunderboard_settings_use_minifier,
			'description' => $lang->thunderboard_settings_use_minifier_desc,
			'optionscode' => "checkbox\nstylesheets=Stylesheets\nscripts=Scripts",
			'value' => 'stylesheets,scripts'
		],
		'use_image_lazy_loader' => [
			'title' => $lang->thunderboard_settings_use_image_lazy_loader,
			'description' => $lang->thunderboard_settings_use_image_lazy_loader_desc,
			'value' => 1
		],
		'usergroups_allowed' => [
			'title' => $lang->thunderboard_settings_usergroups_allowed,
			'description' => $lang->thunderboard_settings_usergroups_allowed_desc,
			'optionscode' => 'groupselect',
			'value' => -1
		],
		'merge_stylesheets' => [
			'title' => $lang->thunderboard_settings_merge_stylesheets,
			'description' => $lang->thunderboard_settings_merge_stylesheets_desc,
			'value' => 1
		]
	]);
	
	if (!$db->table_exists('thunderplates')) {
		
		$collation = $db->build_create_table_collation();
		
		$db->write_query("CREATE TABLE " . TABLE_PREFIX . "thunderplates (
			tid int unsigned NOT NULL auto_increment,
			title varchar(120) NOT NULL default '',
			template text NOT NULL,
			sid smallint NOT NULL default '0',
			KEY sid (sid, title),
			PRIMARY KEY (tid)
        ) ENGINE=MyISAM{$collation};");
		
	}
	
	require_once MYBB_ROOT . 'inc/plugins/ThunderBoard/class_core.php';
	$thunderboard->build_thunderplates();
	
	// Add stylesheets
	$stylesheet = file_get_contents(dirname(__FILE__) . '/ThunderBoard/stylesheets/app-loading.css');
	
	$PL->stylesheet('app-loading.css', $stylesheet);
	
	// Add reload rules
	$PL->cache_update('thunderboard_reload_scripts', $reload_rules);
	
	// Add the plugin to cache
    $info = thunderboard_info();
    $shade_plugins = $cache->read('shade_plugins');
    $shade_plugins[$info['name']] = [
        'title' => $info['name'],
        'version' => $info['version']
    ];
    $cache->update('shade_plugins', $shade_plugins);
}

function thunderboard_uninstall()
{
	global $cache, $PL, $db;
	
	$PL or require_once PLUGINLIBRARY;
        
    // Remove settings
    $PL->settings_delete('thunderboard');
    
    // Remove stylesheets
	$PL->stylesheet_delete('app-loading');
	
	// Remove caches
	$PL->cache_delete('thunderboard_reload_scripts');
	$PL->cache_delete('thunderboard_templates');
	
	$db->drop_table('thunderplates');
	
	// Remove the plugin from cache
	$info = thunderboard_info();
    $shade_plugins = $cache->read('shade_plugins');
    unset($shade_plugins[$info['name']]);
    $cache->update('shade_plugins', $shade_plugins);
}

$plugins->add_hook('pre_output_page', 'thunderboard_replace', 1000);
$plugins->add_hook('global_start', 'thunderboard_alter_templates_object', 1000);
$plugins->add_hook('admin_style_templates_edit_template_commit', 'thunderboard_edit_template', 1);

if (defined("IN_ADMINCP")) {

	$plugins->add_hook("admin_page_output_header", "thunderboard_update");
	$plugins->add_hook("admin_config_menu", "thunderboard_admin_config_menu");
	$plugins->add_hook("admin_config_action_handler", "thunderboard_admin_config_action_handler");
	
}

function thunderboard_update()
{
	global $mybb, $cache, $lang, $reload_rules;
	
	$lang->load('thunderboard');
	
	$shade_plugins = $cache->read('shade_plugins');
	$info = thunderboard_info();
	
	$old_version = (float) str_replace('beta ', '0.', $shade_plugins[$info['name']]['version']);
	$new_version = (float) str_replace('beta ', '0.', $info['version']);
	
	if (version_compare($old_version, $new_version, "<") and !$mybb->input['update']) {
		return flash_message($lang->thunderboard_error_need_to_update, "error");
	}
	
	if ($mybb->input['update'] == 'thunderboard') {
		
		$new_settings = $drop_settings = [];
		
		if (version_compare($old_version, '0.5', "<")) {
			
			global $PL;
			
			$PL or require_once PLUGINLIBRARY;
			
			$PL->cache_update('thunderboard_reload_scripts', $reload_rules);
			
			$drop_settings[] = 'thunderboard_automatic_variable_cleanup';
			$drop_settings[] = 'thunderboard_versioning';
			
		}
		
		if ($new_settings) {
			$db->insert_query_multiple('settings', $new_settings);
		}
		
		if ($drop_settings) {
			$db->delete_query('settings', "name IN ('thunderboard_". implode("','thunderboard_", $drop_settings) ."')");
		}
		
		rebuild_settings();
		
		$shade_plugins[$info['name']]['version'] = $info['version'];
		
		$cache->update('shade_plugins', $shade_plugins);
		
		flash_message($lang->sprintf($lang->thunderboard_success_updated, $shade_plugins[$info['name']]['version'], $info['version']), "success");
		admin_redirect($_SERVER['HTTP_REFERER']);
		
	}
}

function thunderboard_edit_template()
{
	global $mybb, $sid, $template, $db;
	
	require_once MYBB_ROOT . 'inc/plugins/ThunderBoard/class_core.php';
	
	$content = [
		'title' => $template['title'],
		'sid' => $sid,
		'template' => rtrim($mybb->input['template'])
	];
						
	$globalScoped = [];
	
	if ($content['template']) {
	
		$thunderboard->load_content($content['template']);
		
		if ($thunderboard->process_global_variables()) {
		
			if ($thunderboard->scripts) {
				
				foreach ($thunderboard->scripts as $script) {
					
					if (is_array($script['globalScoped']) and $script['globalScoped']) {
						$globalScoped = array_merge($globalScoped, $script['globalScoped']);
					}
					
				}
				
			}
			
		}
		
		if ($thunderboard->originalContent) {
			$content['template'] = $thunderboard->originalContent;
		}
		
		if ($globalScoped) {
			$content['template'] .= "\n<script type='text/javascript' data-global='true'>\n" . implode("\n", $globalScoped) . "\n</script>";
		}
		
		if ($content['template']) {
			$content['template'] = $db->escape_string($content['template']);
		}
		
	}
	
	$query = $db->simple_select('thunderplates', 'tid', "title='" . $db->escape_string($template['title']) . "' AND (sid = '-2' OR sid = '{$sid}')", array('order_by' => 'sid', 'order_dir' => 'desc', 'limit' => 1));
	$tid = $db->fetch_field($query, "tid");
	
	// Decide whether to update or insert this template
	if ($sid > 0) {
		
		$query = $db->simple_select('thunderplates', 'sid', "title='" . $db->escape_string($template['title']) . "' AND (sid = '-2' OR sid = '{$sid}' OR sid='{$template['sid']}')", array('order_by' => 'sid', 'order_dir' => 'desc'));
		$existing_sid = $db->fetch_field($query, 'sid');
		$existing_rows = $db->num_rows($query);
		
		if (($existing_sid == -2 and $existing_rows == 1) or $existing_rows == 0) {
			return $db->insert_query('thunderplates', $content);
		}
		else {
			return $db->update_query('thunderplates', $content, "tid = '{$tid}' AND sid != '-2'");
		}
		
	}
	else {
		return $db->update_query('thunderplates', $content, "tid = '{$tid}' AND sid != '-2'");
	}
	
}

function thunderboard_alter_templates_object()
{
	global $templates;
	
	control_object($templates, '
function cache($templates)
{
	global $theme, $db;
	
	$names = explode(",", $templates);
	
	$sql = "";
	
	foreach ($names as $name) {
		
		if (isset($this->cache[$name])) {
			continue;
		}
		
		$sql .= " ,\'".trim($name)."\'";
		
	}

	$query = $db->simple_select("thunderplates", "title,template", "title IN (\'\'$sql) AND sid IN (\'-2\',\'-1\',\'".$theme[\'templateset\']."\')", array(\'order_by\' => \'sid\', \'order_dir\' => \'asc\'));
	while ($template = $db->fetch_array($query)) {
		$this->cache[$template[\'title\']] = $template[\'template\'];
	}
	
	return true;
}

');
	
}

function thunderboard_replace(&$contents)
{
	global $mybb, $cache;
	
	if ($mybb->input['disable'] == 'thunderboard') {
		return false;
	}
	
	// Restrict to user in certain usergroups
	if (!$mybb->settings['thunderboard_usergroups_allowed'] or ($mybb->settings['thunderboard_usergroups_allowed'] != -1 and !in_array($mybb->user['usergroup'], (array) explode(',', (string) $mybb->settings['thunderboard_usergroups_allowed'])))) {
		return false;
	}
	
	$protected = [];
	
	$minifyResources = array_flip((array) explode(',', $mybb->settings['thunderboard_use_minifier']));
	$minifyResources = array_fill_keys(array_keys($minifyResources), 1);
	
	require_once MYBB_ROOT . 'inc/plugins/ThunderBoard/class_core.php';
	
	$dynamic = $thunderboard->is_pjax();
	
	$thunderboard->load_content($contents);
	
	$thunderboard->load_scripts([
		<<<HTML
	
// Remove all attached handlers on body
$('body').off();

var originalWrite = document.write;
document.write = function() { return false }; // Dummy replacement, disables document.write(). This needs a workaround
	
HTML
	], 'inline', false);
	
	$thunderboard->process_scripts();
	$thunderboard->process_images();
	$thunderboard->process_metas();
	$thunderboard->process_stylesheets();
	$thunderboard->load_scripts(['jscripts/pjax.js', 'jscripts/app-loading.js', 'jscripts/thunderboard.js']);
	
	$pjax_timeout = ($mybb->settings['thunderboard_pjax_timeout']) ? (int) $mybb->settings['thunderboard_pjax_timeout'] : 4000;
	
	// Add PJAX and App-loading.js
	if ($mybb->settings['thunderboard_use_pjax']) {

		$thunderboard->load_scripts([
			<<<HTML
	ThunderBoard.timeout = {$pjax_timeout};
	ThunderBoard.init();
HTML
		], 'inline');
		
		if ($mybb->input['thunderboard'] != 'debug') {
		
			// Tidy up the DOM
			$thunderboard->load_scripts([
				<<<HTML
		
		$(document).ready(function() {
			return $('[data-tb-loader]').remove();
		});
				
HTML
			], 'inline', false);
		
		}
	
	}
	
	// Check if we need to reload TB's templates
	$personal_cache = $cache->read('shade_plugins');
	
	if ($personal_cache['ThunderBoard']['templates_last_updated'] > $mybb->cookies['tb-templates']) {
		my_unsetcookie('tb-templates');
	}
			
	require_once MYBB_ROOT . 'min/static/lib.php';
	
	$minifiedGlobalStylesheets = $minifiedSpecificStylesheets = '';
		
	// Minify stylesheets
	if ($minifyResources['stylesheets'] and !in_array($mybb->user['uid'], $protected)) {
		
		if ($mybb->settings['thunderboard_merge_stylesheets']) {
				
			$minifiedGlobalStylesheets = '<link rel="stylesheet" type="text/css" href="' . $mybb->settings['bburl'] . Minify\StaticService\build_uri('/min/static', 'f=' . implode(',', array_unique($thunderboard->stylesheets['global'])), 'css') . '" />';
			
			if ($thunderboard->stylesheets['specific']) {
				$minifiedSpecificStylesheets = '<link rel="stylesheet" type="text/css" href="' . $mybb->settings['bburl'] . Minify\StaticService\build_uri('/min/static', 'f=' . implode(',', array_unique($thunderboard->stylesheets['specific'])), 'css') . '" />';
			}
			
		}
		
	}
	else {
		
		foreach ($thunderboard->stylesheets['global'] as $css) {
			$minifiedGlobalStylesheets .= "<link rel='stylesheet' type='text/css' href='{$css}' />\n";
		}
		
		if ($thunderboard->stylesheets['specific']) {
			
			foreach ($thunderboard->stylesheets['specific'] as $css) {
				$minifiedSpecificStylesheets .= "<link rel='stylesheet' type='text/css' href='{$css}' />\n";
			}
			
		}
		
	}
	
	// Add eventual scripts to reload
	if ($mybb->settings['thunderboard_use_pjax']) {
		
		if ($thunderboard->scripts['reload'] and $dynamic) {
			$thunderboard->load_scripts([implode(PHP_EOL, $thunderboard->scripts['reload'])], 'inline', false);
		}
		
	}
			
	// Convert refresh metas into JS based refreshes
	if ($thunderboard->metas) {
		
		foreach ($thunderboard->metas as $meta) {
			
			$pieces = explode(';', $meta);
			
			$time = (int) $pieces[0] * 1000;
			$url = trim(str_ireplace('url=', '', $pieces[1]));
			
			$url = (strpos($url, $mybb->settings['bburl']) === false) ? $mybb->settings['bburl'] . '/' . $url : $url;
			
			$thunderboard->load_scripts([
				<<<HTML
	
	// Loading it into ThunderBoard's object, will be cleared when changing page to ensure the page doesn't keep on
	// refreshing even if the page has changed
	ThunderBoard.reloadTimeout = setTimeout(function() {
		
		// Append a dummy element
		$('body').append('<a href="$url" id="thunderboard-reload"></a>');
		
		// Simulate a click to trigger a PJAX refresh
		$('#thunderboard-reload').click();
		
	}, $time);
				
HTML
			], 'inline');
			
		}
		
	}
	
	// Add scripts to LAB.js to load them asynchronously
	$labscripts = '$LAB.setOptions({AlwaysPreserveOrder:true, AllowDuplicates:true})';
	
	foreach ($thunderboard->scripts as $script) {
		
		if ($script['external']) {
			
			if ($minifyResources['scripts'] and strpos($script['external'], 'http') === false) {
				$uri = $mybb->settings['bburl'] . Minify\StaticService\build_uri('/min/static', 'f=' . $script['external'], 'js');
			}
			else {
				$uri = $mybb->settings['bburl'] . $script['external'];
			}
			
			// If this script needs reloading, delete it from our cache and from the DOM
			$reload_extra = (!$script['reload']) ? '' : "
	var index = thunderboard_loaded.indexOf('{$uri}');
	if (index > -1) thunderboard_loaded.splice(index, 1);
	
	var elem = document.querySelector(\"script[src*='{$uri}']\");
	if (elem) elem.remove();
";
				
			$labscripts .= ".script(() => {{$reload_extra}
	if (thunderboard_loaded.indexOf('{$uri}') == -1) {
		thunderboard_loaded.push('{$uri}');
		return '{$uri}';
	}
})";
	
		}
		else if ($script['inline']) {
			$labscripts .= ".wait(() => {\n" . $script['inline'] . "\n})";
		}

	}
	
	$labscripts .= ';';
	$LABMain = !$dynamic ? '<script type="text/javascript" src="jscripts/LAB.min.js"></script>' : '';
	
	$labjs = <<<HTML
$LABMain
<script type="text/javascript" data-tb-loader>

if (typeof thunderboard_loaded === 'undefined') {
	thunderboard_loaded = [];
}

$labscripts</script>
HTML;

	$enclose = [];
	
	if ($mybb->settings['thunderboard_use_pjax'] && !$dynamic) {
		$enclose['start'] = '<div id="thunderboard-container">';
		$enclose['end']   = '</div>';
	}

	// Finally...
	$contents = str_replace(['<body>', '</head>', '</body>'], ['<body>' . $enclose['start'] . $minifiedSpecificStylesheets . $labjs, $minifiedGlobalStylesheets . '</head>', $enclose['end'] . '</body>'], $thunderboard->dom->saveHTML());
	
	// Enable PJAX for valid requests
	if ($mybb->settings['thunderboard_use_pjax']) {
		
		if ($dynamic) {
			
			$hash = ($mybb->input['pid']) ? '#pid' . (int) $mybb->input['pid'] : '';
			
			// Fix for header: location manipulations
			header("X-PJAX-URL: http" . (isset($_SERVER['HTTPS']) ? 's' : '') . '://' . "{$_SERVER['HTTP_HOST']}{$_SERVER['REQUEST_URI']}{$hash}");
			
			return $thunderboard->extract($contents);
			
		}
		
	}
	
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

// ZiNgA BuRgA's control_object
if(!function_exists('control_object')) {
	function control_object(&$obj, $code) {
		static $cnt = 0;
		$newname = '_objcont_'.(++$cnt);
		$objserial = serialize($obj);
		$classname = get_class($obj);
		$checkstr = 'O:'.strlen($classname).':"'.$classname.'":';
		$checkstr_len = strlen($checkstr);
		if(substr($objserial, 0, $checkstr_len) == $checkstr) {
			$vars = array();
			// grab resources/object etc, stripping scope info from keys
			foreach((array)$obj as $k => $v) {
				if($p = strrpos($k, "\0"))
					$k = substr($k, $p+1);
				$vars[$k] = $v;
			}
			if(!empty($vars))
				$code .= '
					function ___setvars(&$a) {
						foreach($a as $k => &$v)
							$this->$k = $v;
					}
				';
			eval('class '.$newname.' extends '.$classname.' {'.$code.'}');
			$obj = unserialize('O:'.strlen($newname).':"'.$newname.'":'.substr($objserial, $checkstr_len));
			if(!empty($vars))
				$obj->___setvars($vars);
		}
		// else not a valid object or PHP serialize has changed
	}
}