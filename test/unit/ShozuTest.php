<?php
require_once __DIR__ . '/test_helper.php';

class DummyUnique
{
    public $foo;
    public function __construct()
    {
        $this->foo = uniqid('test', true);
    }
}

class ShozuTest extends PHPUnit_Framework_TestCase
{
    /**
     * @var \shozu\Shozu $s
     */
    public $s;
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

    public function testGetBaseURL()
    {
        $s = $this->getMock('shozu\Shozu', array('getHost'));
        $s->expects($this->any())
            ->method('getHost')
            ->will($this->returnValue('host.example'));

        $this->assertEquals('http://host.example/', $s->getBaseURL());
    }

    public function testGetBaseURLWhenNonStandardPort()
    {
        $s = $this->getMock('shozu\Shozu', array('getHost'));
        $s->expects($this->any())
            ->method('getHost')
            ->will($this->returnValue('host.example:8000'));

        $this->assertEquals('http://host.example:8000/', $s->getBaseURL());
    }

    public function testGetBaseURLWhenHTTPS()
    {
        $s = $this->getMock('shozu\Shozu', array('getHost', 'getScheme'));
        $s->expects($this->any())
            ->method('getHost')
            ->will($this->returnValue('host.example'));
        $s->expects($this->any())
            ->method('getScheme')
            ->will($this->returnValue('https://'));

        $this->assertEquals('https://host.example/', $s->getBaseURL());
    }

    public function testGetBaseURLWhenNonStandardPortAndHTTPS()
    {
        $s = $this->getMock('shozu\Shozu', array('getHost', 'getScheme'));
        $s->expects($this->any())
            ->method('getHost')
            ->will($this->returnValue('host.example:8000'));
        $s->expects($this->any())
            ->method('getScheme')
            ->will($this->returnValue('https://'));

        $this->assertEquals('https://host.example:8000/', $s->getBaseURL());
    }

    public function testGetHost()
    {
        $_SERVER['HTTP_HOST'] = 'host.example';

        $this->assertEquals('host.example', $this->s->getHost());
    }

    public function testGetSchemeWhenHTTP()
    {
        $this->assertEquals('http://', $this->s->getScheme());
    }

    public function testGetSchemeWhenHTTPS()
    {
        $_SERVER['HTTPS'] = 'on';

        $this->assertEquals('https://', $this->s->getScheme());
    }

    public function testShozuSetShared()
    {
        $this->s->setShared('test', function(){
            return new DummyUnique();
        });

        $uid_a = $this->s->test->foo;
        $uid_b = $this->s->test->foo;

        $this->assertEquals($uid_a, $uid_b);
    }
}
