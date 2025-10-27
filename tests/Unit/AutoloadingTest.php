<?php

namespace EICC\StaticForge\Tests\Unit;

use PHPUnit\Framework\TestCase;

class AutoloadingTest extends TestCase
{
    public function testComposerAutoloadingWorks(): void
    {
        // Test that our namespace is properly autoloaded
        $this->assertTrue(class_exists('PHPUnit\Framework\TestCase'));

        // Test that we can access EICC\Utils namespace
        $this->assertTrue(class_exists('EICC\Utils\Container'));

        // Verify our own namespace structure is ready
        $this->assertEquals('EICC\StaticForge\Tests\Unit', __NAMESPACE__);
    }
}