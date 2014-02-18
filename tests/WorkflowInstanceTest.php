<?php
/**
 * Tests for the workflow engine.
 *
 * @author     marcus@silverstripe.com.au
 * @license    BSD License (http://silverstripe.org/bsd-license/)
 * @package    advancedworkflow
 * @subpackage tests
 */
class WorkflowInstanceTest extends SapphireTest {
	
	/**
	 *
	 * @var string
	 */
	public static $fixture_file = 'advancedworkflow/tests/useractioninstancehistory.yml';
	
	/**
	 * 
	 * @var Member
	 */
	protected $currentMember;
	
	/**
	 * 
	 */
	public function setUp() {
		parent::setUp();
		$this->currentMember = $this->objFromFixture('Member', 'ApproverMember01');
	}

	/**
	 * Tests WorkflowInstance#getMostRecentActionForUser()
	 */
	public function testGetMostRecentActionForUser() {
	
		// Single, AssignUsersToWorkflowAction in "History"
		$history01 = $this->objFromFixture('WorkflowInstance', 'WorkflowInstance01');
		$mostRecentActionForUser01 = $history01->getMostRecentActionForUser($this->currentMember);
		$this->assertInstanceOf('WorkflowActionInstance', $mostRecentActionForUser01, 'Asserts the correct ClassName is retured #1');
		$this->assertEquals('Assign', $mostRecentActionForUser01->BaseAction()->Title, 'Asserts the correct BaseAction is retured #1');
		
		// No AssignUsersToWorkflowAction found with Member's related Group in "History"
		$history02 = $this->objFromFixture('WorkflowInstance', 'WorkflowInstance02');
		$mostRecentActionForUser02 = $history02->getMostRecentActionForUser($this->currentMember);
		$this->assertFalse($mostRecentActionForUser02, 'Asserts false is returned because no WorkflowActionInstance was found');
		
		// Multiple AssignUsersToWorkflowAction in "History", only one with Group relations
		$history03 = $this->objFromFixture('WorkflowInstance', 'WorkflowInstance03');
		$mostRecentActionForUser03 = $history03->getMostRecentActionForUser($this->currentMember);
		$this->assertInstanceOf('WorkflowActionInstance', $mostRecentActionForUser03, 'Asserts the correct ClassName is retured #2');
		$this->assertEquals('Assign', $mostRecentActionForUser03->BaseAction()->Title, 'Asserts the correct BaseAction is retured #2');
		
		// Multiple AssignUsersToWorkflowAction in "History", both with Group relations
		$history04 = $this->objFromFixture('WorkflowInstance', 'WorkflowInstance04');
		$mostRecentActionForUser04 = $history04->getMostRecentActionForUser($this->currentMember);
		$this->assertInstanceOf('WorkflowActionInstance', $mostRecentActionForUser04, 'Asserts the correct ClassName is retured #3');
		$this->assertEquals('Assigned Again', $mostRecentActionForUser04->BaseAction()->Title, 'Asserts the correct BaseAction is retured #3');
		
		// Multiple AssignUsersToWorkflowAction in "History", one with Group relations
		$history05 = $this->objFromFixture('WorkflowInstance', 'WorkflowInstance05');
		$mostRecentActionForUser05 = $history05->getMostRecentActionForUser($this->currentMember);
		$this->assertInstanceOf('WorkflowActionInstance', $mostRecentActionForUser05, 'Asserts the correct ClassName is retured #4');
		$this->assertEquals('Assigned Me', $mostRecentActionForUser05->BaseAction()->Title, 'Asserts the correct BaseAction is retured #4');
	}
	
	/**
	 * Tests WorkflowInstance#canView()
	 */
	public function testCanView() {
		// Single, AssignUsersToWorkflowAction in "History"
		$history01 = $this->objFromFixture('WorkflowInstance', 'WorkflowInstance01');
		$this->assertTrue($history01->canView($this->currentMember));
		
		// No AssignUsersToWorkflowAction found with Member's related Group in "History"
		$history02 = $this->objFromFixture('WorkflowInstance', 'WorkflowInstance02');
		$this->assertFalse($history02->canView($this->currentMember));
		
		// Multiple AssignUsersToWorkflowAction in "History", only one with Group relations
		$history03 = $this->objFromFixture('WorkflowInstance', 'WorkflowInstance03');
		$this->assertTrue($history03->canView($this->currentMember));	
		
		// Multiple AssignUsersToWorkflowAction in "History", both with Group relations
		$history04 = $this->objFromFixture('WorkflowInstance', 'WorkflowInstance04');
		$this->assertTrue($history04->canView($this->currentMember));	
		
		// Multiple AssignUsersToWorkflowAction in "History"
		$history05 = $this->objFromFixture('WorkflowInstance', 'WorkflowInstance05');
		$this->assertTrue($history05->canView($this->currentMember));	
	}

}