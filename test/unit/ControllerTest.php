<?php

require_once __DIR__ . '/test_helper.php';

require_once 'Controller.php';

class Controller extends \shozu\Controller {};

class ControllerTest extends PHPUnit_Framework_TestCase
{
    protected function setUp()
    {
        $this->controller = new Controller;
    }

    public function testGetRequestReturnARequest()
    {
        $this->assertInstanceOf(
            '\\shozu\\Request',
            $this->controller->getRequest()
        );
    }
}
