<?php
require_once __DIR__ . '/test_helper.php';

use \Shozu\Request as Request;

class RequestTest extends PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        $this->request = new Request;
    }

    public function testGetFormat()
    {
        $_SERVER['HTTP_ACCEPT'] = 'application/json';

        $this->assertEquals('application/json', $this->request->getFormat());
    }

    public function testGetAcceptableContentTypesWhenNoneRequested()
    {
        $this->assertEquals(array(), $this->request->getAcceptableContentTypes());
    }

    public function testGetAcceptableContentTypesWhenEmptyStringRequested()
    {
        $_SERVER['HTTP_ACCEPT'] = '';

        $this->assertEquals(array(), $this->request->getAcceptableContentTypes());
    }

    public function testGetAcceptableContentTypes()
    {
        $_SERVER['HTTP_ACCEPT'] =
            'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8';

        $this->assertEquals(
            array(
                'application/xml'       => 0.9,
                '*/*'                   => 0.8,
                'text/html'             => 1.0,
                'application/xhtml+xml' => 1.0
            ),
            $this->request->getAcceptableContentTypes()
        );
    }
}
