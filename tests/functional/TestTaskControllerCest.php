<?php

namespace Tests\Functional;

use Tests\FunctionalTester;

class TestTaskControllerCest
{
    public function _before(FunctionalTester $I)
    {
    }

    public function testInvalidJsonReturns400(FunctionalTester $I)
    {
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPOST('/test-task', 'not valid json');
        $I->seeResponseCodeIs(400);
        $I->seeResponseContains('Bad request');
    }

    public function testValidJsonReturns200(FunctionalTester $I)
    {
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPOST('/test-task', '{"test":"data"}');
        $I->seeResponseCodeIs(200);
    }

    public function testEmptyBodyReturns400(FunctionalTester $I)
    {
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPOST('/test-task', '');
        $I->seeResponseCodeIs(400);
    }
}