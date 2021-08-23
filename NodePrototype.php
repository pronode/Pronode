<?php

namespace Pronode;

abstract class NodePrototype {
	
	/**
	 * Wrapped Object / Array / Scalar
	 * 
	 * @var mixed
	 */
	public $data;
	
	
	/**
	 * Parent Node
	 * eg. after "article,12.title" (article,12) is parent of (title)
	 * 
	 * @var Node|NULL
	 */
	public ?Node $parent = null;
	
	
	/**
	 * Array of child Nodes
	 * (title) is child of (article,12)
	 * Also, its the first child if the first executed branch was "article,12.title"
	 * Children are used for caching the results of branch executing.
	 *
	 * @var Node[]
	 */
	public array $children = [];
	
	
	/**
	 * Resource name in the context of parent Node.
	 * 
	 * Assuming that Parent node holds an User object, then creating a branch user.age causes
	 * a creation of Child Node (int age) and the resource name of that Node is "age".
	 * 
	 * @var string
	 */
	public string $resourceName;
	
	
	/**
	 * Checks whether given child exists among ->children[] and is not null
	 * 
	 * @param $resourceName
	 * 
	 * @return bool
	 */
	public function childExists(string $resourceName) : bool {
		
		return isset($this->children[$resourceName]); // NULL-sensitive
	}
	
	
	/**
	 * Returns an array of every Node from Root to current one.
	 * First element is always Root.
	 *
	 * @return Node[]
	 */
	public function getPath() : array {
		
		$path = [];
		
		$node = $this;
		
		do {
			
			$path[] = $node;
			
		} while ($node = $node->parent);
		
		return $path;
	}
	
	
	/**
	 * Returns an array of all branches of the Tree
	 *
	 * @return Node[][]
	 */
	public function getBranches() : array {
		
		$results = [];
		
		$leaves = $this->getLeaves();
		
		foreach ($leaves as $leaf) {
			
			$results[] = $leaf->getBranch();
		}
		
		return $results;
	}
	
	
	/**
	 * Get descendant Node with given Command or NULL if descendant doesn't exists.
	 *
	 * @param Command $command
	 * @return Node|NULL Descendant Node
	 */
	public function getDescendant(string $path) : ?Node {
		
		$command = new Command($path);
		
		$node = $this;
		
		do {
			
			if (!$node->childExists($command->resourceName)) return null;
			
			$node = $node->children[$command->resourceName];
			
			$command = $command->next();
			
		} while ($node && $command);
		
		
		return $node;
	}
	
	
	/**
	 * Returns an array of all descendant Nodes of current Node
	 *
	 * @return Node[]
	 */
	public function getDescendants() : array {
		
		$results = [];
		
		$children = $this->children;
		
		foreach ($children as $child) {
			
			$results[] = $child;
			$results = array_merge($results, $child->getDescendants());
			
		}
		
		return $results;
	}
	
	
	/**
	 * Returns an array of all child-free nodes in a tree starting from current Node.
	 *
	 * @return Node[]
	 */
	public function getLeaves() : array {
		
		$results = [];
		
		foreach ($this->children as $child) {
			
			if ($child->isLeaf()) {
				
				$results[] = $child;
				
			} else {
				
				$results = array_merge($results, $child->getLeaves());
			}
		}
		
		return $results;
	}
	
	
	/**
	 * Returns an array of all child-free children of current Node.
	 *
	 * @return Node[]
	 */
	public function getLeavesOwn() : array {
		
		$results = [];
		
		foreach ($this->children as $child) {
			
			if ($child->isLeaf()) {
				
				$results[] = $child;
			}
		}
		
		return $results;
	}
	
	
	/**
	 * Gets child of given $resourceName from ->children array.
	 * Child must exist - otherwise undefined index error will occur.
	 */
	protected function getChild($resourceName) : Node {
		
		return $this->children[$resourceName];
	}
	
	
	/**
	 * Finds and returns the final parent of the current Node.
	 */
	public function root() : Node {
		
		if ($this->isRoot()) return $this;
		
		return $this->parent->root();
	}
	
	
	/**
	 * Checks whether the Node is also the Root
	 */
	protected function isRoot() : bool {
		
		return !isset($this->parent);
	}
	
	
	/**
	 * Check whether the Node is leaf at the moment of checking.
	 * Leaf is a Node with no children.
	 */
	protected function isLeaf() : bool {
		
		return empty($this->children);
	}
	
	
	/**
	 * Selects all descendant Nodes which match given types.
	 * Ex. ['View', 'string'] returns all Nodes of data-type 'Node' or 'string'.
	 * 
	 * Note that supported types are:
	 * boolean|integer|double|string|array|resource|NULL|object|__CLASS__
	 * 
	 * Use 'scalar' to select 'integer', 'string', 'float' and 'bool';
	 * Use 'Namespace/Class' to select objects of given class.
	 * 
	 * @param string[] $types
	 */
	public function select(array $types = []) {
		
		$descendants = $this->getDescendants();
		
		if (empty($types) || in_array('*', $types)) {
			
			return $descendants;
		}
		
		$results = [];
		
		if (in_array('scalar', $types)) {
			
			$types = array_merge($types, ['integer', 'float', 'bool', 'string', 'null']);
		}
		
		foreach ($descendants as $node) {
			
			$type = get_type($node->data);
			
			if (in_array($type, $types)) {
				
				$results[] = $node;
				continue;
			}
			
			if (is_object($node->data) && in_array('object', $types)) {
				
				$results[] = $node;
			}
		}
		
		return $results;
	}
	
	
	/**
	 * Adopts given node by setting parent and child relation.
	 *
	 * @param Node $node Node to addopt
	 * @param string $resourceName
	 * @return bool
	 */
	protected function adopt(Node &$node, string $resourceName) : bool {
		
		$node->parent = $this;
		$this->children[$resourceName] = $node;
		
		return true;
	}
	
	
	/**
	 * Destroys current Node.
	 */
	public function destroy() : void {
		
		$this->data = null;
		
		unset($this->parent->children[$this->resourceName]);
	}
	
}