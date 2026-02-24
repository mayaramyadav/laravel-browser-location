<?php

declare(strict_types=1);

namespace Mayaram\BrowserLocation\Facades;

use Illuminate\Support\Facades\Facade;

class BrowserLocation extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Mayaram\BrowserLocation\BrowserLocation::class;
    }
}
