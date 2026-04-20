<?php

namespace Tests\Unit;

use Tests\UnitTester;
use App\Controllers\TestTaskController;

class TestTaskControllerCest
{
    public function testIsJsonWithValidJson(UnitTester $I)
    {
        $result = TestTaskController::isJson('{"key": "value"}');
        $I->assertTrue($result);
    }

    public function testIsJsonWithInvalidJson(UnitTester $I)
    {
        $result = TestTaskController::isJson('not valid json');
        $I->assertFalse($result);
    }

    public function testIsJsonWithEmptyString(UnitTester $I)
    {
        $result = TestTaskController::isJson('');
        $I->assertFalse($result);
    }

    public function testIsJsonWithWhitespaceOnly(UnitTester $I)
    {
        $result = TestTaskController::isJson('   ');
        $I->assertFalse($result);
    }

    public function testIsJsonWithNumericValue(UnitTester $I)
    {
        $result = TestTaskController::isJson('123');
        $I->assertTrue($result);
    }

    public function testIsJsonWithArray(UnitTester $I)
    {
        $result = TestTaskController::isJson('[1, 2, 3]');
        $I->assertTrue($result);
    }
}