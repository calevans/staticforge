<?php

declare(strict_types=1);

namespace EICC\StaticForge\Tests\Unit\Features\DevServer\Services;

use EICC\StaticForge\Features\DevServer\Services\BuildRequest;
use PHPUnit\Framework\TestCase;

class BuildRequestTest extends TestCase
{
    public function testFirstPathPrefersDeletedThenChanged(): void
    {
        $this->assertSame('/d', (new BuildRequest(true, ['/c'], ['/d']))->firstPath());
        $this->assertSame('/c', (new BuildRequest(false, ['/c', '/e'], []))->firstPath());
    }

    public function testFirstPathIsEmptyWhenNothingChanged(): void
    {
        $this->assertSame('', (new BuildRequest(false, [], []))->firstPath());
    }
}
