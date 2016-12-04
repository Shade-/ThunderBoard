<?php
	
// Settings title
$l['thunderboard_settings_title'] = 'ThunderBoard settings';
$l['thunderboard_settings_description'] = 'This section allows you to tune the page speed enhancements applied by ThunderBoard.';

// Settings
$l['thunderboard_settings_use_minifier'] = 'Resource minifier';
$l['thunderboard_settings_use_minifier_desc'] = 'Enable this option to automatically minify JavaScripts and stylesheets. This saves both bandwidth and download time, resulting in slightly faster page loads. In this version of ThunderBoard, stylesheets are minified by default no matter what this option is set to. Also, stylesheets are combined into a single, large file to reduce HTTP queries and it\'s cached into your <b>/cache/thunderboard</b> folder. In the future, old cache files will be deleted automatically.';

$l['thunderboard_settings_use_pjax'] = 'PJAX';
$l['thunderboard_settings_use_pjax_desc'] = 'Enable this option to use AJAX through the whole site. A spinner will be added to indicate progress. This option is highly recommended as it\'s the most important enhancement applied by ThunderBoard: when this option is enabled pages load faster because heavy resources like JavaScripts and stylesheets remain loaded in the page.';

$l['thunderboard_settings_use_image_lazy_loader'] = 'Lazy load images';
$l['thunderboard_settings_use_image_lazy_loader_desc'] = 'Enable this option to lazy load images. Every image will be replaced by a <span> HTML tag and will be loaded using JavaScript, which is loaded asynchronously by default. This option drastically reduces HTTP queries on startup and makes pages load faster; however, images may appear with a small delay when they are loaded the very first time.';

$l['thunderboard_settings_pjax_timeout'] = 'Pjax timeout';
$l['thunderboard_settings_pjax_timeout_desc'] = 'Choose how many milliseconds PJAX should wait before performing a hard refresh of the page. Set to higher values if your server is not very fast and hangs up frequently.';

$l['thunderboard_settings_usergroups_allowed'] = 'Usergroups allowed';
$l['thunderboard_settings_usergroups_allowed_desc'] = 'Select the usergroups allowed to see the benefits of ThunderBoard. Leave empty to let everyone. This is useful to restrict ThunderBoard\'s usage to certain usergroups to test beta versions of the plugin.';

$l['thunderboard_settings_automatic_variable_cleanup'] = '[Experimental] Automatic variables cleanup';
$l['thunderboard_settings_automatic_variable_cleanup_desc'] = 'Enable this option to let ThunderBoard automatically process global scope JavaScript variables. This feature might potentially solve conflicts with scripts which require an external variable to be set in order to work. Several MyBB scripts follow this logic. <b>This feature is experimental and might generate even more conflicts or problems.</b> If you want to use ThunderBoard on production, it is recommended to <u><b><a href="http://www.mybboost.com/thread-thunderboard-beta-3">manually do the required edits</a></b></u> and disable this option.';

$l['thunderboard_settings_spinner_delay'] = 'Spinner delay';
$l['thunderboard_settings_spinner_delay_desc'] = 'Choose how many milliseconds should pass between the user\'s click on a link and the appearance of the loading spinner. Set to 0 to show the spinner immediately after the click. Usually, a page should not require more than 500 milliseconds to load, so if you set this to 500 and the page is downloaded before, the content will be replaced without displaying the loading spinner.';