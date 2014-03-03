<?php
/**
 * Tests for permissions on all Workflow Objects.
 * These will obviousely need to be modified should additional workflow permissions come online.
 *
 * @author     russell@silverstripe.com
 * @license    BSD License (http://silverstripe.org/bsd-license/)
 * @package    advancedworkflow
 * @subpackage tests
 */
class WorkflowPermissionsTest extends SapphireTest {

	/**
	 * @var string
	 */
	public static $fixture_file = 'advancedworkflow/tests/workflowpermissions.yml';
	
	/**
	 * Tests whether members with differing permissions, should be able to create & edit WorkflowDefinitions
	 */
	public function testWorkflowDefinitionCanPerms() {
		// Very limited perms. No create.
		$this->logInWithPermission('CMS_ACCESS_AdvancedWorkflowAdmin');
		$workflowdef = $this->objFromFixture('WorkflowDefinition', 'no-actions');
		$this->assertFalse($workflowdef->canCreate());
		
		// Limited perms. No create.
		$this->logInWithPermission('VIEW_ACTIVE_WORKFLOWS');
		$workflowdef = $this->objFromFixture('WorkflowDefinition', 'no-actions');
		$this->assertFalse($workflowdef->canCreate());
		
		// Has perms. Can create.
		$this->logInWithPermission('CREATE_WORKFLOW');
		$workflowdef = $this->objFromFixture('WorkflowDefinition', 'no-actions');
		$this->assertTrue($workflowdef->canCreate());			
	}
	
	/**
	 * Tests whether members with differing permissions, should be able to create & edit WorkflowActions
	 */
	public function testWorkflowActionCanPerms() {
		// Very limited perms. No create.
		$this->logInWithPermission('CMS_ACCESS_AdvancedWorkflowAdmin');
		$workflowdef = $this->objFromFixture('WorkflowDefinition', 'with-actions');
		$this->assertFalse($workflowdef->Actions()->first()->canCreate());
		$this->assertFalse($workflowdef->Actions()->first()->canEdit());
		$this->assertFalse($workflowdef->Actions()->first()->canDelete());
		
		// Limited perms. No create.
		$this->logInWithPermission('VIEW_ACTIVE_WORKFLOWS');
		$workflowdef = $this->objFromFixture('WorkflowDefinition', 'with-actions');
		$this->assertFalse($workflowdef->Actions()->first()->canCreate());
		$this->assertFalse($workflowdef->Actions()->first()->canCreate());
		$this->assertFalse($workflowdef->Actions()->first()->canDelete());
		
		// Has perms. Can create.
		$this->logInWithPermission('CREATE_WORKFLOW');
		$workflowdef = $this->objFromFixture('WorkflowDefinition', 'with-actions');
		$this->assertTrue($workflowdef->Actions()->first()->canCreate());
		$this->assertTrue($workflowdef->Actions()->first()->canEdit());	
		$this->assertTrue($workflowdef->Actions()->first()->canDelete());
	}	
	
	/**
	 * Tests whether members with differing permissions, should be able to create & edit WorkflowActions
	 */
	public function testWorkflowTransitionPerms() {
		// Very limited perms. No create.
		$this->logInWithPermission('CMS_ACCESS_AdvancedWorkflowAdmin');
		$workflowdef = $this->objFromFixture('WorkflowDefinition', 'with-actions-and-transitions');
		$this->assertFalse($workflowdef->Actions()->first()->Transitions()->first()->canCreate());
		$this->assertFalse($workflowdef->Actions()->first()->Transitions()->first()->canEdit());
		$this->assertFalse($workflowdef->Actions()->first()->Transitions()->first()->canDelete());
		
		// Limited perms. No create.
		$this->logInWithPermission('VIEW_ACTIVE_WORKFLOWS');
		$workflowdef = $this->objFromFixture('WorkflowDefinition', 'with-actions-and-transitions');
		$this->assertFalse($workflowdef->Actions()->first()->Transitions()->first()->canCreate()); 
		$this->assertFalse($workflowdef->Actions()->first()->Transitions()->first()->canEdit()); 
		$this->assertFalse($workflowdef->Actions()->first()->Transitions()->first()->canDelete());
		
		// Has perms. Can create.
		$this->logInWithPermission('CREATE_WORKFLOW');
		$workflowdef = $this->objFromFixture('WorkflowDefinition', 'with-actions-and-transitions');
		$this->assertTrue($workflowdef->Actions()->first()->Transitions()->first()->canCreate());
		$this->assertTrue($workflowdef->Actions()->first()->Transitions()->first()->canEdit());
		$this->assertTrue($workflowdef->Actions()->first()->Transitions()->first()->canDelete());	
	}	

}