<?php

namespace Pronode;

class Marker {
	
	/**
	 * String with {{markerCommand}} syntax.
	 */
	public string $string;
	
	
	/**
	 * Pronode command converted from {{markerCommand}}
	 */
	public string $command;
	
	
	/**
	 * Marker HTML placement type: inner || outer
	 *
	 * If marker is inside tag's attribute => outer (so JS knows to replace outerHtml)
	 * If marker is inside tag contents => inner (so JS knows to replace innerHtml)
	 */
	public string $htmlPlacement = 'undefined';
	
	
	/**
	 * Marker start position inside Template contents
	 */
	public int $pos;
	
	
	/**
	 * Marker end position inside Template contents
	 */
	public int $end;
	
	
	/**
	 * Marker -> Fragment relation.
	 * Marker may belong to only one Fragment
	 */
	public Fragment $fragment;
	
	
	function __construct(string $markerString) {
		
		$this->string = $markerString;
		
		$this->command = $this->toCommand($this->string);
	}
	
	
	/**
	 * Sets start and end positions of Marker inside Template contents
	 */
	public function setPos(int $start) : void {
		
		$this->pos = $start;
		$this->end = $start + strlen($this->string);
		
	}
	
	/**
	 * Converts {{markerCommand}} to Pronode command.
	 */
	protected function toCommand(string $markerString) : string {
		
		$command = $markerString;
		
		// Remove braces:
		$command = str_replace('{', '', $command);
		$command = str_replace('}', '', $command);
		
		// Template shorthand: target|templateName syntax is just shorthand for target.view,templateName
		$command = str_replace('|', '.view,', $command);
		
		// Make sure that the command always requests for a view: 
		if (strpos($command, '.view') === false) {
			
			$command .= '.view'; // TODO: this has to be the LAST command, above checks just an occurence
		}
		
		return $command;
	}
	
	
	/**
	 * Executes ->command on given Node.
	 * Returns result child Node.
	 */
	public function exec(Node $node) : Node {
		
		return $node->exec($this->command);
	}
	
}