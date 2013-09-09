<?php
require_once __DIR__ . '/test_helper.php';

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
        ));
        $this->addColumn(array(
            'name'          => 'snake_prop',
            'type'          => 'string',
            'length'        => 64
        ));
        $this->addColumn(array(
            'name'          => 'notblank_prop',
            'type'          => 'string',
            'length'        => 64,
            'validators'    => array('notblank'),
            'notnull'       => true
        ));
    }
}

class RecordTest extends PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        $record = new Record(array(
            'notblank_prop'  => 'Some value'
        ));

        $this->record = $record;
    }

    public function testRecordWithValidAttributesIsValid()
    {
        $this->assertTrue($this->record->isValid());
    }

    public function testRecordWithAnInvalidAttributeIsNotValid()
    {
        $this->record->notblank_prop = '';

        $this->assertFalse($this->record->isValid());
    }

    public function testRecordHasColumnWhenColumnExists()
    {
        $this->assertTrue($this->record->hasColumn('name'));
    }

    public function testRecordHasColumnWhenColumnDontExists()
    {
        $this->assertFalse($this->record->hasColumn('non_existent_column'));
    }

    public function testRecordMagicSetterSetAttribute()
    {
        $value = 'Some value';
        $this->record->setName($value);

        $this->assertEquals($value, $this->record->name);
    }

    public function testRecordMagicSetterInflectedAttribute()
    {
        $value = 'Some value';
        $this->record->setSnakeProp($value);

        $this->assertEquals($value, $this->record->snake_prop);
    }

    public function testRecordMagicGetterGetAttribute()
    {
        $value = 'Some value';
        $this->record->name = $value;

        $this->assertEquals($value, $this->record->getName());
    }

    public function testRecordMagicGetterInflectAttribute()
    {
        $value = 'Some value';
        $this->record->snake_prop = $value;

        $this->assertEquals($value, $this->record->getSnakeProp());
    }

    public function testRecordMagicGetterGetNullAttribute()
    {
        $this->assertEquals(null, $this->record->getName());
    }

    public function testRecordCallNonExistentGetterRaiseBadMethodCallException()
    {
        $this->setExpectedException('BadMethodCallException');

        $this->record->getNonExistentGetter();
    }

    public function testRecordCallNonExistentSetterRaiseBadMethodCallException()
    {
        $this->setExpectedException('BadMethodCallException');

        $this->record->setNonExistentSetter(null);
    }
}
