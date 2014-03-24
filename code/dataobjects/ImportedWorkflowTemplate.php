<?php
/**
 * This DataObject replaces the SilverStripe cache as the repository for 
 * imported WorkflowDefinitions.
 * 
 * @author  russell@silverstripe.com
 * @license BSD License (http://silverstripe.org/bsd-license/)
 * @package advancedworkflow
 */
class ImportedWorkflowTemplate extends DataObject {
	
	/**
	 *
	 * @var array
	 */
	public static $db = array(
		"Name" => "Varchar(255)",
		"Filename" => "Varchar(255)",
		"Content" => "Text"
	);
	
	/**
	 *
	 * @var array
	 */	
	public static $has_one = array(
		'Definition' => 'WorkflowDefinition'
	);
	
}
