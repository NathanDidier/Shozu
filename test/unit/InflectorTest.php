<?php

require_once __DIR__ . '/test_helper.php';

require_once 'Inflector.php';

use \Shozu\Inflector as Inflector;

class InflectorTest extends PHPUnit_Framework_TestCase
{
    public function testUnderscoreLowerCaseFirstLetter()
    {
        $this->assertEquals(
            'bill',
            Inflector::underscore('Bill')
        );
    }

    public function testUnderscoreLowerCaseUpcasedLettersAndAddUnderscore()
    {
        $this->assertEquals(
            'bill_data',
            Inflector::underscore('BillData')
        );
    }

    public function testUnderscoreAddUnderscoreBeforeDigit()
    {
        $this->assertEquals(
            'bill_data_4',
            Inflector::underscore('BillData4')
        );
    }

    public function testUnderscoreAddUnderscoreBeforeNumber()
    {
        $this->assertEquals(
            'bill_data_42',
            Inflector::underscore('BillData42')
        );
    }

    public function testUnderscoreRespectAcronyms()
    {
        $this->assertEquals(
            'get_html',
            Inflector::underscore('getHTML')
        );
    }

    public function testUnderscoreRespectAcronymsInMiddleOfString()
    {
        $this->assertEquals(
            'get_html_content',
            Inflector::underscore('getHTMLContent')
        );
    }
}
