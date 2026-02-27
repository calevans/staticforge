<?php

declare(strict_types=1);

namespace EICC\StaticForge\Tests\Unit\Features\ShortcodeProcessor;

use EICC\StaticForge\Features\ShortcodeProcessor\Feature;
use EICC\StaticForge\Tests\Unit\UnitTestCase;
use EICC\StaticForge\Core\EventManager;
use EICC\Utils\Container;

class FeatureTest extends UnitTestCase
{
    private Feature $feature;
    private EventManager $eventManager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->eventManager = new EventManager($this->container);
        $this->feature = new Feature();
        $this->feature->setContainer($this->container);
        $this->feature->register($this->eventManager);
    }

    public function testRegisterFeature(): void
    {
        $listeners = $this->eventManager->getListeners('PRE_RENDER');
        $this->assertNotEmpty($listeners);
    }
}
