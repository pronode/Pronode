<?php 

namespace Pronode;

/**
 * Holds an information about which parent-View triggered the View
 * and which {{marker}} was used for its execution.
 * 
 * Stored in View->triggeredBy ViewTrigger array.
 * Set up during sub-View execution in View->compile method.
 */
class ViewTrigger {
	
	/**
	 * parent-View that triggered the View
	 * 
	 * @var View $view
	 */
	public View $view;
	
	/**
	 * Marker that triggered the View
	 *
	 * @var Marker $marker
	 */
	public Marker $marker;
	
	public function __construct(View $view, Marker $marker) {
		
		$this->view = $view;
		$this->marker = $marker;
	}
	
}