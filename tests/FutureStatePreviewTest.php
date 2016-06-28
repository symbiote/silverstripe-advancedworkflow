<?php

/**
 * Tests for the FutureStatePreviewField.
 *
 * @author     cjoe@silverstripe.com
 * @license    BSD License (http://silverstripe.org/bsd-license/)
 * @package    advancedworkflow
 * @subpackage tests
 */
class FutureStatePreviewTest extends SapphireTest {

    public function testNoReadOnlyState()
    {
        $preview = FutureStatePreviewField::create('test');

        $date = $preview->getDateField();
        $time = $preview->getTimeField();

        $this->assertContains('no-change-track', $date->extraClass(), 'Date field contains "no-change-track" class');
        $this->assertEquals('', $date->getName(), 'Date field does not have a name');
        $this->assertContains('no-change-track', $time->extraClass(), 'Time field contains "no-change-track" class');
        $this->assertEquals('', $time->getName(), 'Time field does not have a name');

        $readonly = $preview->performReadonlyTransformation();

        $this->assertEquals($preview, $readonly, 'Preview field is still editable after readonly transformation');
    }
}
