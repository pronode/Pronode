<?php 

namespace Pronode;

/**
 * Returns $var type.
 * If $var is an object, returns object class.
 * 
 * @param mixed $var
 * @return string
 */
function get_type($var) : string {
	
	$type = gettype($var);
	
	if ($type == 'object') {
		
		return get_class($var);
	}
	
	return $type;
}


/**
 * Checks whether variable is an array of numeric-indexes only.
 * 
 * @param mixed $array
 * @return boolean
 */
function is_iter($array) : bool {
	
	if (!is_array($array)) return false;
	if (array() === $array) return false;
	
	foreach (array_keys($array) as $key) {
		
		if (!is_numeric($key)) return false;
	}
	
	return true;
}


/**
 * Checks whether variable is an array of string-indexes.
 *
 * @param mixed $array
 * @return boolean
 */
function is_assoc($array) : bool {
	
	if (!is_array($array)) return false;
	if (array() === $array) return false;
	
	return !is_iter($array);
}


/**
 * Alias for var_dump
 * 
 * @param mixed $var
 */
function v($var) : void {
	
	var_dump($var);
}


/**
 * Quick Benchmark to measure execution time of given function.
 * 
 * usage: bench(fn() => $obj->methodToTest(), 'Some label if you want');
 * 
 * @param \Closure $fn
 * @param string $name
 * @return mixed
 */
function bench(\Closure $fn, $label = 'anonymous') {
	
	$starttime = array_sum(explode(" ", microtime()));
	
	$result = $fn();
	
	$endtime = round((array_sum(explode(" ", microtime())) - $starttime)*1000, 1);
	
	echo "<hr/> Benchmark ($label) : ".$endtime." ms<br/>";
	
	return $result;
}