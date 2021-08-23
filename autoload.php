<?php

namespace Pronode;

/**
 * Vendor class autoloader
 */
function vendorAutoloader($class) {
	
	if (DIRECTORY_SEPARATOR == '/') $class = str_replace('\\', '/', $class); # unix
	
	$path = __DIR__.DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.$class.'.php';
	
	if (file_exists($path)) include_once $path;
}

/**
 * Native class autoloader
 */
function nativeAutoloader($class) {
	
	if (DIRECTORY_SEPARATOR == '/') $class = str_replace('\\', '/', $class); # unix
	
	$path = __DIR__.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.$class.'.php';
	
	if (file_exists($path)) include_once $path;
}

spl_autoload_register('Pronode\vendorAutoloader');
spl_autoload_register('Pronode\nativeAutoloader');

// Additional files:
include_once 'Functions.php';