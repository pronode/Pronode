<?php 

namespace Pronode;

class TemplateFactory {
	
	/**
	 * Runtime cache containter.
	 * Prevents redundant Template processing.
	 * 
	 * @var Template[]
	 */
	protected static array $container = [];
	
	
	/**
	 * Loads Template object from given $path.
	 * @param string $path
	 * @return Template 
	 */
	public static function load(string $path) : ?Template {
		
		if (!$path) {
			
			return null;
			throw new \ErrorException("Template path must be specified");
		}
		
		if (isset(self::$container[$path])) { // Runtime caching
			
			// Loading from cache container:
			return self::$container[$path];
		}
		
		// Check if "/templates/pn_templates_compiled" exists:
		$tpl_compiled_dir = dirname($path).DIRECTORY_SEPARATOR.'pn_templates_compiled';
		if (!file_exists($tpl_compiled_dir)) { 
			
			mkdir($tpl_compiled_dir, 0777, true);
		}
		
		// Loading compiled (serialized, already processed) if exists
		$cpath = $tpl_compiled_dir.DIRECTORY_SEPARATOR.basename($path).'.pn';
		if (file_exists($cpath)) { 
			
			$serialized = file_get_contents($cpath);
			$unserialized = unserialize($serialized);
			
			if ($unserialized->modified == filemtime($unserialized->path)) { // cached file exists and is fresh
				
				$template = $unserialized;
			} 
		} 
		
		if (!isset($template)) { // Create new Template object
			
			$template = new Template($path);
		
			$serialized = serialize($template);
			file_put_contents($cpath, $serialized);
		}
		
		// Pass it to the runtime cache container:
		self::$container[$path] = $template;
		
		return $template;
	}
	
	
}