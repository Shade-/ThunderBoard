<?php

// --- Settings ---
$l['thunderboard'] = 'ThunderBoard';
	
$l['thunderboard_settings_title'] = 'ThunderBoard settings';
$l['thunderboard_settings_description'] = 'This section allows you to tune the page speed enhancements applied by ThunderBoard.';

$l['thunderboard_settings_use_minifier'] = 'Minify resources';
$l['thunderboard_settings_use_minifier_desc'] = 'Enable this option to automatically minify JavaScripts and stylesheets. This saves both bandwidth and download time, resulting in slightly faster page loads. Stylesheets are also cached into your <b>/cache/thunderboard</b> folder. In the future, old cache files will be deleted automatically.';

$l['thunderboard_settings_use_pjax'] = 'PJAX';
$l['thunderboard_settings_use_pjax_desc'] = 'Enable this option to use AJAX through the whole site. A YouTube-like progress bar will be added to indicate progress. This is highly recommended as it\'s the most important enhancement applied by ThunderBoard.';

$l['thunderboard_settings_use_image_lazy_loader'] = 'Lazy load images';
$l['thunderboard_settings_use_image_lazy_loader_desc'] = 'Enable this option to lazy load images. Every image will be replaced by a <div> HTML tag and will be displayed using JavaScript, which is loaded asynchronously by default. This option drastically reduces HTTP queries on startup and makes pages load faster; however, images may appear with a small delay when loaded the very first time.';

$l['thunderboard_settings_pjax_timeout'] = 'PJAX timeout';
$l['thunderboard_settings_pjax_timeout_desc'] = 'This indicates how many millseconds PJAX should wait before performing a hard refresh of the page, in milliseconds. Set to higher values if your server is not very fast and hangs up frequently.';

$l['thunderboard_settings_usergroups_allowed'] = 'Usergroups allowed';
$l['thunderboard_settings_usergroups_allowed_desc'] = 'Select the usergroups allowed to see the benefits of ThunderBoard. This is useful to restrict ThunderBoard\'s usage to certain usergroups and let them test beta versions of ThunderBoard.';

$l['thunderboard_settings_merge_stylesheets'] = 'Merge stylesheets';
$l['thunderboard_settings_merge_stylesheets_desc'] = 'If this option is enabled, global stylesheets will be merged into a single, large file. This should ensure faster load times, since stylesheets are usually not downloaded in parallel.';


// --- Admin module ---
// New template
$l['thunderboard_new'] = 'New template';
$l['thunderboard_create'] = 'Create';
$l['thunderboard_new_title'] = 'Page';
$l['thunderboard_new_title_desc'] = 'Enter the page URL you would like to be previewed with this template. This field is case-insensitive. Separate multiple pages with a coma.';
$l['thunderboard_new_template'] = 'Template';
$l['thunderboard_new_template_desc'] = 'Enter the HTML you wish to display as a preview. Use the {.FIELD} syntax to reference to an element with the data-FIELD attribute or with the FIELD classname. Read <u><b><a href="https://www.mybboost.com/thread-thunderboard-beta-4">the documentation</a></b></u> for further reference.';

// Edit template
$l['thunderboard_edit'] = 'Edit template';
$l['thunderboard_edit_button'] = 'Edit';

// Overview
$l['thunderboard_templates'] = 'Templates';
$l['thunderboard_templates_desc'] = 'Manage ThunderBoard\'s preview templates. A template is a pure HTML snippet which will be used to display a preview when switching to that particular page before it gets populated with the real data. This gives your site a neat look and tricks users into thinking the page has already arrived, thus reducing the perceived page load speed.';
$l['thunderboard_templates_page'] = 'Page';
$l['thunderboard_templates_last_edited'] = 'Last edited';
$l['thunderboard_templates_controls'] = 'Controls';
$l['thunderboard_edit_template'] = 'Edit';
$l['thunderboard_delete_template'] = 'Delete';

// Rebuild cache
$l['thunderboard_rebuild_cache'] = 'Rebuild global variables';
$l['thunderboard_rebuild_cache_desc'] = 'ThunderBoard will gather all your templates and rebuild global variables from scripts. The process may take a while.';

// Reload scripts
$l['thunderboard_reload_rules'] = 'Reload scripts rules';
$l['thunderboard_reload_rules_desc'] = 'Manage reload rules for scripts. Some scripts may need to be added to this list in order to work across page requests: add them here to initialize them upon every page request.';
$l['thunderboard_reload_rules_scripts'] = 'Rules';
$l['thunderboard_reload_rules_scripts_desc'] = 'Manage all rules at once. Separate rules with a newline. The syntax should be: <b>{file}</b>=<b>{function}</b>, for example: <b>inline_moderation</b>=<b>inlineModeration.init()</b>. Functions are case-sensitive.';
$l['thunderboard_reload_rules_header_file'] = 'File';
$l['thunderboard_reload_rules_header_function'] = 'Function';
$l['thunderboard_reload_rules_header_status'] = 'Status';
$l['thunderboard_reload_rules_status'] = 'Rules status';
$l['thunderboard_reload_rules_status_hard'] = '<img src="../images/icons/information.png" /> This file is expected to be reloaded entirely';
$l['thunderboard_reload_rules_status_valid'] = '<img src="../images/valid.png" /> This function is present in the corresponding file';
$l['thunderboard_reload_rules_status_invalid'] = '<img src="../images/invalid.png" /> This function has not been found, this might not work!';

// Cache buster
$l['thunderboard_buster'] = 'Cache buster';
$l['thunderboard_buster_desc'] = 'ThunderBoard writes minified versions of scripts and stylesheets and serves them statically to enhance performance. Do you really want to wipe ThunderBoard\'s cache? It will be rebuilt from scratch the first time a resource is requested.';
$l['thunderboard_buster_all_files_deleted'] = 'The cache has been wiped successfully.';

// Messages
$l['thunderboard_error_no_previews_active'] = 'Currently there are no preview templates set.';

$l['thunderboard_error_need_to_update'] = "You seem to have currently installed an outdated version of ThunderBoard. Please <a href=\"index.php?module=config-settings&update=thunderboard\">click here</a> to run the upgrade script.";
$l['thunderboard_error_nothing_to_do_here'] = "Ooops, ThunderBoard is already up to date! Nothing to do here...";
$l['thunderboard_success_updated'] = "ThunderBoard has been updated correctly from version {1} to {2}. Good job!";
$l['thunderboard_success_created'] = 'You have successfully created a template for this page.';
$l['thunderboard_success_edited'] = 'You have successfully edited this page\'s template.';
$l['thunderboard_success_edited_reload_rules'] = 'You have successfully edited the reload rules for scripts.';
$l['thunderboard_success_deleted'] = 'You have successfully deleted this page\'s template.';
$l['thunderboard_success_cache_rebuilt'] = 'ThunderBoard has successfully rebuilt its template cache.';
