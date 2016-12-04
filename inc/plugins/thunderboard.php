<?php
/**
 * ThunderBoard
 * 
 * Light-speed page loads for your MyBB forum.
 *
 * @package ThunderBoard
 * @author  Shade <legend_k@live.it>
 * @license MIT
 * @version beta 3
 */
 
if (!defined('IN_MYBB')) {
	die('Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.');
}

if (!defined("PLUGINLIBRARY")) {
	define("PLUGINLIBRARY", MYBB_ROOT."inc/plugins/pluginlibrary.php");
}

function thunderboard_info()
{
	return [
		'name'          =>  'ThunderBoard',
		'description'   =>  'Light-speed page loads for your MyBB forum.',
		'website'       =>  'http://www.mybboost.com',
		'author'        =>  'Shade',
		'version'       =>  'beta 3',
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
	global $mybb, $cache, $lang, $PL;
	
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
			'value' => 1
		],
		'use_image_lazy_loader' => [
			'title' => $lang->thunderboard_settings_use_image_lazy_loader,
			'description' => $lang->thunderboard_settings_use_image_lazy_loader_desc,
			'value' => 1
		],
		'usergroups_allowed' => [
			'title' => $lang->thunderboard_settings_usergroups_allowed,
			'description' => $lang->thunderboard_settings_usergroups_allowed_desc,
			'optionscode' => 'text',
			'value' => ''
		],
		'automatic_variable_cleanup' => [
			'title' => $lang->thunderboard_settings_automatic_variable_cleanup,
			'description' => $lang->thunderboard_settings_automatic_variable_cleanup_desc,
			'value' => 1
		],
		'thunderboard_spinner_delay' => [
			'title' => $lang->thunderboard_settings_spinner_delay,
			'description' => $lang->thunderboard_settings_spinner_delay_desc,
			'optionscode' => 'text',
			'value' => '0'
		],
	]);
	
	// Add stylesheets
	//$stylesheet = file_get_contents(dirname(__FILE__) . '/ThunderBoard/stylesheets/nprogress.css');
	
	//$PL->stylesheet('nprogress.css', $stylesheet);
	
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
	global $cache, $PL;
	
	$PL or require_once PLUGINLIBRARY;
        
    // Remove settings
    $PL->settings_delete('thunderboard');
    
    // Remove stylesheets
	//$PL->stylesheet_delete('nprogress');
	
	// Remove the plugin from cache
	$info = thunderboard_info();
    $shade_plugins = $cache->read('shade_plugins');
    unset($shade_plugins[$info['name']]);
    $cache->update('shade_plugins', $shade_plugins);
}

$plugins->add_hook('pre_output_page', 'thunderboard_replace', 1000);

if (defined("IN_ADMINCP")) {

	$plugins->add_hook("admin_config_settings_change", "thunderboard_settings_saver");
	$plugins->add_hook("admin_formcontainer_output_row", "thunderboard_settings_replacer");
	
}

function thunderboard_replace(&$contents)
{
	global $mybb;
	
	if ($mybb->input['disable'] == 'thunderboard') {
		return false;
	}
	
	// Restrict to user in certain usergroups
	if (!in_array($mybb->user['usergroup'], explode(',', (string) $mybb->settings['thunderboard_usergroups_allowed']))
		and $mybb->settings['thunderboard_usergroups_allowed']) {
		return false;
	}
	
	if ($mybb->settings['thunderboard_use_pjax']) {
		
		require_once MYBB_ROOT . 'inc/plugins/ThunderBoard/Turbo.php';
		$turbo = new Turbo\Turbo;
		
	}
	
	// Remove HTML comments inside scripts (DOMDocument returns wrong values otherwise)
	$contents = preg_replace('#(<script[^<]*?)<!--#is', '$1', $contents);
		
	// Begin!
	$doc = new \DOMDocument;
	
	// Tell libxml NOT to pass warnings/errors caused by malformed HTML to PHP (runs shutdown functions properly)
	libxml_use_internal_errors(true);
	
	$doc->loadHTML($contents);
	
	libxml_clear_errors();
		
	$scripts = $inline_scripts = $globalStylesheets = $globalScopedVariables = $scripts_to_reload = $extra_scripts = [];
	$i = 1;
	
	// Instantiate the duplicates check array
	$scripts[] = [
		'inline' => <<<HTML
		
(function() {

	if (typeof window.thunderboard_loaded === 'undefined') {
		window.thunderboard_loaded = [];
	}
	
	// Remove all attached handlers on body and our custom ones on document
	$('body').off();
	$(document).off('.thunderboard');
	
	// Dummy replacement for document.write, disables document.write(). This needs a workaround
	var originalWrite = document.write;
	document.write = function() { return false };

})();
	
HTML
	];
	
	// Scripts to reload in case they are found
	$scripts_to_check_reload = ['MyBB' => 'general', 'inlineEditor' => 'inline_edit', 'inlineModeration' => 'inline_moderation', 'Post', 'Rating', 'Report', 'Thread'];
		
	$shade = ($mybb->user['uid'] == 1);
	
	// Process scripts
	foreach ($doc->getElementsByTagName("script") as $script) {
		
		$src = $script->getAttribute('src');
		if ($src) {
			
			// Should be reloaded?
			foreach ($scripts_to_check_reload as $function => $string) {
				
				if (stripos($src, $string) !== false) {
					
					$scripts_to_reload[$function] = $string;
					
					break;
					
				}
				
			}
			
			// Add this script to our list
			$scripts[$i]['external'] = str_replace($mybb->settings['bburl'] . '/', '', strtok($src, '?'));
			
		}
		
		$content = $script->nodeValue;
		
		if (trim($content)) {
			
			if ($mybb->settings['thunderboard_automatic_variable_cleanup']) {
					
				require_once 'ThunderBoard/jtokenizer.php';
				
				// Rip this JS code into tiny little pieces and analyze them one by one
				// This approach is more precise than using regexes to exclude parts of the script
				$tokens = j_token_get_all($content);
				
				$graphBracketsLevel = $o = 0;
				$protected = false;
				$tempVariables = $deletionQueue = [];
				
				foreach ($tokens as $token) {
					
					// Go up 1 level
					if ($token[1] == '{') {
						$graphBracketsLevel++;
					}
					
					$tokenName = j_token_name($token[0]);
					
					// Stay in the "top" level (which equals to the "global" scope)
					if ($graphBracketsLevel > 0 and !$protected) {
						continue;
					}
					
					// Begin gathering this variable value
					if ($tokenName == 'J_VAR') {
						$protected = true;
						$o++;
					}
					
					// Add this piece to the variable value
					if ($protected) {
						$tempVariables[$o][] = $token[1];
					}
					
					// This variable is over
					if ($tokenName == ';') {
						$protected = false;
					}
					
					// Go down 1 level
					if ($token[1] == '}') {
						$graphBracketsLevel--;
					}
					
				}
				
				// Rebuild the variables we need to extrapolate
				foreach ($tempVariables as $variable) {
					$globalScopedVariables[] = $deletionQueue[] = 'window.' . implode('', $variable);
				}
				
				// Delete variables
				$content = str_replace($deletionQueue, '', $content);
			
			}
			
			if ($content) {
				$scripts[$i]['inline'] = $content;
			}
			
		}
		
		$scripts_to_remove[] = $script;
		
		$i++;
		
	}
	
	// Remove scripts from the DOM
	foreach ($scripts_to_remove as $script) {
		$script->parentNode->removeChild($script);
	}
	
	$pjax_timeout = ($mybb->settings['thunderboard_pjax_timeout']) ? (int) $mybb->settings['thunderboard_pjax_timeout'] : 4000;
	
	// Add PJAX and Spin.js
	if ($mybb->settings['thunderboard_use_pjax']) {
		
		$scripts[] = [
			'external' => 'jscripts/pjax.js'
		];
		$scripts[] =[ 
			'external' => 'jscripts/spin.js'
		];
		
		//if (!$shade) {
			
		$delay = ($mybb->settings['thunderboard_spinner_delay']) ? (int) $mybb->settings['thunderboard_spinner_delay'] : 0;
		
		// Might be customizable in the future
		$loading_bar = <<<HTML
	
	// Add spinner
	$(document).one('pjax:send', function() {
		
		var timeout = setTimeout(function() {
						
			if (thunderboardFinished == true) {
				return clearTimeout(timeout);
			}
		
			var target = $('#content');
			var win = $(window);
			var headerHeight = $('#header').outerHeight();
			var footerHeight = $('#footer').outerHeight();
			var paddingPlusMargin = (target.outerHeight(true) - target.outerHeight()) + (target.innerHeight() - target.height());
			
			// Empty the target content and set the height to stretch to full height of the viewport
			target.empty().css({
				'min-height': win.height() - headerHeight - footerHeight - paddingPlusMargin,
				'position': 'relative'
			});
			
			// Wipe out other elements added to the body, but do not touch 1) the container 2) eventual scripts
			$('body > *:not(#container):not(script)').remove();
			
			// Always scroll to top (fixes mobile issues)
			window.scroll(0,0);
			
			// Trigger the spin, hiding eventual residues from the last page call
			target.spin(false);
			target.spin('small');
		
		}, {$delay});
		
	});
	
	$(document).one('pjax:end', function() {
		
		// Fix for modals
		$('body').css('overflow', 'visible');
		
	});
	
HTML;
		/*}
		else {
			$scripts[] =[ 
				'external' => 'jscripts/nprogress.js'
			];
			$loading_bar = <<<HTML
	
	// Add fancy loading bar
	NProgress.configure({
		easing: 'ease',
		showSpinner: false
	});
	
	$(document).on('pjax:send', function() {
		
		NProgress.done();
		NProgress.start();
		
	});
	
	$(document).on('pjax:end', function() {
		
		NProgress.done();
		
		// Fix for modals
		$('body').css('overflow', 'visible');
		
	});
	
HTML;
		}*/
		$scripts[] = [
			'inline' => <<<HTML
			
(function() {
	
	// AJAXify the whole site
	if ($.support.pjax) {
		
		$(document).on('click.thunderboard', 'a:not([data-skip]):not([href*="attachment.php"])', function(event) {
			
			thunderboardFinished = false;
			
			$.pjax.click(event, {
				timeout: {$pjax_timeout},
				container: 'body',
				// Create a custom replace handler to accomodate eventual page-specific stylesheets
				replacementHandler: function(context, content) {
					
					var stylesheets = [];
					var indexes = [];
					
					// Gather the stylesheets on the new page
					$.each(content, function(k, v) {
						
						var clone = $(v).clone();
						var href = clone.attr('href');
						
						if (v.rel == 'stylesheet') {
						
							if ($('head').find('link[rel*="style"][href="' + href + '"]').length == 0) {
								stylesheets.push(clone);
							}
						
							indexes.push(k);
							
						}
						
					});
						
					// Remove all the stylesheets from the body (in reverse order, because otherwise we are
					// removing other nodes
					if (indexes.length) {
						
						var i = indexes.length;
						
						while (i--) {
							content.splice(indexes[i], 1);
						}
												
					}
					
					if (stylesheets.length) {
						
						// Append them at once
						$('head').append(stylesheets);
						
						var counter = stylesheets.length;
						
						// Add a "load" handler to all of them
						$.each(stylesheets, function(k, v) {
							
							v.one('load', function() {
								
								counter--;
		
								thunderboardFinished = true;
								
								// If there are no more stylesheets remaining, replace the html
								if (counter == 0) {
									return context.html(content);
								}
								
							});
							
						});
						
					}
					else {
		
						thunderboardFinished = true;
						
						return context.html(content);
						
					}
					
				}
			});
			
		});
	
	}
	
	// AJAXify forms
	$(document).on('submit.thunderboard', 'form:not([data-skip])', function(event) {
		$.pjax.submit(event, 'body');
	});
	
	// Add hidden inputs to forms upon submitting
	$(document).on('click.thunderboard', 'form:not([data-skip]) input[type="submit"]', function(event) {
		$(this).closest('form').append($(this).clone().attr('type', 'hidden'));
	});
	
	{$loading_bar}
	
	// Handle errors as if they were a normal page
	$(document).on('pjax:error', function(event, xhr, textStatus, errorThrown, options) {
	    options.success(xhr.responseText, textStatus, xhr);
	    return false;
	});
	
})();
		
HTML
		];
	
	}
	
	// Process images
	if ($mybb->settings['thunderboard_use_image_lazy_loader']) {
	
		$i = 0;
		$imgs = $doc->getElementsByTagName('img');
		
		for ($i = $imgs->length; $i > 0; $i--){
			
		    $img = $doc->getElementsByTagName('img')->item($i - 1);
		   
			$div = $doc->createElement('div');
			
			$src = $img->getAttribute('src');
			
			$div->setAttribute('data-lazy-load-image', $src);
			
			$params = ['class', 'id', 'title'];
			
			foreach ($params as $param) {
				
				$attr = $img->getAttribute($param);
				
				if ($attr != 'undefined') {
					$div->setAttribute($param, $attr);
				}
				
			}
			
			// Replace the image with a plain <span>
			$img->parentNode->replaceChild($div, $img);
		    
		}
	
		$scripts[] = [
			'inline' => <<<HTML
			
(function () {
	
	var thunderboard_images = $("[data-lazy-load-image]");
	
    if (thunderboard_images.length > 0) {
	    
        thunderboard_images.each(function (index, element) {
	        
            var img = new Image();
            
            img.src = $(element).data("lazy-load-image");
            
            var params = ['class', 'id', 'title'], attr;
            
            $.each(params, function(index, value) {
	            
	            attr = $(element).attr(value);
	            
	            if (typeof attr !== 'undefined') {
		            
		            if (value == 'class') {
			            img.className = attr;
			        }
			        else {
		            	img[value] = attr;
		            }
		            
		        }
		        
		    });
		    
            $(element).replaceWith(img);
            
        });
        
    }
    
})();

HTML
		];
	
	}
	
	$minifiedGlobalStylesheets = $minifiedSpecificStylesheets = '';
	
	// Versioning
	$forumVersion = "1.2";
	
	// Process stylesheets
	global $theme;
	
	$i = 0;
	$stylesheets = $doc->getElementsByTagName('link');
	
	for ($i = $stylesheets->length; $i > 0; $i--) {
		
		$css = $doc->getElementsByTagName('link')->item($i - 1);
		
		$rel = $css->getAttribute('rel');
		$src = $css->getAttribute('href');
		
		// Do not continue if:
		// 1) the object is not a stylesheet
		// 2) this resource does not match this site's origin (fixes @Senpai's issue)
		if ($rel != 'stylesheet' or strpos($src, $mybb->settings['bburl']) === false) continue;
		
		// Add the stylesheet to our minification array
		if ($src) {
			
			$plainLink = str_replace($mybb->settings['bburl'] . '/', '', strtok($src, '?'));
			
			$specific = false;
			
			// Check if this stylesheet is a child of 'body', if it is, we assume it's page-specific since
			// MyBB loads global stylesheets in the head
			if (strpos($css->getNodePath(), '/html/body') !== false) {
				$specific = true;
			}
			
			if (!$specific) {
			
				// Check if this stylesheet is a global one or not. If it's specific, we're going to add it
				// to the body instead of the head. Then, our custom handler will append them to the head, checking
				// if the stylesheet is already available
				foreach (['global', $mybb->get_input['action']] as $pageAction) {
					
					foreach ($theme['stylesheets'][basename($_SERVER['PHP_SELF'])][$pageAction] as $pageSpecificStylesheet) {
						
						if ($pageSpecificStylesheet == $plainLink) {
							$specific = true;
						}
					
					}
					
				}
				
			}
			
			if ($specific) {
				$specificStylesheets[] = $plainLink;
			}
			else {
				$globalStylesheets[] = $plainLink;
			}
			
		}
		
		// Remove the stylesheet
		$css->parentNode->removeChild($css);
		
	}
	
	// Restore the original ordering
	$globalStylesheets = array_reverse($globalStylesheets);
	
	if ($specificStylesheets) {
		$specificStylesheets = array_reverse($specificStylesheets);
	}
		
	//if (!$shade) {
		
		// Minify stylesheets
		if ($mybb->settings['thunderboard_use_minifier']) {
			
			$minifiedGlobalStylesheets = '<link rel="stylesheet" type="text/css" href="min/?f=' . implode(',', array_unique($globalStylesheets)) . '&v=' . $forumVersion . '" />';
			
			if ($specificStylesheets) {
				$minifiedSpecificStylesheets = '<link rel="stylesheet" type="text/css" href="min/?f=' . implode(',', array_unique($specificStylesheets)) . '&v=' . $forumVersion . '" />';
			}
			
		}
		
	/*}
	else {
		
		*/
		foreach ($globalStylesheets as $css) {
			$minifiedGlobalStylesheets .= "<link rel='stylesheet' type='text/css' href='{$css}?v={$forumVersion}' />\n";
		}
		
		if ($specificStylesheets) {
			
			foreach ($specificStylesheets as $css) {
				$minifiedSpecificStylesheets .= "<link rel='stylesheet' type='text/css' href='{$css}?v={$forumVersion}' />\n";
			}
			
		}
		
	//}
	
	// Advanced experimental: move variables with global scope to the top
	if ($globalScopedVariables) {
		$scripts = array_merge([0 => ['inline' => str_replace('var ', '', implode("\n", (array) $globalScopedVariables))]], $scripts);
	}
	
	// Add eventual scripts to reload
	if ($mybb->settings['thunderboard_use_pjax']) {
		
		if ($scripts_to_reload and $turbo->isPjax()) {
			
			$base = [];
			
			foreach ($scripts_to_reload as $function => $script) {
				
				$exec = (is_numeric($function)) ? $script : $function;
				
				$base[] = "{$exec}.init();";
				
			}
			
			$scripts[] = [
				'inline' => implode("\n", $base)
			];
			
		}
		
	}
	
	// Add scripts to LAB.js to load them asynchronously
	$labscripts = '$LAB';
	foreach ($scripts as $script) {
		
		if ($script['external']) {
			
			$check_string = md5($script['external']);
			
			$versioning = '&v=' . $forumVersion;
			
			if (strpos($script['external'], $mybb->settings['bburl']) === false) {
				$versioning = '';
			}
	
			// Add the minification prefix to this script
			$prefix = ($mybb->settings['thunderboard_use_minifier'] and strpos($script['external'], 'http') === false) ? $mybb->settings['bburl'] . '/min/?f=' : '';
			
			$labscripts .= '.script(function() {

if (window.thunderboard_loaded.indexOf(\'' . $check_string . '\') == -1) {

	window.thunderboard_loaded.push(\'' . $check_string . '\');
	
	return \'' . $prefix . $script['external'] . $versioning . '\';
	
}

}).wait()';

		}
		else if ($script['inline']) {
			$labscripts .= '.wait(function() {' . $script['inline'] . '})';
		}
		
	}
	
	$labscripts .= ';';
		
	// Minify scripts
	if ($mybb->settings['thunderboard_use_minifier']) {
		
		require_once MYBB_ROOT . 'min/lib/JSMin.php';
		
		try {
			$lab_script = JSMin::minify($labscripts);
		}
		catch (Exception $e) {
			$lab_script = $labscripts;
		}
		
	}
	
	$labjs = <<<HTML
<script type="text/javascript" src="jscripts/LAB.min.js"></script>
<script type="text/javascript">$lab_script</script>
HTML;

	// Finally...
	$contents = str_replace(['<body>', '</head>'], ['<body>' . $minifiedSpecificStylesheets . $labjs, $minifiedGlobalStylesheets . '</head>'], $doc->saveHTML());
	
	// Enable PJAX for valid requests
	if ($mybb->settings['thunderboard_use_pjax']) {
		
		if ($turbo->isPjax()) {
			
			// Fix for header: location manipulations
			header("X-PJAX-URL: http" . (isset($_SERVER['HTTPS']) ? 's' : '') . '://' . "{$_SERVER['HTTP_HOST']}{$_SERVER['REQUEST_URI']}");
			
			return $turbo->extract($contents);
			
		}
		
	}
	
	return $contents;
	
}

$GLOBALS['settingsToReplace'] = [
	"usergroups_allowed" => "users",
];

function thunderboard_settings_saver()
{
	global $mybb, $page, $settingsToReplace;

	if ($mybb->request_method == "post" and $mybb->input['upsetting'] and $page->active_action == "settings") {
		
		foreach($settingsToReplace as $setting => $option) {
			
			if (isset($mybb->input['upsetting']['thunderboard_'.$setting]) and !is_array($mybb->input['thunderboard_'.$setting.'_select'])) {
				$mybb->input['upsetting']['thunderboard_'.$setting] = '';
			}
			else if (is_array($mybb->input['thunderboard_'.$setting.'_select'])) {
				$mybb->input['upsetting']['thunderboard_'.$setting] = implode(",", $mybb->input['thunderboard_'.$setting.'_select']);
			}
			
		}
		
	}
}

function thunderboard_settings_replacer($args)
{
	global $form, $lang, $mybb, $page, $settingsToReplace;

	if ($page->active_action != "settings" and $mybb->input['action'] != "change") {
		return false;
	}

	$lang->load('thunderboard');
	
	foreach($settingsToReplace as $setting => $option) {
		
		if ($args['row_options']['id'] == "row_setting_thunderboard_".$setting) {
			
			preg_match("/value=\"[^A-Za-z]{1,}\"/", $args['content'], $values);
			$values = explode(",", str_replace(["value", "\"", "="], "", $values[0]));
	
			
			if ($option == 'users') {
				$args['content'] = $form->generate_group_select("thunderboard_".$setting."_select[]", $values, ["multiple" => true, "size" => "5"]);
			}
			else if ($option == 'forums') {
				$args['content'] = $form->generate_forum_select("thunderboard_".$setting."_select[]", $values, ["multiple" => true, "size" => "10"]);				
			}
		}
	}
}