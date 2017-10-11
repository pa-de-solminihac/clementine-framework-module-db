<?php
class dbDbTest extends dbDbTest_Parent
{
    public static function setUpBeforeClass()
    {
        static::setUpTestDB();
    }

    public function testEscape()
    {
        global $Clementine;
        $dbModel = $Clementine->getModel('db');
        $this->assertTrue($dbModel->escape_string("foo'bar") === "foo\\'bar");
    }

}
