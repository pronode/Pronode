<?php 

namespace Pronode;

class Template {
	
	
	/**
	 * Template name (name of the file which contains that template)
	 */
	public string $name;
	
	
	/**
	 * Path to Template file
	 */
	public string $path;
	
	
	/**
	 * Template contents loaded from file of given $path
	 */
	public string $contents;
	
	
	/**
	 * Fragmented Template contents (pn_fragment and pn_origin attributes are inserted)
	 */
	public string $contentsFragmented;
	
	
	/**
	* Markers array
	* int:index => Marker Object
	* 
	* @var Marker[]
	*/
	public array $markers = [];
	
	
	/**
	 * Fragments array
	 * int:pos => Marker Object
	 * 
	 * @var Fragment[]
	 */
	public array $fragments = [];
	
	
	public function __construct($path) {
		
		$this->path = $path;
		
		// Processing:
		$this->name = $this->getName();
		$this->modified = filemtime($this->path);
		
		$this->contents = $this->load();
		
		$fragmented = $this->fragmentize($this->fragments);
		
		$this->markers = $fragmented['markers'];
		$this->fragments = $fragmented['fragments'];
		$this->contentsFragmented = $fragmented['contents'];
	}
	
	
	/**
	 * Returns template name (name of the file containing that template)
	 */
	private function getName() : string {
		
		return strtok(basename($this->path), '.');
	}
	
	
	/**
	 * Opens template file from path.
	 * 
	 * @return string
	 */
	private function load() : string {
		
		$contents = '';
		
		if (file_exists($this->path)) {
			
			$contents = file_get_contents($this->path);
			
		} else {
			
			throw new \ErrorException("Template file doesn't exists on given path: {$this->path}");
		}
		
		return $contents;
	}
	
	
	/**
	 * Gets an array of {{markers}} present in Template.
	 * Array is a set of key => value pairs:
	 * index => Marker Object
	 * Sets ->markers to that array.
	 * 
	 * @return Marker[]
	 */
	public function getMarkers(string $contents) : array {
		
		// Marker detection:
		$markerPositions = []; // array of int:position => {{marker}}
		$chars = $contents;
		$length = strlen($chars);
		$writeTo = null; // write pointer
		
		for ($i = 0; $i < $length; $i++) {
			
			$char = $chars[$i];
			
			// Marker detection:
			if ($char == '{' && isset($chars[$i+1]) && $chars[$i+1] == '{') {  // detected {{ start
				
				$markerPositions[$i] = '';
				$writeTo = &$markerPositions[$i];
				
			} elseif ($char == '}' && isset($chars[$i+1]) && $chars[$i+1] == '}') {  // detected }} end
				
				$writeTo .= '}}';
				$i += 1;
				unset($writeTo);
			}
			
			if (isset($writeTo)) {
				
				$writeTo .= $char;
			}
		}
		
		// Marker array build:
		$markers = [];
		foreach ($markerPositions as $pos => $marker) {
			
			$marker = new Marker($marker);
			$marker->setPos($pos);
			
			$markers[] = $marker;
		}
		
		return $markers;
	}
	
	
	/**
	 * Parse Template contents to look for Fragments.
	 * Requires an array of Markers.
	 * Modifies Markers by setting Marker->htmlPlacement property.
	 * 
	 * This function loops through Markers positions and looks backwards for 
	 * any opened but not closed tag to find tag position. Then, it looks forwards
	 * for closed but not opened tag to finish Fragment detection.
	 * 
	 * @param Marker[] $markers
	 * @return Fragment[]
	 */
	public function getFragments(array &$markers, string $contents) : array {
		
		$fragments = [];
		
		$length = strlen($contents);
		$chars = $contents;
		
		foreach ($markers as &$marker) { // use reference to set Marker's ->htmlPlacement inner|outer
			
			// Parse Template contents: 
			$fragment = null;
			$buffer = '';
			
			// 1) Finding Fragment start: last opened but not closed tag
			$openings = 0;
			$closings = 0;
			$gtfound = false; // flags to TRUE if > found (inner|outer detection purpose)
			$skip = false; // if the Fragment is already detected
			
			for ($i = $marker->pos-1; $i >= 0; $i--) {
				
				$char = $chars[$i];
				
				$buffer .= $char;
				
				if ($char == '>') $gtfound = true;
				
				if ($char == '<' && isset($chars[$i+1]) && $chars[$i+1] != '/' && $chars[$i+1] != '!') { // tag opening (except <! and </)
					
					$openings++;
					
				} elseif ($char == '/' && isset($chars[$i-1]) && $chars[$i-1] == '<') { // tag closing: </ (like </div>)
					
					$closings++;
					
				} elseif ($char == '>' && isset($chars[$i-1]) && $chars[$i-1] == '/') { // empty tag closing : /> (like <br/> or <hr />)
					
					$closings++;
				}
				
				if ($char == '<' && !$gtfound) {
					
					$marker->htmlPlacement = 'outer';
				}
				
				if ($openings > $closings) {
					
					// Fragment found - ↓<div position
					
					if (isset($fragments[$i])) {
						
						$skip = true;
						$fragment = $fragments[$i];
						
					} else {
						
						$fragment = new Fragment($this, $i);
					}
					
					if ($marker->htmlPlacement == 'undefined') $marker->htmlPlacement = 'inner';
					
					$marker->fragment = $fragment;
					$fragment->markers[] = $marker;
					break;
				}
			}
			
			if ($skip) continue;
			
			$buffer = strrev($buffer);
			$buffer .= $marker->string;
			
			// 2) Finding Fragment end: last closed but not opened tag
			$openings = 0;
			$closings = 0;
			
			for ($i = $marker->end; $i < $length; $i++) {
				
				$char = $chars[$i];
				
				$buffer .= $char;
				
				if ($char == '<' && isset($chars[$i+1]) && $chars[$i+1] != '/' && $chars[$i+1] != '!') { // tag opening "<" (except "<!" and "</")
					
					$openings++;
					
				} elseif ($char == '<' && isset($chars[$i-1]) && $chars[$i+1] == '/') { // tag closing: </ (like </div>)
					
					$closings++;
					
				} elseif ($char == '/' && isset($chars[$i-1]) && $chars[$i+1] == '>') { // empty tag closing: /> (like <br/> or <hr />)
					
					$closings++;
				}
				
				if ($openings < $closings) {
					
					// Fragment end found - </div>↓ or />↓ position
					$fragments[$fragment->pos] = $fragment;
					
					// Write to the end of a tag:
					do {
						
						$i++;
						$buffer .= $chars[$i]; 
						
					} while ($chars[$i] != '>');
					
					break;
				}
			}
			
			$fragment->contents = $buffer;
			$fragment->id = $fragment->getId();
			$fragment->end = $fragment->pos + strlen($fragment->contents);
		}
		
		return $fragments;
		
	}
	
	
	/**
	 * Converts Template contents into fragmentized contents.
	 * 
	 * Retruns assoc array:
	 * 
	 *  contents => string
	 * 	markers => Marker[]
	 *  fragments => Fragment[]
	 * 
	 * TODO: <script> tags may be problematic due to possible ['<', '>'] occurences
	 * TODO: inline front-end frameworks' scripts may be problematic too
	 * TODO: Solution? Strip whole javascript somehow?
	 * 
	 * @return array
	 */
	public function fragmentize() : array {
		
		$markers = $this->getMarkers($this->contents);
		$fragments = $this->getFragments($markers, $this->contents);
		
		$contents = $this->contents;
		
		$i = 0; // fragment index
		
		foreach ($fragments as $fragment) {
			
			$insert = " pn_fragment=\"{$fragment->id}\" pn_origin=\"{{origin}}\"";
			
			// lets found first space or / or > after tag declaration (<div↓
			$pos = $fragment->pos + $i*strlen($insert);
			
			for ($j = $pos; $j < $pos+20; $j++) {
				
				if (ctype_space($contents[$j]) || $contents[$j] == '>' || $contents[$j] == '/') {
					
					$contents = substr_replace($contents, $insert, $j, 0);
					break;
					
				}
			}
			
			$i++;
		}
		
		$markers = $this->getMarkers($contents);
		$fragments = $this->getFragments($markers, $contents);
		
		return ['contents' => $contents, 'markers' => $markers, 'fragments' => $fragments];
	}
	
}
