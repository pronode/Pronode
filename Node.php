<?php

namespace Pronode;

class Node extends NodePrototype {
	
	/**
	 * Pronode Universal Methods packs:
	 */
	use extString;		# string manipulation methods pack
	use extDateTime;	# dateTime manipulation methods pack
	use extArray;		# array manipulation methods pack
	use extNumber;		# number manipulation methods pack
	use extHttp;		# http-related methods pack
	use extDebug;		# debuging methods pack
	use extCode;		# 
	
	/**
	 * Pronode system-related methods:
	 */
	use extCache;		# cache-related methods
	
	/**
	 * Turn on or off Pronode caching mechanism globally.
	 * False: cache off
	 */
	const CACHE = false;
	
	/**
	 * Turn on or off Pronode routing mechanism for ->execRequest globally.
	 */
	const ROUTING = true;
	
	
	/**
	 * Node origin
	 */
	public string $origin;
	
	
	/**
	 * When command is executed with "/" selector, $branchNext holds an information about what
	 * Node will {{next}} reffer to.
	 */
	protected $branchNext;
	
	
	/**
	 * Expiration time of cached Node.
	 */
	public $expires;
	
	
	/**
	 * Ghost value of the data from previous Request.
	 * Only scalars are ghosted. Other types are nulled.
	 * 
	 * @var integer|float|bool|null|Pronode/View
	 */
	public $ghost;
	
	
	public function __construct($data, Node $parent = null, $resourceName = '') {
		
		$this->data = &$data;
		
		$this->resourceName = $resourceName;
		
		if ($parent) {
			
			$this->parent = &$parent;
			$this->parent->children[$resourceName] = &$this;
		}
		
		// Just for an easier var_dumping:
		$this->origin = $this->origin();
		
		# $this->__setup();
	}
	
	
	/**
	 * Finds Nodes from all type-matched descendants, which changed their wrapped data value
	 * compared to the ghost.
	 * 
	 * Node must provide not-null ghost property.
	 * 
	 * Since only scalars and Pronode\Views "remember" their ghost value, Nodes which 
	 * hold any other datatypes will be skipped - @see __sleep() to check dump cfg
	 * 
	 * @param array $types boolean|integer|double|string|array|resource|NULL|object|__CLASS__
	 * 
	 * @return Node[]
	 */
	public function getChangedDescendants(array $types = []) : array {
		
		$descendants = $this->select($types);
		
		$changed = [];
		
		foreach ($descendants as $node) {
			
			if (isset($node->ghost)) {
				
				if ($node->ghost != $node->data) {
					
					$changed[] = $node;
				}
			}
		}
		
		return $changed;
	}
	
	
	/**
	 * 
	 * @return number|boolean|NULL|Pronode/View
	 */
	public function ghost() {
		
		return $this->ghost;
	}
	
	
	/**
	 * Setup Node basing on wrapped data properties.
	 *
	 * Configuration stuff done here.
	 * Dependency Injection done here.
	 * Directives done here.
	 */
	protected function __setup() : void {
		
		if (is_null($this->data)) return;
		
		// For root:
		if ($this->isRoot()) { // isRoot() method just checks if there is ->parent set.
			
			// Setting default cache configuration:
			$this->cacheConfiguration = $this->getCacheConfiguration();
			
			// Setting default templates dir configuration:
			if (is_object($this->data)) {
				
				$this->templatesDir = self::getClassDir($this->data);
			} else {
				
				$this->templatesDir = __DIR__;
			}
			
		// For every child:
		} else {
			
			// Inherit cache configuration from parent:
			$this->cacheConfiguration = $this->parent->cacheConfiguration;
			
			// Inject all required dependecies from root data:
			$this->injectDependencies();
			
			// Transmit all properties given in root's pn_transmit directive to created Nodes:
			$this->transmit();
		}
		
		// For everybody:
	}
	
	
	/**
	 * Checks whether client hit refresh button to reload the page:
	 * @return boolean
	 */
	public static function refreshRequested() : bool {
		
		return isset($_SERVER['HTTP_CACHE_CONTROL']) && $_SERVER['HTTP_CACHE_CONTROL'] === 'max-age=0';
	}
	
	
	/**
	 * Get list of all available resources (public, non-static) without pn_access check.
	 * Combines ->data properties, ->data methods and Node->methods.
	 * Returns array of elements:
	 * ['resourceName' => 'key|property|method|xmethod'].
	 * 
	 * Types:
	 * 'key' - array key
	 * 'property' - public ->data property
	 * 'method' - public non-static ->data method
	 * 'pn_method' - public non-static Node method
	 */
	public function getAvailableResources() : array {
		
		$result = [];
		
		// Those are available for any ->data type:
		$nodeMethods = $this->getPublicNonStaticMethods(new Node(null));
		
		foreach ($nodeMethods as $value) {
			
			$result[$value] = "pn_method";
		}
		
		// Array data type:
		if (is_array($this->data)) {
			
			foreach(array_keys($this->data) as $value) {
				
				$result[$value] = "key";
			}
		}
		
		// Object data type:
		if (is_object($this->data)) {
			
			$properties = array_keys(get_object_vars($this->data));
			$dataMethods = $this->getPublicNonStaticMethods($this->data);
			
			foreach ($properties as $value) {
				
				$result[$value] = "property";
			}
			
			foreach ($dataMethods as $value) {
				
				$result[$value] = "method";
			}
		}
		
		return array_reverse($result); // just to make ->data fields appear first
	}
	
	
	/**
	 * Get list of all public and non-static methods of given $object.
	 * Returns an array of $key => $value such as:
	 * ['methodName' => 'methodName']
	 */
	public static function getPublicNonStaticMethods($object) : array {
		
		$result = [];
		
		$reflection = new \ReflectionClass($object);
		
		$public = $reflection->getMethods(\ReflectionMethod::IS_PUBLIC);
		$static = $reflection->getMethods(\ReflectionMethod::IS_STATIC);
		
		foreach ($public as $method) {
			
			if (!\strtok($method->name, '__')) { // skip methods containing __ string
				
				$result[$method->name] = $method->name;
			}
		}
		
		foreach ($static as $method) {
			
			unset($result[$method->name]);
		}

		unset($result['dump']);
		
		return $result;
	}
	
	
	/**
	 * Returns an ID of the Node.
	 * Note that ID may be unique for current branch only in case wrapped object doesn't
	 * provide ->id property.
	 */
	public function nodeId() : ?string {
		
		if (!is_object($this->data)) return null;
		
		$class = get_class($this->data);
		
		// Try to get id from wrapped object:
		@$id = $this->data->id;
		
		if (!isset($id)) {
			
			# $id = $this->num2alpha($this->number());
			$id = '';
		}
		
		return $class.$id;
		
	}
	
	
	/**
	 * Transmits properties from root to the Node.
	 * Used for global dependency broadcasting.
	 * Used on __setup().
	 */
	protected function transmit() {
		
		$root = $this->root();
		
		if (isset($root->pn_transmit)) {
			foreach ($root->pn_transmit as $resourceName) {
				
				$this->{$resourceName} = &$root->{$resourceName};
			}
		}
	}
	
	
	/**
	 * Injects dependencies from root basing on pn_di directive.
	 * pn_di = ['dependency1', 'dependency2'].
	 * Dependencies must be "public" in order to be "grabbed" from root.
	 */
	protected function injectDependencies() {
		
		// Inject all required dependecies from root data:
		if (is_object($this->data) && !empty($this->data->pn_di)) { // We dont use is_array since it's slow asfuk
			
			$root = $this->root();
			
			if (is_object($root->data)) {
				
				foreach ($this->data->pn_di as $dependency) {
					
					# echo "<p> Injecting dependency $dependencyName </p>";
					$this->data->{$dependency} = &$root->data->{$dependency};
					$this->data->{'access'.$dependency} = false; // Default dependency access configuration
					$this->data->{'access'.ucfirst($dependency)} = $root->data->{'access'.$dependency}; // Copy access configuration
				}
			}
		}
	}
	
	
	/**
	 * Search for parent property up the tree till it finds proper not-null value.
	 */
	protected function inherit(string $propertyName) {
		
		if ($this->isRoot()) return $this->{$propertyName};
		
		if (isset($this->parent->{$propertyName})) return $this->parent->{$propertyName};
		
		return $this->parent->inherit($propertyName);
	}
	
	
	/**
	 * Defines which Node vars are to be serialized and dumped to cache.
	 */
	public function __sleep() {
		
		# $vars = get_object_vars($this);
		
		$toSerialize = ['resourceName', 'parent', 'children'];
		
		$this->ghost = $this->data;
		
		// Serialize scalar data:
		if (is_scalar($this->ghost)) $toSerialize[] = 'ghost';
		
		// Serialize data of type Pronode/View:
		if (($this->ghost instanceof View)) $toSerialize[] = 'ghost';
		
		return $toSerialize;
	}
	
	
	
	
	
	/**
	 * Defines how to display certain ->data types when casting Node to string:
	 */
	public function __toString() {
		
		if ($this->data instanceof View) {
			
			$view = $this->data;
		}
		
		$view = new View($this, $this->getTemplate());
		
		return $view->output;
	}
	
	
	/**
	 * Returns object class definition directory.
	 */
	public static function getClassDir($object) {
		
		$reflection = (new \ReflectionClass(get_class($object)))->getFileName();
		$classDir = dirname($reflection);
		
		return $classDir;
	}
	
	
	/**
	 * Checks if ->data provides given $target.
	 * If ->data is object, property_exists and method_exists are checked.
	 * if ->data is array, key_exists is checked.
	 */
	protected function data_provides($target) : bool {
		
		if (is_object($this->data)) {
			
			if (isset($this->data->{$target})) {
				
				return true;
			}
				
			if (method_exists($this->data, $target)) {
				
				return true;
			}
				
		}
		
		if (is_array($this->data)) {
			
			if (array_key_exists($target, $this->data)) return true;
		}
		
		return false;
	}
	
	
	/**
	 * Checks if Node provides given $target either by its own method
	 * or by accessing wrapped ->data method / property.
	 */
	protected function provides($target) : bool {
		
		if (method_exists($this, $target)) {
			
			return true;
		}
		
		return $this->data_provides($target);
	}
	
	
	/**
	 * Removes all child Nodes from children array, where data is null.
	 * This method is called before caching Root Node to prevent exploits like spamming system
	 * with calling not-existing branches.
	 */
	protected function clearNullChildren() : void {
		
		foreach ($this->children as $key => $child) {
			
			if ($child->data === null) unset($this->children[$key]);
		}
	}
	

	/**
	 * Returns an absolute origin of Node.
	 * Absolute origin is an exact "address" of data in Pronode system.
	 */
	public function origin() : string {
		
		if ($this->isRoot()) return '';
		
		$node = $this;
		
		$origin = '';
		
		do {
			
			if ($node->resourceName != '') {
				
				$origin = '.'.$node->resourceName.$origin;
			}
			
		} while ($node = $node->parent);
		
		return $origin;
	}
	
	
	/**
	 * Check if wrapped object allows Node to get $target method or property.
	 * ->data must implement access[Target] method or property.
	 * Access is granted only if access[Target] method returns true.
	 *
	 * Set ->data->access = false; to deny access to every $target inside data.
	 * Define ->data->access() method to dynamicaly determine access.
	 */
	protected function checkAccessConfiguration(string $target = '') {
		
		# echo "<p> Checking access to $target </p>";
		
		/**
		 * Predefined access configuration for certain targets:
		 */
		switch ($target) {
			
			// Deny access to specific targets:
			case 'exec': return false;
			case 'execRequest': return false;
		}
		
		/**
		 * Access is true by default:
		 */
		$configurationVar = 'access';
		$configuration = true;
		
		if (!is_object($this->data)) return $configuration;
		
		// Whole object check:
		if (method_exists($this->data, $configurationVar)) {
			
			$configuration = call_user_func($this->data, $configurationVar);
		} else
			
			if (property_exists($this->data, $configurationVar)) {
				
				$configuration = $this->data->$configurationVar;
			}
		
		// Specific check:
		if ($target) {
			
			$method = $configurationVar.ucfirst($target);
			$property = $configurationVar.ucfirst($target);
			
			if (method_exists($this->data, $method)) {
				
				$configuration = call_user_func($this->data, $method);
				return $configuration;
			}
			
			if (property_exists($this->data, $property)) {
				
				
				$configuration = $this->data->$property;
				return $configuration;
			}
		}
		
		return $configuration;
	}
	
	
	/**
	 * Gets directive setup of wrapped object.
	 * 
	 * If $target is specified, this will look for resource-specific configuration, 
	 * then (if its not provided), for general conf.
	 * 
	 * Objects can provide directives via methods and properties.
	 * Arrays can provide directives via key => value.
	 * Scalars are ignored.
	 * 
	 * ('pn_cache', 'html') will look for ->pn_cacheHtml directive, then for ->pn_cache.
	 */
	public function getDirective($directiveName, $target = '') {
		
		$_directiveName = $directiveName . ucfirst($target);
		$directive = null;
		
		if (is_object($this->data)) {
			
			if (isset($this->data->$_directiveName)) {
				
				$directive = $this->data->$_directiveName;
				
			} elseif (method_exists($this->data, $_directiveName)) {
				
				$directive = $this->data->$_directiveName($target);
				
			}
			
		} elseif (is_array($this->data)) {
			
			if (isset($this->data[$_directiveName])) {
				
				$directive = $this->data[$_directiveName];
			}
		} else {
			
			return false;
		}
		
		// If there is no target-specific directive, look for general:
		if (!isset($directive) && !empty($target)) {
			
			return $this->getDirective($directiveName);
		}
		
		return $directive;
	}
	
	
	/**
	 * Loads directive settings from Root Node.
	 * 
	 * Use this method when you want directive to act globaly for all Nodes
	 * and be set only in one place - your root object.
	 */
	public function getDirectiveRoot($directiveName, $target) {
		
		return $this->root()->getDirective($directiveName, $target);
	}
	
	
	/**
	 * Gets information about target caching from object pn_cache directive.
	 * 
	 * To enable caching of EVERY target (property / method / special method), set data->cache property.
	 * To enable caching of certain target, set data->cache[Targetname] property.
	 *
	 * Use data->cache = true or data->cache = "public" to cache every target publicly
	 * Use data->cacheTargetname = true to cache specified property / method / special method publicly
	 *
	 * Use data->cache = "personal" to cache every target in client $_SESSION // TODO
	 * Use data->cache = "public" (equals to cache = true)
	 * Use data->cache = "public 1 hour" to refresh data after 3600 seconds
	 *
	 */
	public function getCacheConfiguration(string $target = '') : ?\stdClass {
		
		if (!self :: CACHE) return null;
		
		// Predefined cache configuration for certain targets:
		switch ($target) {
			
		}
		
		['refmenu 10 sec deep public'];
		['refmenu 10 sec deep public', 'otherResource,param 1 hour'];
		 'refmenu 10 sec deep public; otherResource,param 1 hour';
		
		$directive = $this->getDirective('pn_cache', $target);
		
		$conf = new \stdClass();
		$conf->type = 'public';
		$conf->deep = false;
		
		
		if (is_string($directive)) {
			
			$directiveArray = explode(';', $directive);
			
			// Look for target-matching directive:
			foreach ($directiveArray as $element) {
				
				$targ = strtok($directive, ' ');
				# if ($this->targetMatch($t)) {}
			}
			
		}
		
		if (is_string($directive)) {
			
			$target = strtok($directive, ' ');
			
			$count = 0;
			$directive = str_replace('public', '', $directive, $count);
			if ($count) $conf->type = 'public';
			
			$directive = str_replace('personal', '', $directive, $count);
			if ($count) $conf->type = 'personal';
			
			$directive = str_replace('deep', '', $directive, $count);
			if ($count) $conf->deep = true;
			
			$conf->time = strtotime($directive); // there should've left only strtotime-acceptable string
			
			return $conf;
			
		} elseif ($directive === true) { // Default settings for TRUE:
			
			$conf->time = time() + 3600; // Set expiration time to +1 hour
			
			return $conf;
		}
		
		return null; // Default
	}
	
	
	/**
	 * Stores Node in cache
	 */
	protected function toCache() {
		
		return $this->cache_set($this->origin(), $this);
	}
	
	
	/**
	 * Gets child Node from cache and sets it among ->children[].
	 * Returns that Node.
	 */
	protected function getChildFromCache($originRelative) : ?Node {
		
		$origin = $this->origin().$originRelative;
		
		$node = $this->getNodeFromCache($origin);
		
		if (!$node) return null;
		
		$node->parent = $this;
		$this->children[$originRelative] = $node;
		
		return $node;
	}
	
	
	/**
	 * Gets Node of given $origin from cache.
	 * If Node is expired, cache file will be deleted and null will be returned.
	 */
	protected function getNodeFromCache($origin) : ?Node {
		
		$node = $this->cache_get($origin);
		
		if (!$node) return null;
		
		if (time() > $node->expires) {
			
			$this->cache_delete($origin);
			return null;
		}
		
		return $node;
	}
	
	
	/**
	 * Returns an array of changed Views among descendant Nodes.
	 * 
	 * @return View[]
	 */
	public function getChangedViews() : array {
		
		$nodes = $this->select(['Pronode/View']);
		
		$changedViews = [];
		
		foreach ($nodes as $node) {
			
			if (($view = $node->data) instanceof View) {
				
				$ghostView = $node->ghost;
				
				if (!$ghostView) {
					
					$viewChanged = true;
					
				} else {
					
					$viewChanged = $view->output != $ghostView->output;
				}
				
				if ($viewChanged && empty($view->subviews)) { 
					
					$changedViews[] = $view;
				}
			}
		}
		
		return $changedViews;
	}
	
	
	/**
	 * Returns an array of Fragments containing Views changed comparing to the Ghost
	 * 
	 * @return Fragment[]
	 */
	public function getChangedFragments() : array {
		
		$changedViews = $this->getChangedViews();
		$changedFragments = [];
		
		foreach ($changedViews as $view) {
			
			foreach ($view->triggeredBy as $viewTrigger) {
				
				$changedFragments[$viewTrigger->fragment->id] = $viewTrigger->fragment;
			}
		}
		
		return $changedFragments;
	}
	
	
	
	
	
	
	
	/**
	 * Tries to get "target" from Node.
	 * Despite of what kind of data is wrapped by Node, target can reffer to:
	 * - an object property
	 * - an object method w/wo params
	 * - Node special method
	 * - array key
	 *
	 * Important!
	 * Be careful using magic methods like __GET or __CALL while implementing your object's class.
	 * If you want to use such with Pronode, please notice that $target CANNOT reffer to any of
	 * Node's special methods. For example, your __GET wont be executed if target's name is 'html'
	 * or 'view', since they are Node special-use functions.
	 * Moreover, they can slow down your app since Node doesn't know why your __GET or __CALL
	 * returned NULL (it can be an actual result or just unsupported property or method).
	 *
	 * @param string $target object method / property / array's key
	 * @param array $params array of parameteres
	 * @return mixed
	 */
	protected function get(string $target, array $params = []) {
		
		$data = null;
		$caseFlag = 0;
		
		$isObject = is_object($this->data);
		
		// Object method:
		if ($isObject && method_exists($this->data, $target)) {
			
			$data = call_user_func_array([$this->data, $target], $params);
			
			$caseFlag = 1;
			
		} else
			
			// Object property:
			if ($isObject && property_exists($this->data, $target)) {
				
				$data = $this->data->$target;
				
				$caseFlag = 2;
				
			} else
				
				// Object magic method (__CALL)
				if ($isObject && $params && method_exists($this->data, '__CALL') && !method_exists($this, $target)) {
					
					$data = $this->data->__CALL($target, $params);
					
					$caseFlag = 3;
					
				} else
					
					// Object magic property (__GET)
					if ($isObject && method_exists($this->data, '__GET') && !method_exists($this, $target)) {
						
						$data = $this->data->$target;
						
						$caseFlag = 4;
						
					} else
						
						// Array property:
						if ((is_array($this->data) || $this->data instanceof \ArrayAccess) && array_key_exists($target, $this->data)) {
							
							$data = &$this->data[$target];
							
							$caseFlag = 5;
						}
					
		// If $data is still null after __GET or __CALL use:
		if ($data === null && in_array($caseFlag, [0, 3, 4])) {
			
			// If target is unreachable in data scope, ask current Node for method.
			// Calling static Pronode methods via get is disallowed by design.
			if (method_exists($this, $target) && !(new \ReflectionMethod($this, $target))->isStatic()) {
				
				$data = call_user_func_array([$this, $target], $params);
			} else
				
				// If target is unreachable in Node scope, ask Root:
				if (!$this->isRoot()) {
					
					// Decided to comment below line out. I think this is just too messy.
					// Use root.command to directly ask root for property/method.
					# $data = $this->root()->exec($this->createCommand($target, $params));
				}
			
		}
		
		return $data;
	}
	
	
	/**
	 * Finds next Node on the Request Branch.
	 * Can be used to dynamicaly change the view.
	 * You can use {{next}} marker in your template and get desired view as the location.href changes:
	 * /articles - {{next}} will reference to $app->articles() method (eg. showing the list of articles)
	 * /article,12 - {{next}} will reference to $app->article(1) method (eg. showing full article of ID 12);
	 */
	public function next() : ?Node {
		
		if (isset($this->branchNext)) { // ->branchNext is set in ->exec() when "/" selector is used
			
			$command = new Command($this->branchNext);
			
			// Child must have been created on ->execRequest() 
			if ($this->childExists($command->resourceName)) {
				
				return $this->children[$command->resourceName];
			}
		}
		
		return null;
	}
	
	
	/**
	 * Get last Node on the Request Branch.
	 */
	public function last() : Node {
		
		// start context:
		$lastNode = $this->root();
		
		while ($next = $lastNode->next()) {
			
			$lastNode = $next;
		}
		
		return $lastNode;
	}
	
	
	/**
	 * Finds the last Node on the Request Branch that provides given $target.
	 * Petforms ->data_provides check on every Node starting from the last Node on the Branch
	 * till the provider is found.
	 */
	public function lastProvider($target) : ?Node {
		
		$node = $this->last();
		
		do {
			
			if ($node->data_provides($target)) {
				
				return $node;
			}
			
		} while ($node = $node->parent());
		
		return null;
	}
	
	
	
	
	/**
	 * Returns the parent of current Node
	 */
	public function parent() : ?Node {
		
		return $this->parent;
	}
	
	
	/**
	 * Upper-search for parent of specific name.
	 */
	public function parentFind($name) : ?Node {
		
		if (!$this->parent) return null;
		if ($this->parent->getName() == $name) return $this->parent;
		
		return $this->parent->{__FUNCTION__}($name);
	}
	
	
	/**
	 * Upper-search for the parent of type Object
	 */
	public function parentFindObject() : ?Node {
		
		if (!$this->parent) return null;
		
		if (is_object($this->parent->data)) return $this->parent;
		
		return $this->parent->{__FUNCTION__}();
	}
	
	
	/**
	 * Upper-search for the parent of type Array
	 */
	public function parentFindArray() : ?Node {
		
		if (!$this->parent) return null;
		
		if (is_array($this->parent->data)) return $this->parent;
		
		return $this->parent->{__FUNCTION__}();
	}
	
	
	/**
	 * Upper-search for the parent of type Iter
	 */
	public function parentFindIter() : ?Node {
		
		if (!$this->parent) return null;
		
		if (is_iter($this->parent->data)) return $this->parent;
		
		return $this->parent->{__FUNCTION__}();
	}
	
	
	/**
	 * Upper-search for the parent of type Assoc
	 */
	public function parentFindAssoc() : ?Node {
		
		if (!$this->parent) return null;
		
		if (is_assoc($this->parent->data)) return $this->parent;
		
		return $this->parent->{__FUNCTION__}();
	}
	
	
	/**
	 * Exports every branch of Pronode application from Root to all of the Leafs.
	 * Returns and array with pair key => value.
	 * Key is an absolute origin of Node.
	 * Value is that Node data.
	 *
	 * 'article,1.title' => Node("Space travels and how to avoid them.");
	 * 
	 * @return Node[]
	 */
	public function branchExport() : array {
		
		$result = [];
		
		if ($this->isLeaf()) {
			
			$result = [$this->origin() => $this];
			
		} else {
			
			foreach ($this->children as $child) {
				
				$result = array_merge($result, $child->branchExport());
				
			}
		}
		
		return $result;
		
	}
	
	
	/**
	 * Executes Pronode command in safe mode.
	 * Use this method when executing any command from outside, $_GET['request'] especially.
	 * Forbids any command containing exploitable character to pass.
	 *
	 * Uses ->data->pn_route directive to translate commands.
	 */
	public function execRequest($request = 'html') {
		
		if ($request === '/favicon.ico') die(http_response_code(404));
		
		# if ($request == '/' || $request === '') $request = 'html';
		
		// Check for any forbidden char occurence:
		$check = preg_match('/[$<>{}"\']/', $request);
		
		if ($check) die('Execution terminated: request contains forbidden characters.');
		
		return $this->exec($request, self::ROUTING);
		
	}
	
	
	
	protected static $getActionsExecuted = [];
	protected function execGetActionsOnce() : ?bool {
		
		$id = $this->nodeId();
		
		if (!key_exists($id, self :: $getActionsExecuted)) {
			
			self :: $getActionsExecuted[$id] = true;
			return $this->execGetActions();
		}
		
		return null;
	}
	
	/**
	 * Executing actions provided by $_GET.
	 * An action can be any wrapped object's public method with positive access.
	 * This method is used on current branch nodes.
	 * Allows to perform side tasks without "leaving" a branch.
	 * 
	 * Returns true if any action was executed.
	 * Returns false if no action was executed.
	 * 
	 * Note that action name must not contain _underscore char - this is due to parse_str changing . to _
	 */
	protected function execGetActions() : bool {
		
		if (!is_object($this->data)) return false;
		
		$result = false;
		
		foreach (array_keys($_GET) as $afterQuestionMarkAction) {
			
			if (strpos($afterQuestionMarkAction, '_')) {
				
				$input = str_replace('_', '.', $afterQuestionMarkAction);
				
				$command = new Command($input);
				
				var_dump($this->nodeId());
				
				if ($command->target == $this->nodeId()) {
					
					$nextCommand = $command->next();
					
					if (method_exists($this->data, $nextCommand->target)) {
						
						$this->exec($nextCommand->normalized);
						$result = true;
					}
				}
			}
			
		}
				
		return $result;
	}
	
	
	/**
	 * Executes Pronode command on the Node.
	 * Creates new node and joins it to the branch.
	 * Returns the last node of the branch.
	 * 
	 * If wrapped object doesn't provide specified resource, ->get used for obtaining data will 
	 * look for target in current Node's methods.
	 *
	 * Command examples:
	 * getArticle,12.title will return the title of an article (wrapped object must provide getArticle method)
	 */
	public function exec($input, $routing = false) : ?Node {
		
		// Normalize command to make sure that selector is provided:
		$command = new Command($input);
		
		// Translate command if ->data->route is provided
		# if ($routing) $command = $this->route($command);
		
		if ($command->target == '') return $this;
		
		// Set nextTarget if "/" selector was used
		if ($command->selector == "/") {
			
			$this->branchNext = $command->resourceName;
		}
		
		
		
		
		if ($command->resourceName == 'view,refmenu') {
			
			if ($this->childExists('view,refmenu')) {
				
				$node = $this->getChild('view,refmenu');
				$node->data = $node->ghost;
				return $node;
			}
		}
		
		
		// Obtaining child from children array (application life-cycle cache):
		if ($this->childExists($command->resourceName)) {
			
			$child = $this->getChild($command->resourceName);
			
			if ($child->data === null) {
				
				$child->refreshData();
			}
			
			# echo "<p> Obtaining {$this->origin()}<b>.{$command->resourceName}</b> from children </p>";
				
		} 
		
		// Get data or foreign Node reference:
		if (!isset($child)) {
			
			$data = $this->get($command->target, $command->params);
			
			if ($data instanceof Node) {
				
				$reference = $data;
				
			} else {
				
				$child = new Node($data, $this, $command->resourceName);
				
				# $this->children[$command->resourceName] = $child;
			}
			
		}
		
		if (isset($reference)) {
			
			$node = &$reference;
			
		} else {
			
			$node = $child;
		}
		
		// Process further if there is a command tail:
		if ($command->tail != '') {
			
			return $node->exec($command->tail, $routing);
			
			// If not, return created / cached Node:
		} else {
			
			return $node;
		}
	}
	
	
	public function refresh() {
		
		$this->refreshData();
		
		return $this->parent->exec($this->originRelative);
	}
	
	/**
	 * Re-obtains data wrapped by Node by asking its parent to re-run ->get with Node's origin data.
	 * Used automaticly by exec() to refresh data of not-cached objects.
	 */
	protected function refreshData() {
		
		if ($this->isRoot()) return; // Root Node data can't be refreshed.
		
		$this->lastRefresh = time();
		
		$command = new Command($this->resourceName);
		
		$data = $this->parent->get($command->target, $command->params);
		
		if ($data instanceof static);
		
		/**
		 * If data changed then all Node's children are assumed to be outdated, since theirs data
		 * depends on their parent's data.
		 * Therefore we flush children array.
		 */
		if ($data != $this->data) {
			
			/**
			 * Decided to comment that out: cache is cache, we assume that value might have changed
			 * but we get it from cache anyway so there is no need to flush cached children.
			 * TODO: think more of it
			 */
			//$this->children = [];
		}
		
		$this->data = $data;
		
		/* if ($this->parent->getCacheConfiguration($this->originTarget)) {
			
			$this->toCache();
		}
		
		$this->__setup(); */
	}
	
	
	public function getTemplate() {
		
		$path = $this->template_autoSelectFile();
		
		$template = TemplateFactory::load($path);
		
		return $template;
	}
	
	
	public function getView($templateName = '') {
		
		if ($templateName) {
			
			$path = $this->template_getDirectory().DIRECTORY_SEPARATOR.$templateName.'.html';
			
			$template = TemplateFactory::load($path);
			
		} else {
			
			$path = $this->template_autoSelectFile();
			
			$template = TemplateFactory::load($path);
		}
		
		$view = new View($this, $template);
		
		return $view;
		
	}
	

	/**
	 * Bundles Node's data with given template and returns HTML.
	 * PUG templates have priority over html files, so article.pug will be used if found.
	 * If no $templateName is specified, Pronode will assume that the name of template is the same
	 * as the name of a target or object class.
	 *
	 * If template is not available, this method will return raw Node data.
	 */
	public function view($templateName = '') {
		
		$view = $this->getView($templateName);
		
		return $view;
	}
	
	
	/**
	 * Calls the view method on the Root.
	 * @return string
	 */
	public function html() {
		
		$xhr = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest';
		
		if ($xhr || isset($_GET['xhr'])) {
			
			return $this->htmlXHR();
			
		} else {
			
			return $this->root()->exec('view')->data;
		}
		
	}
	
	
	protected function htmlXHR() {
		
		header('Content-Type: application/json');
		
		$changedViews = $this->root()->getChangedViews();
		
		$response = [];
		
		foreach ($changedViews as $view) {
			
			foreach($view->triggeredBy as $viewTrigger) {
				
				$change = new \stdClass();
				
				$change->pn_fragment = $viewTrigger->marker->fragment->id;
				$change->pn_origin = $viewTrigger->view->node->origin();
				$change->htmlPlacement = $viewTrigger->marker->htmlPlacement;
				$change->html = $viewTrigger->view->compileFragment($viewTrigger->marker->fragment);
				
				$response[] = $change;
			}
		}
		
		return json_encode($response);
		
	}
	
	/**
	 * Returns current context (current Node).
	 * Use {{this|templateName}} or {{this.view,templateName}} to "pass" current object to given template.
	 */
	public function this() {
		
		return $this;
	}
	
	
	public function value() {
		
		if (is_scalar($this->data)) return $this;
	}
	
	
	/**
	 * Force method is Pronode special method.
	 * It can be used to force displaying template since it always returns true.
	 * You can set marker like this: {{force|footer}} to easily include partials
	 * in your main template.
	 *
	 * TODO: cosider redirecting this command to root to prevent duplicated ->force branches.
	 */
	public function force() : bool {
		
		return true;
	}
	
	
	/**
	 * Get an automaticaly generated name of Node.
	 * Name is usually the same as "target" part of command used to create the Node.
	 * Only exception: root nodes are named by their wrapped object's class name (lcfist).
	 *
	 * This method is used for setting up default template for Node.
	 */
	public function getName() {
		
		if ($this->originTarget !== null) return $this->originTarget;
		if (is_object($this->data)) return lcfirst(get_class($this->data));
	}
	

	
	/**
	 * Checks if ->data->templateName is defined.
	 * If so, given templateName will be used to render view of ->data.
	 * If not, Pronode will use Node::getName to determine templateName.
	 */
	protected function template_checkName() {
		
		if ($this->data->templateName) return $this->data->templateName;
		
		return $this->getName();
	}
	
	
	/**
	 * Gets template directory to look for templates used to render views of ->data.
	 * Set your ->data->templateDir property to use custom template dir.
	 *
	 * Returns './templates' by default;
	 */
	protected function template_getDirectory() {
		
		if (isset($this->data->pn_templatesDir)) return $this->data->pn_templatesDir; // user-set dir
		
		if (is_object($this->data)) {
			
			$dir = self::getClassDir($this->data).DIRECTORY_SEPARATOR.'templates';
		} else {
			
			$dir = self::getClassDir($this->root()->data).DIRECTORY_SEPARATOR.'templates';
		}
		
		return $dir;
	}
	
	/**
	 * Looks for specified templateName in /templates DIR.
	 *
	 * Respects given order: .html. .jade, .pug
	 * If article.jade exists, not gonna load article.html.
	 * Returns file path.
	 */
	protected function template_selectFormat($templateName = '') : string {
		
		if (!$templateName) $templateName = $this->template_checkName();
		
		// $templateName cannot be path (anti-exploit):
		if (preg_match('/[\/]/', $templateName)) return false;
		
		$dir = $this->template_getDirectory();
		
		if (file_exists($path = $dir.'/'.$templateName.'.html')) return $path;
		if (file_exists($path = $dir.'/'.$templateName.'.jade')) return $path;
		if (file_exists($path = $dir.'/'.$templateName.'.pug')) return $path;
		
		return false;
	}
	
	
	/**
	 * Auto-select template file basing on (by priority):
	 * 1. ->data->templateName property if provided
	 * 2. ->originTarget for specific template case
	 * 3. get_class(->data) if is_object(->data)
	 *
	 * Returns path to template file.
	 * Pass $templateName as reference to obtain $templateName (ex. "article" is name of ::CLASS::/templates/article.html)
	 */
	protected function template_autoSelectFile(&$templateName = '') : string {
		
		$dir = $this->template_getDirectory();
		
		if (isset($this->data->templateName)) {
			
			$pathNoExt = $dir.DIRECTORY_SEPARATOR.$this->data->templateName;
			$templateName = $this->data->templateName;
			
			if (file_exists($path = $pathNoExt.'.html')) return $path;
			# if (file_exists($path = $pathNoExt.'.jade')) return $path;
			# if (file_exists($path = $pathNoExt.'.pug'))  return $path;
		}
		
		if (isset($this->originTarget)) {
			
			$pathNoExt = $dir.DIRECTORY_SEPARATOR.$this->originTarget;
			$templateName = $this->originTarget;
			
			if (file_exists($path = $pathNoExt.'.html')) return $path;
			# if (file_exists($path = $pathNoExt.'.jade')) return $path;
			# if (file_exists($path = $pathNoExt.'.pug'))  return $path;
		}
		
		if (is_object($this->data)) {
			
			$className = (new \ReflectionClass($this->data))->getShortName();
			
			$pathNoExt = $dir.DIRECTORY_SEPARATOR.$className;
			$templateName = $pathNoExt;
			
			if (file_exists($path = $pathNoExt.'.html')) return $path;
			# if (file_exists($path = $pathNoExt.'.jade')) return $path;
			# if (file_exists($path = $pathNoExt.'.pug'))  return $path;
		}
		
		return false;
	}
	
	
	/**
	 * Translates pretty command to Pronode command.
	 * Works similar to .htaccess RewriteRule directive.
	 * Performed at the begining of ->exec.
	 * ->data needs to implement ->pn_route table with pairs 'rule' => 'command', where:
	 * 	'rule' is regular expression, ex. 'article/(.*?)'
	 * 	'command' is the replacement, ex. 'getArticleByTitle,$1.html'
	 *
	 * @param Command
	 */
	protected function route(Command $command) : Command {
		
		
		if (!empty($this->data->pn_route)) {
			
			foreach ($this->data->pn_route as $rule => $replacement) {
				
				$rule = Command :: normalize($rule, $command->selector);
				
				// Let's escape all / just for readability sake:
				$rule = str_replace('/', '\/', $rule);
				
				// Let's make sure that rule . selector is escaped:
				if ($rule && $rule[0] == '.') $rule = '\\'.$rule;
				
				$pattern = '/^'.$rule.'/i';
				
				$result = preg_replace($pattern, $replacement, $command->normalized);
				$result = Command :: normalize($result, $command->selector);
				
				if ($result != $command->normalized) { // Replacement happen
					
					# echo "<p> Routing $command => $result </p>";
					return new Command($result);
				}
			}
		}
		
		return $command;
	}
	
	
	/**
	 * Displays the tree of application.
	 *
	 * The tree of application contains an information about all executed branches.
	 * Includes shortened information about data.
	 */
	public function tree() {
		
		echo "<ul style=\"font-family: consolas;\">";
		
		$temp = $this->children;
		if ($this->next()) {
			$temp['next'] = $this->next();
		}
		
		foreach ($temp as $resourceName => $child) {
			
			echo "<li>";
			if (is_object($child->data)) {
				
				echo "<b>$resourceName </b> (".get_type($child->data)."): ";
				
			} elseif (is_array($child->data)) {
				
				echo "<b>$resourceName : Array</b>";
				
			} else {
				
				echo "$resourceName (".get_type($child->data)."): ";
				$details = htmlentities(substr($child->data, 0, 100));
				if (strlen($child->data) > 100) $details .= '...';
				echo "<span style=\"color:green\">$details</span>";
			}
			
			if (!empty($child->children) && $resourceName != 'next') $child->tree();
			echo "</li>";
		}
		
		echo "</ul>";
	}
	
	
	/**
	 * For objects:
	 * Returns set of fields (properties or methods) to export when export() or json() methods
	 * are called.
	 *
	 * Object may provide public $jsonExport property:
	 * If $jsonExport is not provided, all public properties will be exported (access must be granted).
	 * If $jsonExport is false, no properties will be exported.
	 * If $jsonExport is set of fields, eg. ['title', 'message', 'someMethod,param'], only those fields will be exported.
	 * If $jsonExport fieldset contains wildcard '*', every public property will be included in fieldset.
	 *
	 * Object may provide public $jsonBlacklist property:
	 * If $jsonBlacklist = ['password', 'otherVar'], those fields will be ignored.
	 *
	 * Notice: Pronode always checks access before exporting certain property or method.
	 *
	 * For the rest of data types (arrays, scalars):
	 * Returns true - all data is exported (standard json_encode is called).
	 *
	 * TODO: checks array_keys, which is unavailable in MAGIC objects (@READBEAN).
	 */
	protected function checkJsonConfiguration() {
		
		if (is_object($this->data)) {
			
			if (isset($this->data->jsonExport) && $this->data->jsonExport === false) {
				
				return [];
				
			}
			
			if (!isset($this->data->jsonExport)) {
				
				$fieldSet = array_keys(get_object_vars($this->data));
			}
			
			if (isset($this->data->jsonExport) && is_array($this->data->jsonExport)) {
				
				$fieldSet = $this->data->jsonExport;
				$index = array_search('*', $fieldSet); // wildcard
				
				if ($index !== false) {
					
					$fieldSet = array_merge($fieldSet, array_keys(get_object_vars($this->data)));
					unset($fieldSet[$index]);
				}
			}
			
			if (isset($this->data->jsonBlacklist)) {
				
				$fieldSet = array_diff($fieldSet, $this->data->jsonBlacklist);
				
			}
			
			return $fieldSet;
			
		}
		
		if ($this->data_isAssoc($this->data)) {
			
			if (isset($this->data['jsonExport']) && $this->data['jsonExport'] === false) {
				
				return [];
			}
			
			if (!isset($this->data['jsonExport'])) {
				
				$fieldSet = array_keys($this->data);
			}
			
			if (isset($this->data['jsonExport']) && is_array($this->data['jsonExport'])) {
				
				$fieldSet = $this->data['jsonExport'];
				$index = array_search('*', $fieldSet); // wildcard
				
				if ($index !== false) {
					
					$fieldSet = array_merge($fieldSet, array_keys($this->data));
					unset($fieldSet[$index]);
				}
			}
			
			if (isset($this->data['jsonBlacklist'])) {
				
				$fieldSet = array_diff($fieldSet, $this->data['jsonBlacklist']);
				
			}
			
			return $fieldSet;
			
		}
		
		return true;
		
	}
	
	
	/**
	 * Exports wrapped object to JSON-like structure so it can be used for further json encoding.
	 * Respects .jsonConfiguration and .accessConfiguration.
	 */
	public function export(array $whitelist = []) {
		
		$result = null;
		
		if (is_object($this->data)) {
			
			$result = new \stdClass();
			
			$fieldSet = $this->checkJsonConfiguration();
			
			foreach ($fieldSet as $command) {
				
				$target = $this->parseCommand($command)['target'];
				
				if (count($whitelist)) {
					
					if (in_array($target, $whitelist)) {
						
						$node = $this->exec($command);
						
						$result->{$command} = $node->export();
					}
					
				} else {
					
					$node = $this->exec($command);
					
					$result->{$command} = $node->export();
				}
				
			}
			
		} elseif ($this->data_isAssoc()) {
			
			$result = [];
			
			$fieldSet = $this->checkJsonConfiguration();
			
			foreach ($fieldSet as $command) {
				
				$target = $this->parseCommand($command)['target'];
				
				if (count($whitelist)) {
					
					if (in_array($target, $whitelist)) {
						
						$node = $this->exec($command);
						
						$result[$command] = $node->export();
					}
					
				} else {
					
					$node = $this->exec($command);
					
					$result[$command] = $node->export();
				}
				
			}
			
		} else {
			
			$result = $this->data;
		}
		
		return $result;
	}
	
	
	public function json(int $flags = JSON_PRETTY_PRINT, int $depth = 512) {
		
		if (!headers_sent()) {
			header('Content-Type: application/json');
		}
		
		if (is_object($this->data) && $this->data instanceof \stdClass) {
			
			return json_encode($this->data, $flags, $depth);
		}
		
		$output = json_encode($this->export(), $flags, $depth);
		
		return $output;
	}
	
	
	
	/**
	 * Jade to HTML converter.
	 * Converts Jade-syntax template (Jade / PUG / PHUG) to HTML.
	 * Preserves double-bracket markers: {{var}}.
	 * Supports :javascript filter.
	 * Supports :cdata filter.
	 * Supports :css filter.
	 */
	public static function jade2html($input) : string {
		
		$dumper = new \Jade\Dumper\PHPDumper();
		
		$dumper->registerFilter('javascript', new \Jade\Filter\JavaScriptFilter());
		$dumper->registerFilter('cdata', new \Jade\Filter\CDATAFilter());
		$dumper->registerFilter('style', new \Jade\Filter\CSSFilter());
		
		$parser = new \Jade\Parser(new \Jade\Lexer\Lexer());
		
		$jade = new \Jade\Jade($parser, $dumper);
		
		$output = $jade->render($input);
		
		return $output;
	}
	
	
	/**
	 * Pronode special method: .jade
	 * Converts Jade-syntax input (Jade / PUG / PHUG) to HTML.
	 */
	public function jade() : string {
		
		$input = $this->data;
		
		return self::jade2html($input);
	}
	
	
	/**
	 * Pronode special method: .pug
	 * Alias for .jade
	 */
	public function pug() {
		
		return self::jade2html($this->data);
	}
	
	
	/**
	 * Pronode special method: .phug
	 * Alias for .jade
	 */
	public function phug() {
		
		return self::jade2html($this->data);
	}
	
}

/**
 * Pronode extension for the main class.
 * Provides extra methods to operate on string data.
 * Every method need to operate on $this->data property.
 */
trait extString {
	
	/**
	 * Typography beutifier.
	 * Removes the "orphans" - alone alphanumeric characters from the end of a line.
	 *
	 * Example usage:
	 * {{article.contents.removeOrphans}}
	 */
	public function removeOrphans() {
		
		$text = $this->data;
		
		$text = preg_replace('/ (\w) ([^ ]+)/', ' $1&nbsp;$2', $text);
		
		return $text;
	}
	
	
	/**
	 * Returns the length of a string or the number of elements in array.
	 *
	 * Example usage:
	 * This article is {{article.contents.length}} characters long.
	 * There are {{articles.length}} articles in database.
	 */
	public function length() {
		
		if (is_array($this->data)) return count($this->data);
		
		return strlen($this->data);
	}
	
	
	/**
	 * Uppercase first letter.
	 *
	 * Returns a string with the first character of string capitalized, if that character is alphabetic.
	 */
	public function ucfirst() {
		
		return ucfirst($this->data);
		
	}
	
	/**
	 * Lowercase first letter.
	 *
	 * Returns a string with the first character of string lowercased if that character is alphabetic.
	 */
	public function lcfirst() {
		
		return lcfirst($this->data);
		
	}
	
	
	public function strtolower() {
		
		return strtolower($this->data);
		
	}
	
	
	public function strip_tags($allowed_tags = null) {
		
		return strip_tags($this->data, $allowed_tags);
	}
	
	
	public function strrev() {
		
		return strrev($this->data);
	}
	
	/**
	 * Glues a string to the begining of a data only if data == true.
	 *
	 * The difference between {{user.age.prefix,"Age: "}} and Age: {{user.age}}
	 * is that the first one will output "" and the second one will output "Age: "
	 * when no user's age is specified.
	 */
	public function prefix($str) {
		
		if ($this->data) {
			return $str.$this->data;
		}
		
	}
	
	
	/**
	 * Glues a string to the end of a data only if data == true.
	 *
	 * The difference between {{user.height.suffix," m"}} and {{user.height}} m
	 * is that the first one will output "" and the second one will output " m"
	 * when no user's height is specified.
	 */
	public function suffix($str) {
		
		if ($this->data) {
			return $this->data.$str;
		}
		
	}
	
	public function replace($from, $to) {
		
		return str_replace($from, $to, $this->data);
	}
	
	
	public function formatTimestamp($format) {
		
		if (!is_numeric($this->data)) {
			$timestamp = strtotime($this->data);
		} else {
			$timestamp = $this->data;
		}
		
		$to_convert = array(
				'l'=>array('dat'=>'N','str'=>array('Poniedziałek','Wtorek','Środa','Czwartek','Piątek','Sobota','Niedziela')),
				'F'=>array('dat'=>'n','str'=>array('styczeń','luty','marzec','kwiecień','maj','czerwiec','lipiec','sierpień','wrzesień','październik','listopad','grudzień')),
				'f'=>array('dat'=>'n','str'=>array('stycznia','lutego','marca','kwietnia','maja','czerwca','lipca','sierpnia','września','października','listopada','grudnia'))
		);
		if ($pieces = preg_split('#[:/.\-, ]#', $format)){
			if ($timestamp === null) { $timestamp = time(); }
			foreach ($pieces as $datepart){
				if (array_key_exists($datepart,$to_convert)){
					$replace[] = $to_convert[$datepart]['str'][(date($to_convert[$datepart]['dat'],$timestamp)-1)];
				}else{
					$replace[] = date($datepart,$timestamp);
				}
			}
			$result = strtr($format,array_combine($pieces,$replace));
			
			return $result;
		}
		
	}
	
	
	public function toDate() {
		
		$date = new \DateTime($this->data);
		return $date;
		
	}
	
	
	public function text($text) {
		
		return $text;
	}
	
	
	public function urlencode() {
		
		return urlencode($this->data);
	}
	
	
	public function htmlentities() {
		
		return htmlentities($this->data);
	}
	
	
	/**
	 * Short alias for .escape
	 */
	public function e() {
		
		return $this->escape();
	}
	
	
	public function escape() {
		
		return htmlspecialchars($this->data);
	}
	
	
	public function nl2br() {
		
		return nl2br($this->data);
	}
	
	
	public function substr($start, $length) {
		
		return substr($this->data, $start, $length);
	}
	
	
	public function maxChars($max, $suffix = '') {
		
		if (strlen($this->data) > ($max + 4)) {
			return substr($this->data,0,$max).$suffix;
		}
		
		return $this->data;
	}
	
	
	public function md5() {
		
		return md5($this->data);
	}
	
	
	
	/**
	 * Hello-world function for benchmark purpose.
	 */
	public function hello() {
		
		return 'Hello Pronode!';
	}
	
	
	/**
	 * Converts an integer into the alphabet base (A-Z).
	 *
	 * @param int $n This is the number to convert.
	 * @return string The converted number.
	 * @author Theriault
	 */
	function num2alpha($n) {
		$r = '';
		for ($i = 1; $n >= 0 && $i < 10; $i++) {
			$r = chr(0x41 + ($n % pow(26, $i) / pow(26, $i - 1))) . $r;
			$n -= pow(26, $i);
		}
		return $r;
	}
	
	
	/**
	 * Converts an alphabetic string into an integer.
	 *
	 * @param int $n This is the number to convert.
	 * @return string The converted number.
	 * @author Theriault
	 */
	function alpha2num($n) {
		$r = 0;
		$l = strlen($n);
		for ($i = 0; $i < $l; $i++) {
			$r += pow(26, $i) * (ord($n[$l - $i - 1]) - 0x40);
		}
		return $r - 1;
	}
	
}


trait extDateTime {
	
	public function dateFormat($format) {
		
		$date = new \DateTime($this->data);
		$format = $date->format($format);
		
		return $format;
		
	}
	
	public function currentDate() {
		
		return date("Y-m-d");
	}
	
	
	public function currentTime() {
		
		return date("H:i:s");
	}
	
}


/**
 * Array data manipulation method pack.
 */
trait extArray {
	
	function empty() {
		
		if (empty($this->data)) return true;
		return false;
	}
	
	function implode($glue) {
		
		if (!is_array($this->data)) return $this->data;
		return implode($glue, $this->data);
	}
	
	
	function implodeNl() {
		
		return implode("\r\n", $this->data);
	}
	
	
	function sort($sortFlags = null) {
		
		sort($this->data, $sortFlags);
		return $this->data;
	}
	
	
	function order($field) {
		
		osort($this->data, $field);
		
		return $this->data;
	}
	
	
	function reverse() {
		
		if (is_array($this->data)) return \array_reverse($this->data);
		if (is_string($this->data)) return \strrev($this->data);
		
	}
	
	
	function toIter() {
		
		$new = [];
		$i = 0;
		foreach ($this->data as $value) {
			
			$new[$i++] = $value;
			
		}
		
		return $new;
	}
	
	
	function first($n = 0) {
		
		if (!is_array($this->data)) {
			return $this->data;
		}
		
		if ($n) {
			return array_splice($this->data, 0, $n);
		}
		
		return reset($this->data);
	}
	
	
	function pop() {
		
		return array_pop($this->data);
	}
	
	
	function splice($offset, $length = 0) {
		
		return array_splice($this->data, $offset, $length);
	}
	
	
	function count() {
		
		return count($this->data);
	}
	
	
	function sizeof() {
		
		return sizeof($this->data);
	}
	
	function uniqueByField($field) {
		
		$uai = [];
		foreach ($this->data as $object) {
			
			$uai[$object->$field] = $object;
			
		}
		
		$ua = [];
		foreach ($uai as $object) {
			
			$ua[] = $object;
			
		}
		
		return $ua;
	}
	
	
	function groupBy($field) {
		
		$arr = [];
		foreach ($this->data as $object) {
			
			$arr[$object->$field][] = $object;
			
		}
		
		return $arr;
	}
	
	
	function keys() {
		
		return array_keys($this->data);
	}
	
}


/**
 * Debuging-related methods pack.
 */
trait extDebug {
	
	protected static $_DEBUG_SERVERS = [];
	protected static $_DEBUG_DEVELOPERS = [];
	
	/**
	 * Adds server domain to whitelist.
	 * 
	 * Use inside safe networks only.
	 */
	public static function debugAddServer($domain) : void {
		
		self::$_DEBUG_SERVERS[$domain] = true;
	}
	
	/**
	 * Checks whether current IP address is whitelisted by ::addDeveloper($ip), if not:
	 * Checks whether current host is whitelisted by ::debugAddServer($domain)
	 * @return bool
	 */
	protected static function debugAllowed() : bool {
		
		if (isset(self::$_DEBUG_DEVELOPERS[$_SERVER['REMOTE_ADDR']])) return true;
		
		$server = $_SERVER['HTTP_HOST']; // TODO: this isn't safe due to possible http header mods
		
		if (array_key_exists($server, self::$_DEBUG_SERVERS)) return true;
		
		return false;
	}
	
	
	/**
	 * Allows an access to DEBUG methods for given connection $ip
	 * 
	 * Use inside safe networks only.
	 */
	public static function addDeveloper($ip) {
		
		self::$_DEBUG_DEVELOPERS[$_SERVER['REMOTE_ADDR']] = true;
		
	}
	
	
	/**
	 * Performs var_dump on Node's data.
	 * This method is available only in DEBUG mode.
	 * 
	 * Use ::debugAddServer(localhost) to enable DEBUG methods on localhost.
	 * Use ::addDeveloper($ip) to enable DEBUG mode for given connection.
	 * 
	 * Warning: this method allows you to view RAW form of any wrapped data,
	 * therefore should always be protected from exploits.
	 */
	public function dump() {
		
		if (!self::debugAllowed()) {
			
			return $this->debug_accessDeniedMessage();
		}
	
		ob_start();
		var_dump($this->data);
		
		return ob_get_clean();
	}
	
	
	/**
	 * Prints Node's data without access check.
	 * This method is available only in DEBUG mode.
	 * 
	 * Use ::debugAddServer(localhost) to enable DEBUG methods on localhost.
	 * Use ::addDeveloper($ip) to enable DEBUG mode for given connection.
	 * 
	 * Warning: this method allows you to view RAW form of any wrapped data,
	 * therefore should always be protected from exploits.
	 */
	public function print() {
		
		if (!self::debugAllowed()) {
			
			return $this->debug_accessDeniedMessage();
		}
		
		header('Content-Type:text/plain');
		print $this;
	}
	
	
	/**
	 * Let system sleep for given $seconds.
	 * This method is available only in DEBUG mode.
	 * 
	 * Use ::debugAddServer(localhost) to enable DEBUG methods on localhost.
	 * Use ::addDeveloper($ip) to enable DEBUG mode for given connection.
	 * 
	 * Warning: this method may suspend your server when DDOSed.
	 * Use only under proxy protection.
	 */
	public function sleep($seconds) {
		
		if (!self::debugAllowed()) {
			
			return $this->debug_accessDeniedMessage();
		}
		
		sleep($seconds);
		
		return true;
	}
	
	
	/**
	 * Returns an array of children Nodes.
	 * This method is available only in DEBUG mode.
	 * 
	 * @return array
	 */
	public function children() : array {
		
		if (!self::debugAllowed()) {
			
			return $this->debug_accessDeniedMessage();
		}
		
		return $this->children;
	}
	
	
	protected function debug_accessDeniedMessage() {
		
		$host = $_SERVER['HTTP_HOST'];
		$user = $_SERVER['REMOTE_ADDR'];
		
		return "Access denied. Use either Pronode::addDeveloper('$user') or Pronode::debugAddServer('$host') before →execRequest() is called.";
	}
	
}


trait extCache {

	protected function cache_set($key, $val) {
		
		$file = $this->cache_getFilePath($key);
		
		if (!file_exists(dirname($file))) {
			
			mkdir(dirname($file), 0777, true);
		}
		
		$val = serialize($val);
		
		$contents = '<?php /*  '.$val;
		
		return file_put_contents($file, $contents);
		return file_put_contents($file, $contents, LOCK_EX);
	}
	
	
	protected function cache_key_exists($key) {
		
		$file = $this->cache_getFilePath($key);
		
		if (!file_exists($file)) return false;
		
		return true;
	}
	
	
	protected function cache_get($key) {
		
		$file = $this->cache_getFilePath($key);
		
		if (!file_exists($file)) return null;
		
		$serialized = \substr(file_get_contents($file), 10);
		
		return unserialize($serialized);
	}
	
	
	protected function cache_delete($key) {
		
		$file = $this->cache_getFilePath($key);
		
		if (!file_exists($file)) return null;
		
		return unlink($file);
	}
	
	
	protected function cache_getFilePath($key) {
		
		$encodedKey = md5($key);
		
		$dir = __DIR__;
		$dir = static :: getClassDir($this->root()->data);
		
		$path = $dir.DIRECTORY_SEPARATOR.
					'pn_cache'.DIRECTORY_SEPARATOR.
						'public'.DIRECTORY_SEPARATOR.
							$encodedKey.'.cache.php';
		
		return $path;
	}
}


/**
 * Numeric data manipulation methods pack.
 */
trait extNumber {
	
	/**
	 * Generates random number from 10000 to 99999.
	 * Helpful when you want to prevent client browser from caching assets.
	 * Example usage in template: <link rel="stylesheet" href="style.css?v={{randVer}}"/>
	 *
	 * Cache randVer the way you want.
	 * Setting ->cacheRandVer = "public 1 hour" will result in "refreshing" your assets every hour for every user
	 * @return number
	 */
	public function randVer() : int {
		
		return rand(10000, 99999);
	}
	
}


/**
 * Http / web method pack.
 */
trait extHttp {
	
	
	public function csrf() {
		
		return bin2hex(random_bytes(32));
	}
	
	
	public function http404() {
		
		http_response_code(404);
		
		return $this->view(__FUNCTION__);
	}
	
	
	/**
	 * Returns captcha HTML inline base64.
	 * Sets up $_SESSION['captcha'] phrase for further verification.
	 * Uses Gregwar Captcha.
	 * @see https://github.com/Gregwar/Captcha
	 */
	public function captcha() : string {
		
		$captcha = new \Gregwar\Captcha\CaptchaBuilder();
		
		$captcha->build();
		
		$_SESSION['captcha'] = $captcha->getPhrase();
		
		return $captcha->inline(90); // param/100 = image quality
	}
	
	
	public function post() {
		
		if (isset($_POST)) return $_POST;
		
		return json_decode(file_get_contents('php://input'));
	}
}

trait extCode {
	
	function codeTagsToHightlightJS() {
		
		$phpCallback = function($matches) {
			
			return '<pre><code class="php">'.trim(htmlentities($matches[1])).'</code></pre>';
		};
		
		$htmlCallback = function($matches) {
			
			return '<pre><code class="html">'.trim(htmlentities($matches[1])).'</code></pre>';
		};
		
		$httpCallback = function($matches) {
			
			return '<pre><code class="http">'.trim(htmlentities($matches[1])).'</code></pre>';
		};
		
		$code = $this->data;
		
		$code = preg_replace_callback('/<php>(.*?)<\/php>/s', $phpCallback, $code);
		$code = preg_replace_callback('/<html>(.*?)<\/html>/s', $htmlCallback, $code);
		$code = preg_replace_callback('/<http>(.*?)<\/http>/s', $httpCallback, $code);
		
		return $code;
	}
}