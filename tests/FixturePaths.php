<?php

declare(strict_types=1);

namespace Northrook\Tests;

trait FixturePaths
{
    private static function fixtureRoot() : string
    {
        return __DIR__.'/fixtures';
    }

    private static function classesRoot() : string
    {
        return self::fixtureRoot().'/classes';
    }
}
