<?php
/**
 *
 *
 * @package advancedworkflow
 */
class AdvancedWorkflowAdmin extends ModelAdmin {

	public static $menu_title    = 'Workflows';
	public static $menu_priority = -1;
	public static $url_segment   = 'workflows';

	public static $managed_models  = 'WorkflowDefinition';
	public static $model_importers = array();

}
