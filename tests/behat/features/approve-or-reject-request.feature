@todo
Feature: Approve a request for edited content
  As a cms Content Publisher
  I want to approve a request through a workflow after recieving an email notification from a Content Author 

  Background:
    Given a "Review and Approve" "Workflow Definition" "Template" is created
    And a "group" "Content Author" has permissions "Request approval" content
	  And a "group" "Content Publisher" has permissions "approve" content
	  And a "group" "Content Publisher" is set on "Workflow Definition" "Approve" action
    And "Notify Initiator Publish" email template has content "Your content change for the page named $Context.Title has been approved by {$Member.FirstName}."
    And "About Us" has a pending "Apply for Approval" action triggered
    And I am logged in with "Content Publisher" permissions
    And I go to "/admin/pages"
    Then I click on "About Us" in the tree

@todo
  Scenario: I can approve a pending "Apply for approval" action
    Given I recived a "Notify Us" email
    Then I can see the "Approve" button
@todo    And I can see the "Reject" button
    Then I press the "Approve" button
@todo    Then I can see a "Comments" form
@todo    Then I can edit a "Comment"
    And my change is "Saved in Draft"
    Then a "Notify Initiator Publish" email notification is sent to "Content Author"
