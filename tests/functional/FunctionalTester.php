<?php

namespace Tests\Support;

class FunctionalTester extends \Codeception\Actor
{
    use _generated\FunctionalTesterActions;

    public function sendPostJson($url, $data)
    {
        $this->haveHttpHeader('Content-Type', 'application/json');
        $this->sendPOST($url, json_encode($data));
    }
}