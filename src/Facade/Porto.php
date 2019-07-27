<?php

namespace Brooke\Porto\Facade;

use think\Facade;

class BrookePorto extends Facade
{
    protected static function getFacadeClass()
    {
        return 'Brooke\Porto\Core';
    }
}
