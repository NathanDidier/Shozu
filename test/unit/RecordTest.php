<?php

require_once __DIR__ . '/test_helper.php';

require_once 'Record.php';

# FIXME: Neither PHP nor PHPUnit allows to stub instance methods at runtime, so
# \shozu\Record can not be tested conveniently.
# So we create the following Record class (extending \shozu\Record) and use it
# as the SUT.
# A better solution would be to use PHP runkit (or similar) and define the
# method at runtime on \shozu\Record, in each test that needs it.
class Record extends \shozu\Record
{
    # Records needs setTableDefinition() method to be defined.
    protected function setTableDefinition()
    {
        $this->addColumn(array(
            'name'          => 'name',
            'type'          => 'string',
            'length'        => 64,
            'validators'    => array('notblank')
        ));
    }
}

class RecordTest extends PHPUnit_Framework_TestCase
{
    public function testRecordWithValidAttributesIsValid()
    {
        $record = new Record(array(
            'name'  => 'Some record'
        ));

        $this->assertTrue($record->isValid());
    }

    public function testRecordWithAnInvalidAttributeIsNotValid()
    {
        $record = new Record(array(
            'name'  => ''
        ));

        $this->assertFalse($record->isValid());
    }
}
