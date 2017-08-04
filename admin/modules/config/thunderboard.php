<?php
/**
 * ThunderBoard
 * 
 * Admin module
 *
 */
 
if (!defined('IN_MYBB')) {
	header("HTTP/1.0 404 Not Found");
	exit;
}

if (!defined("PLUGINLIBRARY")) {
	define("PLUGINLIBRARY", MYBB_ROOT."inc/plugins/pluginlibrary.php");
}

define(MAINURL, "index.php?module=config-thunderboard");

$PL or require_once PLUGINLIBRARY;

$lang->load("thunderboard");

$page->add_breadcrumb_item($lang->thunderboard, MAINURL);

if ($mybb->input['action'] == 'delete') {
	
	$templates = $PL->cache_read('thunderboard_templates');
	
	if ($mybb->input['title']) {
		
		unset($templates[$mybb->input['title']]);
		
		$PL->cache_update('thunderboard_templates', $templates);
		
		$shade_plugins = $cache->read('shade_plugins');
	    $shade_plugins['ThunderBoard']['templates_last_updated'] = TIME_NOW;
	    $cache->update('shade_plugins', $shade_plugins);
		
		flash_message($lang->thunderboard_success_deleted, 'success');
		
	}
	
	admin_redirect(MAINURL);
	
}
else if ($mybb->input['action'] == 'buster') {
	
	if ($mybb->request_method == 'post') {
		
		if ($mybb->input['no']) {
			admin_redirect(MAINURL);
		}
				
		$dirs = array_filter(glob(MYBB_ROOT . 'min/static/*'), 'is_dir');
				
		foreach ($dirs as $dir) {
			rrmdir($dir);
		}
		
		flash_message($lang->thunderboard_buster_all_files_deleted, 'success');
		admin_redirect(MAINURL);
		
	}
	
	$page->output_confirm_action(MAINURL . '&amp;action=buster', $lang->thunderboard_buster_desc, $lang->thunderboard_buster);
	
}
else if ($mybb->input['action'] == 'reload') {
	
	$rules = (array) $PL->cache_read('thunderboard_reload_scripts');
	
	if ($mybb->request_method == 'post') {
		
		$rules = (array) explode(PHP_EOL, $mybb->input['rules']);
		
		$scripts = [];
		
		foreach ($rules as $rule) {
			
			list($file, $function) = explode('=', $rule);
			
			$file = str_replace('.js', '', $file);
			$function = rtrim(str_replace(';', '', $function));
			
			if (!$function or !$file) {
				continue;
			}
			
			$scripts[$file] = $function;
			
		}
		
		$PL->cache_update('thunderboard_reload_scripts', $scripts);

		flash_message($lang->thunderboard_success_edited_reload_rules, 'success');
		
	}
	
	$page->output_header($lang->thunderboard_reload_rules);
	
	thunderboard_generate_tabs("reload");
	
	$form = new Form(MAINURL . "&amp;action=reload", "post");
	$form_container = new FormContainer($lang->thunderboard_reload_rules);
	
	$show_rules = '';
	
	foreach ($rules as $file => $function) {
		$show_rules .= $file . '=' . $function . PHP_EOL;
	}
	
	$template = $form->generate_text_area('rules', rtrim($show_rules));
	
	$form_container->output_row($lang->thunderboard_reload_rules_scripts, $lang->thunderboard_reload_rules_scripts_desc, $template, 'rules');
		
	$form_container->end();
		
	$buttons[] = $form->generate_submit_button($lang->thunderboard_edit_button);
		
	$form->output_submit_wrapper($buttons);
		
	$form->end();
	
	echo "<br />";
	echo "<br />";
	
	$table = new Table;
	
	$table->construct_header($lang->thunderboard_reload_rules_header_file, array('width' => "15%"));
	$table->construct_header($lang->thunderboard_reload_rules_header_function);
	$table->construct_header($lang->thunderboard_reload_rules_header_status, array('width' => "50%"));
	
	$path = MYBB_ROOT . 'jscripts/';
	
	foreach ($rules as $file => $function) {
		
		$table->construct_cell($file);
		$table->construct_cell($function);
		
		$clearedFunc = str_replace(['(', ')'], '', $function);
		
		if ($function == 'reload') {
			$table->construct_cell($lang->thunderboard_reload_rules_status_hard);
		}
		else if (exec("grep -E '" . escapeshellarg($clearedFunc) . '|' . escapeshellarg(explode('.', $clearedFunc)[1]) . "' " . $path . $file . '.js')) {
			$table->construct_cell($lang->thunderboard_reload_rules_status_valid);
		}
		else {
			$table->construct_cell($lang->thunderboard_reload_rules_status_invalid);
		}
		
		$table->construct_row();
		
	}
	
	$table->output($lang->thunderboard_reload_rules_status);
	
}
else if ($mybb->input['action'] == 'edit') {
	
	$templates = (array) $PL->cache_read('thunderboard_templates');
	
	if ($mybb->request_method == 'post') {
		
		$templates[$mybb->input['title']]['template'] = $mybb->input['template'];
		$templates[$mybb->input['title']]['timestamp'] = TIME_NOW;
		
		$PL->cache_update('thunderboard_templates', $templates);
		
		$shade_plugins = $cache->read('shade_plugins');
	    $shade_plugins['ThunderBoard']['templates_last_updated'] = TIME_NOW;
	    $cache->update('shade_plugins', $shade_plugins);

		flash_message($lang->thunderboard_success_edited, 'success');
		admin_redirect(MAINURL);
		
	}
	
	$title = (string) $mybb->input['title'];
	
	if (!$templates[$title]) {
		admin_redirect(MAINURL . '&amp;action=new');
	}
	
	$page->output_header($lang->thunderboard_edit);
	
	thunderboard_generate_tabs("edit");
	
	$form = new Form(MAINURL . "&amp;action=edit", "post");
	$form_container = new FormContainer($lang->thunderboard_edit);
	
	$template = $form->generate_text_area('template', $templates[$title]['template']);
	$title = $form->generate_text_box('title', $title);
	
	$form_container->output_row($lang->thunderboard_new_title, $lang->thunderboard_new_title_desc, $title, 'title');
	$form_container->output_row($lang->thunderboard_new_template, $lang->thunderboard_new_template_desc, $template, 'template');
		
	$form_container->end();
		
	$buttons[] = $form->generate_submit_button($lang->thunderboard_edit_button);
		
	$form->output_submit_wrapper($buttons);
		
	$form->end();
	
}
else if ($mybb->input['action'] == 'new') {
	
	if ($mybb->request_method == 'post') {
		
		$templates = (array) $PL->cache_read('thunderboard_templates');
		
		$templates[$mybb->input['title']]['template'] = $mybb->input['template'];
		$templates[$mybb->input['title']]['timestamp'] = TIME_NOW;
		
		$PL->cache_update('thunderboard_templates', $templates);
		
		$shade_plugins = $cache->read('shade_plugins');
	    $shade_plugins['ThunderBoard']['templates_last_updated'] = TIME_NOW;
	    $cache->update('shade_plugins', $shade_plugins);
		
		flash_message($lang->thunderboard_success_created, 'success');
		admin_redirect(MAINURL);
		
	}
	
	$page->output_header($lang->thunderboard_new);
	
	thunderboard_generate_tabs("new");
	
	$form = new Form(MAINURL . "&amp;action=new", "post");
	$form_container = new FormContainer($lang->thunderboard_new_template);
	
	$title = $form->generate_text_box('title', $mybb->input['title']);
	$template = $form->generate_text_area('template', $mybb->input['template']);
	
	$form_container->output_row($lang->thunderboard_new_title, $lang->thunderboard_new_title_desc, $title, 'title');
	$form_container->output_row($lang->thunderboard_new_template, $lang->thunderboard_new_template_desc, $template, 'template');
		
	$form_container->end();
		
	$buttons[] = $form->generate_submit_button($lang->thunderboard_create);
		
	$form->output_submit_wrapper($buttons);
		
	$form->end();
	
}
else if ($mybb->input['action'] == 'rebuild_cache') {
	
	if ($mybb->request_method == 'post') {
	
		if ($mybb->input['no']) {
			admin_redirect(MAINURL);
		}
		
		require_once MYBB_ROOT . 'inc/plugins/ThunderBoard/class_core.php';
		
		$thunderboard->build_thunderplates();
		
		flash_message($lang->thunderboard_success_cache_rebuilt, 'success');
		admin_redirect(MAINURL);
	
	}

	$page->add_breadcrumb_item($lang->thunderboard, MAINURL);
	$page->add_breadcrumb_item($lang->thunderboard_rebuild_cache, MAINURL . '&action=rebuild_cache');
	
	$page->output_confirm_action(MAINURL . '&action=rebuild_cache', $lang->thunderboard_rebuild_cache_desc, $lang->thunderboard_rebuild_cache);
	
}
// Overview
else {

	$templates = $PL->cache_read('thunderboard_templates');
	
	$page->output_header($lang->thunderboard);
	
	thunderboard_generate_tabs("overview");
	
	$table = new Table;
	
	$table->construct_header($lang->thunderboard_templates_page, array('width' => "15%"));
	$table->construct_header($lang->thunderboard_templates_last_edited);
	$table->construct_header($lang->thunderboard_templates_controls, array('width' => "200px"));
	
	if ($templates) {
		
		$i = 0;
	
		foreach ($templates as $pg => $template) {
			
			if (!$template) {
				$deletionQueue[] = $pg;
				continue;
			}
		
			// This rule's informations
			$table->construct_cell($pg);
			$table->construct_cell(my_date('relative', $template['timestamp']));
			
			// This rule's available commands
			$popup = new PopupMenu("thunderboard_{$i}", $lang->options);
			$popup->add_item($lang->thunderboard_edit, MAINURL . "&amp;action=edit&amp;title=$pg");
			$popup->add_item($lang->thunderboard_delete_template, MAINURL . "&amp;action=delete&amp;title=$pg");
			
			$table->construct_cell($popup->fetch(), array('class' => 'align_center'));
			
			$table->construct_row();
			
			$i++;
			
		}
		
		// Delete corrupted templates
		if ($deletionQueue) {
			
			foreach ($deletionQueue as $pg) {
				unset ($templates[$pg]);
			}
			
			$PL->cache_update('thunderboard_templates', $templates);
			
		}
		
	}
	
	if (!$templates) {
	
		$table->construct_cell($lang->thunderboard_error_no_previews_active, array('colspan' => '3'));
		$table->construct_row();
		
	}
	
	$table->output($lang->thunderboard);
	
}

$page->output_footer();

function thunderboard_generate_tabs($selected)
{
	global $lang, $page;

	$sub_tabs = array();
	$sub_tabs['overview'] = array(
		'title' => $lang->thunderboard_templates,
		'link' => MAINURL,
		'description' => $lang->thunderboard_templates_desc
	);
	$sub_tabs['new'] = array(
		'title' => $lang->thunderboard_new,
		'link' => MAINURL."&amp;action=new",
		'description' => $lang->thunderboard_new_desc
	);
	$sub_tabs['edit'] = array(
		'title' => $lang->thunderboard_edit,
		'link' => MAINURL."&amp;action=edit",
		'description' => $lang->thunderboard_edit_desc
	);
	$sub_tabs['reload'] = array(
		'title' => $lang->thunderboard_reload_rules,
		'description' => $lang->thunderboard_reload_rules_desc,
		'link' => MAINURL."&amp;action=reload"
	);
	$sub_tabs['rebuild_cache'] = array(
		'title' => $lang->thunderboard_rebuild_cache,
		'link' => MAINURL."&amp;action=rebuild_cache",
	);
	$sub_tabs['buster'] = array(
		'title' => $lang->thunderboard_buster,
		'description' => $lang->thunderboard_buster_desc,
		'link' => MAINURL."&amp;action=buster",
	);

	return $page->output_nav_tabs($sub_tabs, $selected);
}

function rrmdir($dir)
{
	if (is_dir($dir)) {
		$objects = scandir($dir);
		foreach ($objects as $object) {
			if ($object != "." && $object != "..") {
				if (filetype($dir."/".$object) == "dir") {
					rrmdir($dir."/".$object);
				}
				else {
					unlink ($dir."/".$object);
				}
			}
		}
		reset($objects);
		rmdir($dir);
	}
}