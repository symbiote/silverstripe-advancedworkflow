<?php
/**
 * Tests for the workflow engine.
 *
 * @author     marcus@silverstripe.com.au
 * @license    BSD License (http://silverstripe.org/bsd-license/)
 * @package    advancedworkflow
 * @subpackage tests
 */
class WorkflowEngineTest extends SapphireTest {

	public function testCreateWorkflowInstance() {
		
		$definition = new WorkflowDefinition();
		$definition->Title = "Create Workflow Instance";
		$definition->write();

		$stepOne = new WorkflowAction();
		$stepOne->Title = "Step One";
		$stepOne->WorkflowDefID = $definition->ID;
		$stepOne->write();

		$stepTwo = new WorkflowAction();
		$stepTwo->Title = "Step Two";
		$stepTwo->WorkflowDefID = $definition->ID;
		$stepTwo->write();

		$transitionOne = new WorkflowTransition();
		$transitionOne->Title = 'Step One T1';
		$transitionOne->ActionID = $stepOne->ID;
		$transitionOne->NextActionID = $stepTwo->ID;
		$transitionOne->write();

		$instance = new WorkflowInstance();
		$instance->write();

		$instance->beginWorkflow($definition);

		$actions = $definition->Actions();
		$this->assertEquals(2, $actions->Count());

		$transitions = $actions->find('Title', 'Step One')->Transitions();
		$this->assertEquals(1, $transitions->Count());
	}

	public function testExecuteImmediateWorkflow() {
		$def = $this->createDefinition();

		$actions = $def->Actions();
		$firstAction = $def->getInitialAction();
		$this->assertEquals('Step One', $firstAction->Title);

		$instance = new WorkflowInstance();
		$instance->beginWorkflow($def);
		$this->assertTrue($instance->CurrentActionID > 0);

		$instance->execute();

		// the instance should be complete, and have two finished workflow action
		// instances.
		$actions = $instance->Actions();
		$this->assertEquals(2, $actions->Count());

		foreach($actions as $action) {
			$this->assertTrue((bool) $action->Finished);
		}
	}
	
	public function testPublishAction() {
		$this->logInWithPermission();
		
		$action = new PublishItemWorkflowAction;
		$instance = new WorkflowInstance();

		$page = new Page();
		$page->Title = 'stuff';
		$page->write();

		$instance->TargetClass = 'Page';
		$instance->TargetID = $page->ID;

		$this->assertFalse($page->isPublished());

		$action->execute($instance);

		$page = DataObject::get_by_id('Page', $page->ID);
		$this->assertTrue($page->isPublished());
		
	}

	public function testCreateDefinitionWithEmptyTitle() {
		$definition = new WorkflowDefinition();
		$definition->Title = "";
		$definition->write();
		$this->assertContains('My Workflow',$definition->Title,'Workflow created without title is assigned a default title.');
	}

	protected function createDefinition() {
		$definition = new WorkflowDefinition();
		$definition->Title = "Dummy Workflow Definition";
		$definition->write();

		$stepOne = new WorkflowAction();
		$stepOne->Title = "Step One";
		$stepOne->WorkflowDefID = $definition->ID;
		$stepOne->write();

		$stepTwo = new WorkflowAction();
		$stepTwo->Title = "Step Two";
		$stepTwo->WorkflowDefID = $definition->ID;
		$stepTwo->write();

		$transitionOne = new WorkflowTransition();
		$transitionOne->Title = 'Step One T1';
		$transitionOne->ActionID = $stepOne->ID;
		$transitionOne->NextActionID = $stepTwo->ID;
		$transitionOne->write();

		return $definition;
	}
	
	
	public function testCreateFromTemplate() {
		$structure = array(
			'First step'	=> array(
				'type'		=> 'AssignUsersToWorkflowAction',
				'transitions'	=> array(
					'second'	=> 'Second step'
				)
			),
			'Second step'	=> array(
				'type'		=> 'NotifyUsersWorkflowAction',
				'transitions'	=> array(
					'Approve'	=> 'Third step'
				)
			),
		);
		
		$template = new WorkflowTemplate('Test');
		
		$template->setStructure($structure);
		
		$actions = $template->createRelations();
		
		$this->assertEquals(2, count($actions));
		$this->assertTrue(isset($actions['First step']));
		$this->assertTrue(isset($actions['Second step']));
		
		$this->assertTrue($actions['First step']->exists());
		
		$transitions = $actions['First step']->Transitions();

		$this->assertTrue($transitions->count() == 1);
		
		
	}
	
	/**
	 * Tests whether if user(s) are able to delete a workflow, dependent on permissions.
	 */
	public function testCanDeleteWorkflow() {
		// Create a definition
		$def = $this->createDefinition();
		
		// Test a user with lame permissions
		$memberID = $this->logInWithPermission('SITETREE_VIEW_ALL');
		$member = DataObject::get_by_id('Member', $memberID);
		$this->assertFalse($def->canCreate($member));
		
		// Test a user with good permissions
		$memberID = $this->logInWithPermission('CREATE_WORKFLOW');
		$member = DataObject::get_by_id('Member', $memberID);
		$this->assertTrue($def->canCreate($member));
	}	
	
	/**
	 * For a context around this test, see: https://github.com/silverstripe-australia/advancedworkflow/issues/141
	 * 
	 *	1). Create a workflow definition
	 *	2). Step the content into that workflow
	 *	3). Delete the workflow
	 *	4). Check that the content:
	 *		i). Has no remaining related actions
	 *		ii). Can be re-assigned a new Workflow 
	 *	5). Check that the object under workflow, maintains its status (Draft, Published etc)
	 */
	public function testDeleteWorkflowTargetStillWorks() {
		// 1). Create a workflow definition
		$def = $this->createDefinition();
		$page = Page::create();
		$page->Title = 'dummy test';
		$page->WorkflowDefinitionID = $def->ID;	// Normally done via CMS
		Versioned::reading_stage('Stage');
		$page->write();
		
		// Check $page is in draft, pre-deletion
		$status = ($page->getIsAddedToStage() && !$page->getExistsOnLive());
		$this->assertTrue($status);	
		
		// 2). Step the content into that workflow
		$instance = new WorkflowInstance();
		$instance->beginWorkflow($def, $page);
		$instance->execute();
		
		// Check the content is assigned
		$testPage = DataObject::get_by_id('Page', $page->ID);
		$this->assertEquals($instance->TargetID, $testPage->ID);
		
		// 3). Delete the workflow
		$def->delete();
		
		// Check $testPage is _still_ in draft, post-deletion
		$status = ($testPage->getIsAddedToStage() && !$testPage->getExistsOnLive());
		$this->assertTrue($status);	
		
		/*
		 * 4). i). Check that the content: Has no remaining related actions
		 * Note: WorkflowApplicable::WorkflowDefinitionID does _not_ get updated until assigned a new workflow
		 * so we can use it to check that all related actions are gone
		 */
		$defID = $testPage->WorkflowDefinitionID;
		$this->assertEquals(0, DataObject::get('WorkflowAction')->filter('WorkflowDefID', $defID)->count());
		
		/*
		 * 4). ii). Check that the content: Can be re-assigned a new Workflow Definition
		 */	
		$newDef = $this->createDefinition();
		$testPage->WorkflowDefinitionID = $newDef->ID;	// Normally done via CMS	
		$instance = new WorkflowInstance();
		$instance->beginWorkflow($newDef, $testPage);
		$instance->execute();
		
		// Check the content is assigned to the new Workflow Definition correctly
		$this->assertEquals($newDef->ID, $testPage->WorkflowDefinitionID);
		$this->assertEquals($newDef->Actions()->count(), DataObject::get('WorkflowAction')->filter('WorkflowDefID', $newDef->ID)->count());
		
		// 5). Check that the object under workflow, maintains its status
		$newDef2 = $this->createDefinition();
		
		// Login so SiteTree::canPublish() returns true
		$testPage->WorkflowDefinitionID = $newDef2->ID;	// Normally done via CMS	
		$this->logInWithPermission();		
		$testPage->doPublish();
		
		// Check $testPage is published, pre-deletion (getStatusFlags() returns empty array)
		$this->assertTrue($testPage->getExistsOnLive());		

		$instance = new WorkflowInstance();
		$instance->beginWorkflow($newDef2, $testPage);
		$instance->execute();		
		
		// Now delete the related WorkflowDefinition and ensure status is the same (i.e. so it's not 'modified' for example)
		$newDef2->delete();
		
		// Check $testPage is _still_ published, post-deletion (getStatusFlags() returns empty array)
		$this->assertTrue($testPage->getExistsOnLive());
	}
}
