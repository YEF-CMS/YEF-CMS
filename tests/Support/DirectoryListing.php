<?php

declare(strict_types=1);

namespace Yef\Tests\Support;

/**
 * What lies in a directory, for tests.
 */
final class DirectoryListing
{
    /**
     * @return list<string> The names of what is in the directory, without "." and "..", in order.
     */
    public static function of(string $directory): array
    {
        $names = scandir($directory);
        if ($names === false) {
            return [];
        }

        $listing = [];
        foreach ($names as $name) {
            if ($name !== '.' && $name !== '..') {
                $listing[] = $name;
            }
        }

        return $listing;
    }
}
