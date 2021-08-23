<?php 

namespace Pronode;

class Root extends Node {
	
	/**
	 * Ghost flag to prevent dumping Ghost Root on __destruct
	 */
	public bool $isGhost = false;
	
	
	function __construct($data) {
		
		parent::__construct($data);
		
		// Ghost operations:
		$ghost = $this->ghostLoad();
		$this->ghostApply($ghost);
	}
	
	
	public function __destruct() {
		
		if ($this->isRoot() && !$this->isGhost) {
			
			$this->ghostDump();
		}
	}
	
	
	/**
	 * Applies Ghost to the Root by creating a App Tree Skeleton
	 * where every Node has ->ghost scalar property set to the value
	 * generated with previous Request.
	 * 
	 * Ghost should be loaded with ->ghostLoad() method first.
	 * 
	 * @param Root|null $ghost
	 * @return bool
	 */
	protected function ghostApply(?Root $ghost) : bool {
		
		if ($ghost === null) return false;
		
		$this->children = $ghost->children;
		
		foreach ($this->children as &$child) {
			
			$child->parent = $this;
		}
		
		return true;
	}
	
	
	/**
	 * Dumps serialized Ghost Root to the current Session Ghost Cache file.
	 * 
	 * Returns the file_put_contents() result.
	 * 
	 * @return int 
	 */
	protected function ghostDump() : int {
		
		$dir = $this->getClassDir($this->data);
		
		$file = $dir.DIRECTORY_SEPARATOR.'ghost.cache';
		
		return file_put_contents($file, serialize($this));
	}
	
	
	/**
	 * Loads unserialized Ghost Root from the current Session Ghost Cache file.
	 * 
	 * @return Root|NULL
	 */
	protected function ghostLoad() : ?Root {
		
		$dir = $this->getClassDir($this->root()->data);
		
		$file = $dir.DIRECTORY_SEPARATOR.'ghost.cache';
		
		if (!file_exists($file)) return null;
		
		$serialized = file_get_contents($file);
		$ghost = unserialize($serialized);
		$ghost->isGhost = true;
		
		return $ghost;
	}
	
	
}