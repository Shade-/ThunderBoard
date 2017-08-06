<?php

/**
 * Light-speed page loads for your MyBB forum
 *
 * @package Main API class
 * @version beta 5
 */

class ThunderBoard
{
	public $scripts = [];
	public $metas = [];
	public $stylesheets = [];
	public $globalScoped = [];
	public $counter = 0;
	
	public function __construct()
	{
		
		$this->dom = new \DOMDocument;
		
		libxml_use_internal_errors(true);
		
	}
	
	public function load_content($content = '')
	{
		if (!$content) {
			return false;
		}
		
		$this->originalContent = $content;
		
		// Convert <if>, <else> and closures to their HTML entities equivalents.
		// This is to prevent DOMDocument to strip out invalid tags. Also, strip out HTML comments in script,
		// to prevent DOMDocument to return wrong values
		$temp = preg_replace('#(<script[^<]*?)<!--#is', '$1', str_replace(['<if', '<else>', '<else', '</if>'], ['&lt;if', '&lt;else&gt;', '&lt;else', '&lt;/if&gt;'], $content));
	
		$this->dom->loadHTML($temp, LIBXML_HTML_NOIMPLIED);
		
		libxml_clear_errors();
		
		// Clear our objects
		$this->scripts = [];
		$this->metas = [];
		$this->stylesheets = [];
	}
	
	public function process_scripts()
	{
		global $mybb, $PL;
		
		$PL or require_once PLUGINLIBRARY;
		
		// Scripts to reload in case they are found
		$scriptsToCheckForReload = (array) $PL->cache_read('thunderboard_reload_scripts');
		$scriptsToRemove = [];
		
		foreach ($this->dom->getElementsByTagName("script") as $script) {
			
			$this->counter++;
			
			$src = $script->getAttribute('src');
			if ($src) {
				
				// Should be reloaded?
				foreach ($scriptsToCheckForReload as $file => $function) {
					
					if (stripos($src, $file) !== false) {
						
						// Shall we reload the entire script?
						if ($function == 'reload') {
							$this->scripts[$this->counter]['reload'] = true;
						}
						// Or shall we just use the custom function?
						else {
							$this->scripts['reload'][] = $function . ';';
						}
						
						break;
						
					}
					
				}
				
				// Add this script to our list
				$this->scripts[$this->counter]['external'] = str_replace($mybb->settings['bburl'] . '/', '', $src);
				
				// Don't strip out the querystring if this script is external
				if (strpos($this->scripts[$this->counter]['external'], 'www') === false) {
					$this->scripts[$this->counter]['external'] = strtok($this->scripts[$this->counter]['external'], '?');
				}
				
			}
			
			$content = trim($script->nodeValue);
			
			if ($script->hasAttribute('data-global')) {
				$this->globalScoped[] = $content;
			}
			else if ($content) {
				$this->scripts[$this->counter]['inline'] = html_entity_decode($content);
			}
			
			$scriptsToRemove[] = $script;
		
			// Remove {$mybb->asset_url}
			if ($this->scripts[$this->counter]['external']) {
				$this->scripts[$this->counter]['external'] = str_replace('{$mybb->asset_url}/', '', $this->scripts[$this->counter]['external']);
			}
			
		}
		
		foreach ($scriptsToRemove as $script) {
			$script->parentNode->removeChild($script);
		}
		
		// Add global scope variables to the top
		if ($this->globalScoped) {
			array_unshift($this->scripts, ['inline' => implode("\n", $this->globalScoped)]);
		}
		
		return $this->scripts;
		
	}
	
	function process_global_variables()
	{
		
		global $mybb;
		
		foreach ($this->dom->getElementsByTagName("script") as $script) {
			
			$content = trim($script->nodeValue);
			
			if ($content) {
			
				$this->counter++;
						
				require_once MYBB_ROOT . 'inc/plugins/ThunderBoard/jtokenizer.php';
				
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
					$this->scripts[$this->counter]['globalScoped'][] = implode('', $variable);
				}
				
				// Delete variables
				$this->originalContent = str_replace($this->scripts[$this->counter]['globalScoped'], '', $this->originalContent);
				
			}
			
			// Replace var with window to globalize them
			if ($this->scripts[$this->counter]['globalScoped']) {
				$this->scripts[$this->counter]['globalScoped'] = str_replace('var ', 'window.', $this->scripts[$this->counter]['globalScoped']);
			}
			
		}
		
		return $this->scripts;
		
	}
	
	function process_metas()
	{
		
		$metasToDelete = [];
		
		foreach ($this->dom->getElementsByTagName('meta') as $meta) {
			
			if ($meta->getAttribute('http-equiv') == 'refresh') {
				
				$this->metas[] = $meta->getAttribute('content');
				
				$metasToDelete[] = $meta;
				
			}
			
		}
		
		foreach ($metasToDelete as $meta) {
			$meta->parentNode->removeChild($meta);
		}
		
		return $this->metas;
		
	}
	
	function process_stylesheets()
	{
		
		global $theme, $mybb;
		
		$i = 0;
		$stylesheets = $this->dom->getElementsByTagName('link');
		
		for ($i = $stylesheets->length; $i > 0; $i--) {
		
			$css = $this->dom->getElementsByTagName('link')->item($i - 1);
			
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
					foreach (['global', $mybb->get_input('action')] as $pageAction) {
						
						foreach ((array) $theme['stylesheets'][basename($_SERVER['PHP_SELF'])][$pageAction] as $pageSpecificStylesheet) {
							
							if ($pageSpecificStylesheet == $plainLink) {
								$specific = true;
							}
						
						}
						
					}
					
				}
				
				if ($specific) {
					$this->stylesheets['specific'][] = $plainLink;
				}
				else {
					$this->stylesheets['global'][] = $plainLink;
				}
				
			}
			
			// Remove the stylesheet
			$css->parentNode->removeChild($css);
			
		}
		
		// Restore the original ordering
		if ($this->stylesheets['global']) {
			$this->stylesheets['global'] = array_reverse($this->stylesheets['global']);
		}
		
		if ($this->stylesheets['specific']) {
			$this->stylesheets['specific'] = array_reverse($this->stylesheets['specific']);
		}
		
		return $this->stylesheets;
		
	}
	
	function process_images()
	{
		
		global $mybb;
		
		if ($mybb->settings['thunderboard_use_image_lazy_loader']) {
	
			$i = 0;
			$imgs = $this->dom->getElementsByTagName('img');
			
			for ($i = $imgs->length; $i > 0; $i--){
				
			    $img = $this->dom->getElementsByTagName('img')->item($i - 1);
			    
				$div = $this->dom->createElement('div');
				
				$src = $img->getAttribute('src');
				
				$div->setAttribute('data-lazy-load-image', $src);
				
				$params = ['class', 'id', 'title'];
				
				foreach ($params as $param) {
					
					$attr = $img->getAttribute($param);
					
					if ($attr != 'undefined') {
						$div->setAttribute($param, $attr);
					}
					
				}
				
				// Replace the image with a plain <div>
				$img->parentNode->replaceChild($div, $img);
			    
			}
		
			return $this->load_scripts([
				<<<HTML
		
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
	
HTML
			], 'inline');
		
		}
		
		return false;
		
	}
	
	function build_thunderplates()
	{
		
		global $db;
		
		// Remove old thunderplates
		$db->delete_query('thunderplates');
		
		$insert = [];
		
		$query = $db->simple_select('templates', 'sid, title, template', '', ['order_by' => 'title', 'order_dir' => 'ASC']);
		
		while ($template = $db->fetch_array($query)) {
			
			$content = [
				'title' => $template['title'],
				'sid' => $template['sid'],
				'template' => ''
			];
						
			$globalScoped = [];
			
			if ($template['template']) {
				
				$this->load_content($template['template']);
			
				if ($this->process_global_variables()) {
		
					if ($this->scripts) {
						
						foreach ($this->scripts as $script) {
							
							if ($script['globalScoped']) {
								$globalScoped = array_merge($globalScoped, $script['globalScoped']);
							}
							
						}
						
					}
				
				}
				
				if ($this->originalContent) {
					$content['template'] = $this->originalContent;
				}
				
				if ($globalScoped) {
					$content['template'] .= "\n<script type='text/javascript' data-global='true'>\n" . implode("\n", $globalScoped) . "\n</script>";
				}
				
				if ($content['template']) {
					$content['template'] = $db->escape_string($content['template']);
				}
				
			}
			
			$insert[] = $content;
			
		}
		
		if ($insert) {
			return $db->insert_query_multiple('thunderplates', $insert);
		}
		
		return false;
		
	}
	
	function load_scripts($contents = [], $type = 'external', $skipIfPJAXRequest = true)
	{
		if ($skipIfPJAXRequest and $this->is_pjax()) return $this->scripts;
		
		foreach ((array) $contents as $content) {
			$this->counter++;
			$this->scripts[$this->counter][$type] = $content;
		}
		
		return $this->scripts;
		
	}
	
	function is_pjax()
	{
		return (isset($_SERVER["HTTP_X_PJAX"]) or isset($_GET["_pjax"]));
	}
	
	function extract($content)
	{
		// Send back the original content if we aren't supposed to be extracting
        if (!is_string($content) OR !$this->is_pjax()) {
            return $content;
        }

        // We only process if we find a valid <body>
        preg_match('/(?:<body[^>]*>)(.*)<\/body>/isU', $content, $matches);

        // Did we find the body
        if (count($matches) !== 2) {
            return $content;
        }

        $body = $matches[1];

        // Does the page have a title
        preg_match('@<title>([^<]+)</title>@', $content, $matches);

        // Did we find the title
        $title = (count($matches) === 2) ? $matches[0] : '';

        // Set new content
        return $title.$body;
	}
	
}

$thunderboard = new ThunderBoard();