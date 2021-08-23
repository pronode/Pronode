<?php

namespace Pronode;

class Fragment {
	
	/**
	 * Fragment id in hash form: 066bce79
	 * This is stored in tag's pn_fragment attribute:
	 * <tag pn_fragment="066bce79" pn_origin="{{origin}}">{{someVar}}</tag>
	 */
	public string $id;
	
	/**
	 * Fragment start position inside Template contents (char index)
	 */
	public int $pos;
	
	
	/**
	 * Fragment end position inside Template contents (char index)
	 */
	public int $end;
	
	
	/**
	 * HTML contents
	 */
	public string $contents = '';
	
	
	/**
	 * Marker occurences found inside that fragment
	 */
	public array $markers = [];
	
	
	/**
	 * Parent Template pointer
	 */
	public Template $template;
	
	
	public function __construct(Template $template, int $pos) {
		
		$this->template = $template;
		$this->pos = $pos;
	
	}
	
	
	/**
	 * Generates an unique Fragment identifier basing on Fragment->contents
	 * 
	 * @param string $contents Fragment contents
	 */
	public function getId() {
		
		return static :: getIdStatic($this->contents);
	}
	
	
	/**
	 * Generates an unique Fragment identifier basing on $contents string.
	 *
	 * @param string $contents Fragment contents
	 */
	public static function getIdStatic(string $contents) {
		
		if (!$contents) {
			
			throw new \ErrorException("Fragment contents must not be empty in order to encode an id");
		}
		
		// Strip pn_fragment and pn_origin attributes:
		$stripped = preg_replace('/ pn_fragment=\"(.*?)\" pn_origin=\"(.*?)\"/', '', $contents);
		
		return substr(md5($stripped), 0, 8); // 8 should be enough to avoid collisions
	}
	
	
	/**
	 * Returns array of nested Fragments inside current fragment
	 * 
	 * @return Fragment[]
	 */
	public function findNested() : array {
		
		$nested = [];
		
		foreach ($this->template->fragments as $fragment) {
			
			if ($fragment->pos > $this->pos && $fragment->end < $this->end) {
				
				$nested[] = $fragment;
			}
		}
		
		return $nested;
	}
	
	
	/**
	 * Finds parent Fragment
	 * 
	 * @return Fragment|NULL
	 */
	public function findParent() : ?Fragment {
		
		$parent = null;
		
		foreach ($this->template->fragments as $fragment) {
			
			if ($fragment->pos < $this->pos && $fragment->end > $this->end) {
				
				$parent = $fragment;
			}
			
		}
		
		return $parent;
		
	}
	
	
	/**
	 * Returns true if this Fragment is nested inside any parent Fragment
	 * 
	 * @return bool
	 */
	public function isNested() : bool {
		
		if ($this->findParent()) return true;
		
		return true;
	}
	
	
	/**
	 * Returns true if there are any nested Fragments inside this one.
	 *
	 * @return bool
	 */
	public function hasChildren() : bool {
		
		if ($this->findNested()) return true;
		
		return false;
	}
	
	
	/**
	 * Returns array of Fragment children
	 */
	public function findChildren() : array {
		
		$nested = $this->findNested();
		
		$children = [];
		
		foreach ($nested as $fragment) {
			
			if ($fragment->id != $this->id && $fragment->findParent() == $this) {
				
				$children[] = $fragment;
			}
		}
		
		return $children;
	}
	
}