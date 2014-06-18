<?php

/**
 * @author marcus@silverstripe.com.au
 * @license BSD License http://silverstripe.org/bsd-license/
 */
class WorkflowEmbargoExpiryTest extends SapphireTest {
	
	public function __construct() {
		if(!class_exists('AbstractQueuedJob')) {
			$this->skipTest = true;
		}

		parent::__construct();
		
		$this->requiredExtensions = array(
			'Page'		=> array('WorkflowEmbargoExpiryExtension')
		);
	}
	
	public function testFutureDatesJobs() {
		$page = new Page();
		
		$page->PublishOnDate = date("Y-m-d H:i:s", strtotime('+20 days'));
		$page->UnPublishOnDate = date("Y-m-d H:i:s", strtotime('+21 days'));
		
		// Two writes are necessary for this to work on new objects
		$page->write();
		$page->write();
		
		$this->assertTrue($page->PublishJobID > 0);
		$this->assertTrue($page->UnPublishJobID > 0);
	}
	
	
	public function testDesiredRemovesJobs() {
		$page = new Page();
		
		$page->PublishOnDate = date("Y-m-d H:i:s", strtotime('+20 days'));
		$page->UnPublishOnDate = date("Y-m-d H:i:s", strtotime('+21 days'));
		
		// Two writes are necessary for this to work on new objects
		$page->write();
		$page->write();
		
		$this->assertTrue($page->PublishJobID > 0);
		$this->assertTrue($page->UnPublishJobID > 0);
		
		$page->DesiredPublishDate = date("Y-m-d H:i:s", strtotime('+30 days'));
		$page->DesiredUnPublishDate = date("Y-m-d H:i:s", strtotime('+31 days'));
		
		$page->write();
		
		$this->assertTrue($page->PublishJobID == 0);
		$this->assertTrue($page->UnPublishJobID == 0);
	}
	
	public function testPublishActionWithFutureDates() {
		$action = new PublishItemWorkflowAction;
		$instance = new WorkflowInstance();
		
		$page = new Page();
		$page->Title = 'stuff';
		$page->DesiredPublishDate = date("Y-m-d H:i:s", strtotime('+20 days'));
		$page->DesiredUnPublishDate = date("Y-m-d H:i:s", strtotime('+21 days'));
		$page->write();

		$instance->TargetClass = 'Page';
		$instance->TargetID = $page->ID;

		$action->execute($instance);
		
		$page = DataObject::get_by_id('Page', $page->ID);
		$this->assertTrue($page->PublishJobID > 0);
		$this->assertTrue($page->UnPublishJobID > 0);
	}
	
	public function testRescheduleDates() {
		$action = new PublishItemWorkflowAction;
		
		// Create new page
		$page = new Page();
		$page->Title = 'stuff';
		$desiredPublish = $page->DesiredPublishDate = date("Y-m-d H:i:s", strtotime('+20 days'));
		$desiredUnPublish = $page->DesiredUnPublishDate = date("Y-m-d H:i:s", strtotime('+21 days'));
		$page->write();

		// Approve publish request
		$instance = new WorkflowInstance();
		$instance->TargetClass = 'Page';
		$instance->TargetID = $page->ID;
		$action->execute($instance);
		
		// Check that the correct jobs have been initiated
		$page = DataObject::get_by_id('Page', $page->ID);
		$publishJobID = $page->PublishJobID;
		$unPublishJobID = $page->UnPublishJobID;
		$publishJob = QueuedJobDescriptor::get()->byID($publishJobID);
		$unPublishJob = QueuedJobDescriptor::get()->byID($unPublishJobID);
		$this->assertEquals($desiredPublish, $publishJob->StartAfter);
		$this->assertEquals($desiredUnPublish, $unPublishJob->StartAfter);
		
		// Reschedule
		$newDesiredPublish  = $page->DesiredPublishDate = date("Y-m-d H:i:s", strtotime('+20 days'));
		$newDesiredUnPublish = $page->DesiredUnPublishDate = date("Y-m-d H:i:s", strtotime('+21 days'));
		$page->write();
		
		// Check old jobs have been aborted and detached from this page
		$page = DataObject::get_by_id('Page', $page->ID);
		$this->assertEquals(0, $page->PublishJobID);
		$this->assertEquals(0, $page->UnPublishJobID);
		$this->assertEmpty(QueuedJobDescriptor::get()->byID($publishJobID));
		$this->assertEmpty(QueuedJobDescriptor::get()->byID($unPublishJobID));
		
		// Approve new request
		$instance = new WorkflowInstance();
		$instance->TargetClass = 'Page';
		$instance->TargetID = $page->ID;
		$action->execute($instance);
		
		// Jobs have been replaced
		$page = DataObject::get_by_id('Page', $page->ID);
		$publishJobID = $page->PublishJobID;
		$unPublishJobID = $page->UnPublishJobID;
		$publishJob = QueuedJobDescriptor::get()->byID($publishJobID);
		$unPublishJob = QueuedJobDescriptor::get()->byID($unPublishJobID);
		$this->assertEquals($newDesiredPublish, $publishJob->StartAfter);
		$this->assertEquals($newDesiredUnPublish, $unPublishJob->StartAfter);
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
}
