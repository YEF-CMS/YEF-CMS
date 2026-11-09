<?php

declare(strict_types=1);

namespace Yef\Module;

use function is_file;
use function preg_match;
use function spl_autoload_register;
use function str_replace;
use function str_starts_with;
use function strlen;
use function substr;

/**
 * Makes the classes of an enabled module loadable at run time (PSR-4). Modules are installed without
 * running Composer, so Composer's autoloader does not know them.
 */
final readonly class ModuleAutoloader
{
    /**
     * @param string $namespace Namespace prefix with the trailing backslash.
     * @param string $sourceDirectory Directory the namespace is rooted at.
     */
    public function add(string $namespace, string $sourceDirectory): void
    {
        spl_autoload_register(static function (string $class) use ($namespace, $sourceDirectory): void {
            if (!str_starts_with($class, $namespace)) {
                return;
            }

            $relative = substr($class, strlen($namespace));
            if (preg_match('/^[A-Za-z0-9_\\\\]+$/', $relative) !== 1) {
                return;
            }

            $file = $sourceDirectory . '/' . str_replace('\\', '/', $relative) . '.php';
            if (is_file($file)) {
                require $file;
            }
        });
    }
}
