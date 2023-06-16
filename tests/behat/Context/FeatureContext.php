<?php

namespace Symbiote\AdvancedWorkflow\Tests\Behat\Context;

use Behat\Mink\Element\Element;
use Behat\Mink\Exception\ElementHtmlException;
use SilverStripe\BehatExtension\Context\SilverStripeContext;

if (!class_exists(SilverStripeContext::class)) {
    return;
}

class FeatureContext extends SilverStripeContext
{
    /**
     * Example: Then the workflow diff for the "Title" field should be "About <ins>Us!</ins><del>Us</del>"
     *
     * @Then /^the workflow diff for the "([^"]+)" field should be "(.*)"$/
     */
    public function theFieldDiffShouldBe(string $field, string $diff)
    {
        $element = $this->assertSession()->elementExists('css', "#workflow-$field");
        $actualHtml = $element->getHtml();

        $message = sprintf('The diff "%s" for the "%s" field did not match "%s"', $actualHtml, $field, $diff);

        $this->assertElement(
            (bool) preg_match($this->convertDiffToRegex($diff), $actualHtml),
            $message,
            $element
        );
    }

    /**
     * Allow for arbitrary whitespace before/after HTML tags, and before/after the diff as a whole.
     */
    private function convertDiffToRegex(string $diff): string
    {
        return '/\s*' . str_replace(['\<', '\>'], ['\s*\<', '\>\s*'], preg_quote($diff, '/')) . '\s*/u';
    }

    /**
     * @see Behat\Mink\WebAssert::assertElement()
     */
    private function assertElement(bool $condition, string $message, Element $element): void
    {
        if ($condition) {
            return;
        }

        throw new ElementHtmlException($message, $this->getSession()->getDriver(), $element);
    }
}
