@block @block_alle @javascript
Feature: Course assistant block
  In order to get help on the course at any time
  As a learner
  I need to chat with an assistant that answers from the course documents

  Background:
    Given the following "users" exist:
      | username | firstname | lastname |
      | teacher1 | Teacher   | One      |
      | student1 | Student   | One      |
    And the following "courses" exist:
      | fullname          | shortname |
      | Generative AI 101 | GENAI101  |
    And the following "course enrolments" exist:
      | user     | course   | role           |
      | teacher1 | GENAI101 | editingteacher |
      | student1 | GENAI101 | student        |
    And the following "blocks" exist:
      | blockname | contextlevel | reference | pagetypepattern | defaultregion |
      | alle      | Course       | GENAI101  | course-view-*   | side-pre      |

  Scenario: Teachers manage the assistant, learners only chat
    Given I am on the "GENAI101" "Course" page logged in as "teacher1"
    Then I should see "ALL-E" in the "ALL-E" "block"
    And I should see "Edit the prompt" in the "ALL-E" "block"
    And I should see "Upload the course documents" in the "ALL-E" "block"
    And I log out
    And I am on the "GENAI101" "Course" page logged in as "student1"
    And I should see "I am ALL-E, your learning assistant" in the "ALL-E" "block"
    And I should not see "Edit the prompt" in the "ALL-E" "block"

  Scenario: The teacher prompt dialog opens and closes
    Given I am on the "GENAI101" "Course" page logged in as "teacher1"
    When I click on "Edit the prompt" "button" in the "ALL-E" "block"
    Then I should see "The prompt frames every answer in this course."
    And I click on "Cancel" "button" in the "ALL-E" "block"
    And I should not see "The prompt frames every answer in this course."
