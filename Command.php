<?php 

namespace Pronode;

final class Command {
	
	
	
	/**
	 * First part of the command string to execute.
	 * .foo,param1.tail,param2 => .foo,param1
	 */
	public string $first;
	
	/**
	 * Normalized command string (selector check)
	 * foo,param1.bar,param2 => .foo,param1.bar,param2
	 * .foo,param1.bar,param2 => .foo,param1.bar,param2
	 * /foo,param1.bar,param2 => /foo,param1.bar,param2
	 * 
	 * @var string $normalizedInput
	 */
	private string $normalizedInput;
	
	
	/**
	 * Command selector: . or /
	 * .foo,param1.bar,param2 => .
	 * /foo,param1.bar,param2 => /
	 * 
	 * @var string $selector
	 */
	public string $selector = '.';
	
	
	/**
	 * Target to obtain (resource name without params)
	 * .foo,param1.bar,param2 => foo
	 * 
	 * @var string $target
	 */
	public string $target;
	
	
	/**
	 * Array of params
	 * .foo,param1,param2.bar,param1 => ['param1', 'param2']
	 *
	 * @var string[] $params
	 */
	public array $params = [];
	
	
	/**
	 * Remaining part of the command string
	 * .foo,param1.bar,param2 => .bar,param2
	 *
	 * @var string $tail
	 */
	public string $tail;
	
	
	/**
	 * Resource name
	 * .foo,param1.bar,param2 => foo,param1
	 * 
	 * @var string $resourceName
	 */
	public string $resourceName;
	
	
	
	public function __construct(string $input) {
		
		$this->normalizedInput = $this->normalize($input);
		
		$this->parse($this->normalizedInput);
		
		$this->resourceName = $this->getResourceName();
		
		$this->first = $this->selector.$this->resourceName;
	}
	
	/**
	 * Adds missing $selector at the beginning of command.
	 *
	 * someCommand,param => .someCommand,param
	 * .someCommand,param => .someCommand,param
	 * /someCommand,param => /someCommand,param
	 * 
	 * @return string
	 */
	public static function normalize(string $command, string $selector = '.') : string {
		
		if ($command == '') return $command;
		
		if ($command[0] != '.' && $command[0] != '/') {
			
			$command = $selector.$command;
		}
		
		return $command;
	}
	
	
	private function getResourceName() {
		
		$resourceName = $this->target;
		
		if (!empty($this->params)) $resourceName .= ','.implode(',', $this->params);
		
		return $resourceName;
	}
	
	
	/**
	 * Parse command to obtain properties:
	 * - selector
	 * - target resource
	 * - params
	 * - tail
	 * 
	 * @return void
	 */
	private function parse(string $command) : void {
		
		$chars = str_split($command);
		
		$commandArray = ['selector' => '.', 'target' => '', 'params' => [], 'tail' => ''];
		
		$paramsIndex = -1;
		$nodeParamsIndex = -1;
		$writeTo = &$commandArray['target'];
		$doubleQuoteOn = false;
		foreach ($chars as $i => $char) {
			
			if ($char == '"' && $chars[$i-1] != '\\') {
				$doubleQuoteOn = !$doubleQuoteOn;
				continue;
			}
			
			if (!$doubleQuoteOn) {
				// Target
				if ($char == '.' || $char == '/') {
					if ($i == 0) {
						$commandArray['selector'] = $char;
						continue;
					}
					else {
						$commandArray['tail'] = substr($command, $i);
						break;
					}
				}
				
				// Params
				if ($char == ',') {
					$paramsIndex++;
					$writeTo = &$commandArray['params'][$paramsIndex];
					continue;
				}
				
				// Node Params
				if ($char == '|') {
					$nodeParamsIndex++;
					$writeTo = &$commandArray['nodeParams'][$nodeParamsIndex];
					continue;
				}
			}
			
			$writeTo .= $char;
		}
		
		unset($writeTo); // Prevent 'never-used' warning.
		
		$this->selector = 	$commandArray['selector'];
		$this->target = 	$commandArray['target'];
		$this->params = 	$commandArray['params'];
		$this->tail = 		$commandArray['tail'];
	}
	
	
	/**
	 * Creates Command from given selector, target and params.
	 * 
	 * @param string $selector
	 * @param string $target
	 * @param array $params
	 * 
	 * @return Command
	 */
	public static function create(?string $selector = '.', string $target, array $params = [], string $tail = '') : Command {
	
		$input = $selector.$target;
		
		if (!empty($params)) $input .= ','.implode(',', $params);
		
		$input .= static :: normalize($tail);
		
		return new Command($input);
	}
	
	
	
	/**
	 * Creates next command basing on tail
	 */
	public function next() : ?Command {
		
		if (!$this->tail) return null;
		
		return new Command($this->tail);
		
	}
}