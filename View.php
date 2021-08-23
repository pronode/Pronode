<?php

namespace Pronode;

class View {
	
	/**
	 * Template object.
	 * 
	 * @var Template $template Template to operate on
	 */
	private Template $template;
	
	
	/**
	 * Reference to the Node we are operating on:
	 * 
	 * @var Node $node Node - resources provider
	 */
	public Node $node;
	
	
	/**
	 * Output
	 * 
	 * @var string $output Compiled output string
	 */
	public string $output;
	
	
	/**
	 * Array of (foreign) Markers which triggered execution of this view
	 * 
	 * @var ViewTrigger[] $triggeredBy
	 */
	public array $triggeredBy = [];
	
	
	/**
	 * Array of Views included in current View.
	 * Sub-views are represented by {{Markers}} in Template contents
	 * 
	 * @var View[]
	 */
	public array $subviews = [];
	
	
	public function __construct(Node $node, Template $template = null) {
		
		$this->node = $node;
		
		if ($template) $this->setTemplate($template);
		
		$this->output = $this->compile();

	}
	
	
	public function __sleep() {
		
		return ['output', 'subviews', 'triggeredBy'];
	}
	
	
	public function __toString() {
		
		return $this->output;
	}
	
	
	/**
	 * Set Template object to be used with this view.
	 * 
	 * @param Template $template
	 */
	public function setTemplate(Template $template) : void {
		
		$this->template = $template;
	}
	
	
	/**
	 * Compiles template using $node resources.
	 */
	public function compile() : string {
		
		if (is_iter($this->node->data)) {
			
			$output = '';
			
			foreach (array_keys($this->node->data) as $key) {
				
				$elementNode = $this->node->exec($key);
				
				if (isset($this->template)) {
					
					$elementView = $elementNode->exec('view,'.$this->template->name)->data;
					$pseudoMarker = "{{".$key."|".$this->template->name."}}";
				} else {
					
					$elementView = $elementNode->exec('view')->data;
					$pseudoMarker = "{{".$key."}}";
				}
				
				$this->subviews[$pseudoMarker] = $elementView;
				
				$output .= $elementView->output;
			}
			
			return $output;
		}
		
		
		if (!isset($this->template)) return $this->dataToString();
		
		$output = $this->template->contentsFragmented;
		
		$executed = []; // array of already executed commands to reduce string replacements
			
		foreach ($this->template->markers as $marker) {
			
			$view = $this->node->exec($marker->command)->data;
			
			if (!($view instanceof View)) {
				
				throw new \ErrorException("Something went wrong during view compilation. Expected Pronode\View, ".get_type($view)." given");
			}
			
			$view->triggeredBy[] = new ViewTrigger($this, $marker);
			
			$this->subviews[$marker->string] = $view;
			
			if (!isset($executed[$marker->command])) {
				
				if ($view->output != 'NULL') { // leave {{unavailableMarkers}} visible
					
					$output = str_replace($marker->string, $view->output, $output);
				}
			}
			
			@$executed[$marker->command]++;
		}
		
		$this->output = $output;
		
		return $output;
	}
	
	
	public function compileFragment(Fragment $fragment) {
		
		$output = $fragment->contents;
		
		$executed = []; // array of already executed commands to reduce string replacements
		
		foreach ($fragment->markers as $marker) {
			
			$view = $this->node->exec($marker->command)->data;
			
			if (!($view instanceof View)) {
				
				throw new \ErrorException("Something went wrong during view compilation. Expected Pronode\View, ".get_type($view)." given");
			}
			
			$view->triggeredBy[] = new ViewTrigger($this, $marker);
			
			if (!isset($executed[$marker->command])) {
				
				if ($view->output != 'NULL') { // leave {{unavailableMarkers}} visible
					
					$output = str_replace($marker->string, $view->output, $output);
				}
			}
			
			@$executed[$marker->command]++;
		}
		
		return $output;
	}
	
	
	
	/**
	 * Returns string definition of Node's data.
	 * Scalar values are returned as they are.
	 */
	public function dataToString() : string {
		
		# BOOL:
		if (is_bool($this->node->data)) {
			
			if ($this->node->data) return 'true'; return 'false';
		}
		
		# SCALAR:
		if (is_scalar($this->node->data)) return (string) $this->node->data;
		
		# NULL:
		if (is_null($this->node->data)) return 'NULL';
		
		# OBJECT:
		if (is_object($this->node->data)) {
			
			if (method_exists($this->node->data, '__toString')) {
				
				return $this->node->data->__toString();
			}
			
			return get_class($this->node->data).' Object';
		}
		
		# ARRAY:
		if (is_array($this->node->data)) {
			
			# ASSOC:
			if (is_assoc($this->node->data)) return 'Assoc []';
			
			# ITER:
			if (is_iter($this->node->data)) return 'Iter []';
			
			# EMPTY ARRAY:
			return 'Empty []';
		}
		
		return 'undefined';
	}
	
	
	/**
	 * Get Fragments occuring in this View if Template is provided.
	 * 
	 * @return Fragment[]
	 */
	public function getFragments() : array {
		
		if (!isset($this->template)) return [];
		
		return $this->template->fragments;
	}
	
	
	/**
	 * Get Markers occuring in this View if Template is provided.
	 *
	 * @return Marker[]
	 */
	public function getMarkers() : array {
		
		if (!isset($this->template)) return [];
		
		return $this->template->markers;
	}
	
	
	
}