@todo
Feature: Request publication of edited content
  As a cms Content Author
  I want to request through a workflow email notification to a Content Publisher to approve my content 

  Background:
    Given a "Review and Approve" "Workflow Definition" "Template" is created
    And a "group" "Content Author" has permissions "Request approval" content
	  And a "group" "Content Publisher" has permissions "approve" content
	  And a "group" "Content Author" is set on "Workflow Definition"
    And "Notify Users" email template has content "Hello, Please approve or reject a content change for a page named '$Context.Title', performed by {$Member.FirstName}."
    And "About Us" has the "Review and Approve" "Workflow Definition" is configured under "Workflow" tab
    And "About Us" has the "Content" "<h1>My awesome headline</h1><p>Some amazing content</p>"
    And I am logged in with "Content Author" permissions
    And I go to "/admin/pages"
    Then I click on "About Us" in the tree

@todo
  Scenario: I can request approval to a Content Publisher via email notification
    Given I select "My awesome headline" in the "Content" HTML field
    Then I change the text to "My awesome headline is now more awesome" in the "Content" HTML field
    And I do not See "Save and Publish" button
    Then I press the "Apply for approval" button
    Then "About Us" becomes read only
    And my change is "Saved in Draft"
    Then an email notification is sent to "Content Publisher"
