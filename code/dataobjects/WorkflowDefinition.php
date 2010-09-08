<?php
/* 
 * 
All code covered by the BSD license located at http://silverstripe.org/bsd-license/
 */

/**
 * An overall definition of a workflow
 *
 * The workflow definition has a series of steps to it. Each step has a series of possible transitions
 * that it can take - the first one that meets certain criteria is followed, which could lead to
 * another step.
 *
 * A step is either manual or automatic; an example 'manual' step would be requiring a person to review
 * a document. An automatic step might be to email a group of people, or to publish documents.
 * Basically, a manual step requires the interaction of someone to pick which action to take, an automatic
 * step will automatically determine what to do once it has finished.
 *
 * @author marcus@silverstripe.com.au
 */
class WorkflowDefinition extends DataObject {

	public static $db = array(
		'Title' => 'Varchar(128)',
		'Description' => 'Text',
	);

	public static $has_many = array(
		'Actions' => 'WorkflowAction',
	);

	/**
	 * Get all the actions sorted in the appropriate order...
	 *
	 * @return DataObjectSet
	 */
	public function getSortedActions() {
		return DataObject::get('WorkflowAction', '"WorkflowDefID"='.((int) $this->ID), 'Sort ASC');
	}
}