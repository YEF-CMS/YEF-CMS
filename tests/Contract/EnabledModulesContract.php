<?php

declare(strict_types=1);

namespace Yef\Tests\Contract;

use PHPUnit\Framework\TestCase;
use Yef\Domain\Module\EnabledModules;
use Yef\Domain\Module\ModuleName;

/**
 * Behavior every EnabledModules implementation has to provide.
 */
abstract class EnabledModulesContract extends TestCase
{
    public function testNothingIsEnabledAtFirst(): void
    {
        $this->assertSame([], $this->createEnabledModules()->all());
    }

    public function testEnabledModuleIsListed(): void
    {
        $enabled = $this->createEnabledModules();

        $enabled->enable(new ModuleName('acme/gallery'));

        $this->assertEquals([new ModuleName('acme/gallery')], $enabled->all());
    }

    public function testEnablingTwiceListsTheModuleOnce(): void
    {
        $enabled = $this->createEnabledModules();

        $enabled->enable(new ModuleName('acme/gallery'));
        $enabled->enable(new ModuleName('acme/gallery'));

        $this->assertCount(1, $enabled->all());
    }

    public function testDisabledModuleIsGoneAndOthersStay(): void
    {
        $enabled = $this->createEnabledModules();
        $enabled->enable(new ModuleName('acme/gallery'));
        $enabled->enable(new ModuleName('acme/news'));

        $enabled->disable(new ModuleName('acme/gallery'));

        $this->assertEquals([new ModuleName('acme/news')], $enabled->all());
    }

    public function testDisablingAModuleThatIsNotEnabledDoesNothing(): void
    {
        $enabled = $this->createEnabledModules();

        $enabled->disable(new ModuleName('acme/gallery'));

        $this->assertSame([], $enabled->all());
    }

    abstract protected function createEnabledModules(): EnabledModules;
}
