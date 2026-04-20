<?php

namespace Tests\Support;

use Codeception\Module;
use Codeception\Lib\Interfaces\RequiresPackage;

class Functional extends Module implements RequiresPackage
{
    public function _requires()
    {
        return [];
    }

    protected function getInternalDomains()
    {
        return [];
    }
}
