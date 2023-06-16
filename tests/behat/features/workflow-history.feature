@javascript
Feature: Workflow Actions history
  As a cms author
  I want to see the history of workflow actions on a page
  So that I can have assurance that correct processes are being followed

  Background:
    Given a workflow "Two-Step" using the "Review and Approve" template
    And a "page" "About Us" has the "Content" "<p>My content</p>"
    And the "page" "About Us" has the "Two-Step" workflow
    And the "page" "About Us" is published
    And the "group" "AUTHOR" has permissions "Access to 'Pages' section"
    And I am logged in as a member of "AUTHOR" group
    And I go to "/admin/pages"
    Then I should see "About Us" in the tree

  Scenario: I can see page edits as a diff in the Workflow Actions tab
    When I click on "About Us" in the tree
    Then I should see an edit page form
    When I fill in "Title" with "About Us!"
    And I fill in the "Content" HTML field with "<p>my new content</p>"
    And I press the "Apply for approval" button
    And I wait for 3 seconds
    # Form fields should be readonly
    Then I should see a "#Form_EditForm_Title.readonly" element
    And I should see a "#Form_EditForm_Content.readonly" element
    # Save and Apply for approval buttons shouldn't be there anymore
    And I should not see "Apply for approval"
    And I should not see "Save"
    When I click the "Workflow Actions" CMS tab
    Then the workflow diff for the "Title" field should be "About <ins>Us!</ins><del>Us</del>"
    And the workflow diff for the "Content" field should be "<p><ins>my new</ins><del>My</del> content</p>"

