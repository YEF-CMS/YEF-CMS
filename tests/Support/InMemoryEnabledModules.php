<?php

declare(strict_types=1);

namespace Yef\Tests\Support;

use Override;
use Yef\Domain\Module\EnabledModules;
use Yef\Domain\Module\ModuleName;

use function array_filter;
use function array_map;
use function array_values;
use function in_array;

final class InMemoryEnabledModules implements EnabledModules
{
    /**
     * @param list<string> $names
     */
    public function __construct(private array $names = []) {}

    #[Override]
    public function all(): array
    {
        return array_map(static fn(string $name): ModuleName => new ModuleName($name), $this->names);
    }

    #[Override]
    public function enable(ModuleName $name): void
    {
        if (!in_array($name->value, $this->names, true)) {
            $this->names[] = $name->value;
        }
    }

    #[Override]
    public function disable(ModuleName $name): void
    {
        $this->names = array_values(array_filter($this->names, static fn(string $enabled): bool => $enabled !== $name->value));
    }
}
