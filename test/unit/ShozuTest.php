<?php

require_once __DIR__ . '/test_helper.php';

require_once 'Shozu.php';

class ShozuTest extends PHPUnit_Framework_TestCase
{
    protected function setUp()
    {
        $this->s = new \shozu\Shozu();
    }

    public function testGetConfigDefaultsMustReturnAnArray()
    {
        $this->assertInternalType('array', $this->s->getConfigDefaults());
    }

    public function testSetConfigMustMergeNewSettings()
    {
        $this->s->some_property = 'before_merge';

        $this->s->setConfig(array(
            'some_property' => 'after_merge'
        ));

        $this->assertEquals('after_merge', $this->s->some_property);
    }

    public function testSetConfigMustKeepDefaultSettings()
    {
        $s = $this->getMock('shozu\Shozu', array('getConfigDefaults'));
        $s->expects($this->any())
            ->method('getConfigDefaults')
            ->will($this->returnValue(array(
                'some_property' => 'before_merge'
            )));

        $s->setConfig(array(
            'another_property' => 'after_merge'
        ));

        $this->assertEquals('before_merge', $s->some_property);
    }

    # Currently Shozu::handle() does too many things, se we cannot test
    # it right now, but the following test should be part of the suite.
    public function testHandleMustSetConfig()
    {
        # FIXME: this test should not be skipped!
        $this->markTestSkipped(
            'Shozu::handle() need to be split and/or cleaned'
        );

        $config = array('some_property' => 'some_value');
        $s = $this->getMock('shozu\Shozu', array('setConfig'));
        $s->expects($this->once())
            ->method('setConfig')
            ->with($this->equalTo($config));

        $s->handle($config);
    }
}
