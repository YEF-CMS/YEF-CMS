<?php

declare(strict_types=1);

namespace Yef\Module;

use Composer\Semver\VersionParser;
use InvalidArgumentException;
use JsonException;
use UnexpectedValueException;
use Yef\Domain\Module\ModuleName;

use function array_key_exists;
use function basename;
use function file_get_contents;
use function is_array;
use function is_file;
use function is_string;
use function json_decode;
use function mb_strlen;
use function preg_match;
use function sprintf;
use function str_starts_with;
use function trim;

/**
 * Reads and checks the manifest of the module in a directory.
 */
final readonly class ManifestReader
{
    private const string FILE = 'module.json';

    private const int SCHEMA = 1;

    private const int MAX_TITLE_LENGTH = 80;

    private const string NAMESPACE_PATTERN = '/^([A-Za-z_][A-Za-z0-9_]*\\\\)+$/';

    private const string CLASS_PATTERN = '/^([A-Za-z_][A-Za-z0-9_]*\\\\)+[A-Za-z_][A-Za-z0-9_]*$/';

    /**
     * @throws InvalidManifest
     */
    public function read(string $directory): ModuleManifest
    {
        $data = $this->decode($directory . '/' . self::FILE);

        if (($data['schema'] ?? null) !== self::SCHEMA) {
            throw InvalidManifest::because(sprintf('"schema" must be %d.', self::SCHEMA));
        }

        $name = $this->name($data);
        if (basename($directory) !== $name->directoryName()) {
            throw InvalidManifest::because(sprintf('The directory must be named "%s" for the module "%s".', $name->directoryName(), $name));
        }

        $namespace = $this->text($data, 'namespace');
        if (preg_match(self::NAMESPACE_PATTERN, $namespace) !== 1) {
            throw InvalidManifest::because('"namespace" must be a PHP namespace ending with a backslash.');
        }

        $entry = $this->text($data, 'entry');
        if (preg_match(self::CLASS_PATTERN, $entry) !== 1 || !str_starts_with($entry, $namespace)) {
            throw InvalidManifest::because('"entry" must be a class inside the module namespace.');
        }

        return new ModuleManifest(
            $name,
            $this->title($data),
            $this->optionalText($data, 'description'),
            $this->version($data),
            $namespace,
            $entry,
            $this->coreConstraint($data),
        );
    }

    /**
     * @return array<array-key, mixed>
     *
     * @throws InvalidManifest
     */
    private function decode(string $file): array
    {
        $contents = is_file($file) ? file_get_contents($file) : false;
        if ($contents === false) {
            throw InvalidManifest::because(sprintf('%s is missing or unreadable.', self::FILE));
        }

        try {
            /** @psalm-suppress MixedAssignment The shape is checked right below. */
            $data = json_decode($contents, true, flags: \JSON_THROW_ON_ERROR);
        } catch (JsonException $jsonException) {
            throw InvalidManifest::because(sprintf('%s is not valid JSON.', self::FILE), $jsonException);
        }

        return is_array($data) ? $data : throw InvalidManifest::because(sprintf('%s must contain a JSON object.', self::FILE));
    }

    /**
     * @param array<array-key, mixed> $data
     *
     * @throws InvalidManifest
     */
    private function name(array $data): ModuleName
    {
        try {
            return new ModuleName($this->text($data, 'name'));
        } catch (InvalidArgumentException $invalidArgumentException) {
            throw InvalidManifest::because($invalidArgumentException->getMessage(), $invalidArgumentException);
        }
    }

    /**
     * @param array<array-key, mixed> $data
     *
     * @throws InvalidManifest
     */
    private function title(array $data): string
    {
        $title = trim($this->text($data, 'title'));
        if ($title === '' || mb_strlen($title) > self::MAX_TITLE_LENGTH) {
            throw InvalidManifest::because(sprintf('"title" is the name users see; it must have 1 to %d characters.', self::MAX_TITLE_LENGTH));
        }

        return $title;
    }

    /**
     * @param array<array-key, mixed> $data
     *
     * @throws InvalidManifest
     */
    private function version(array $data): string
    {
        $version = $this->text($data, 'version');
        try {
            new VersionParser()->normalize($version);
        } catch (UnexpectedValueException $unexpectedValueException) {
            throw InvalidManifest::because(sprintf('"version" "%s" is not a valid version.', $version), $unexpectedValueException);
        }

        return $version;
    }

    /**
     * @param array<array-key, mixed> $data
     *
     * @throws InvalidManifest
     */
    private function coreConstraint(array $data): string
    {
        /** @psalm-suppress MixedAssignment The shape is checked right below. */
        $requires = $data['requires'] ?? null;
        $constraint = is_array($requires) ? ($requires['yef'] ?? null) : null;
        if (!is_string($constraint)) {
            throw InvalidManifest::because('"requires.yef" is missing: state which versions of the core the module works with.');
        }

        try {
            new VersionParser()->parseConstraints($constraint);
        } catch (UnexpectedValueException $unexpectedValueException) {
            throw InvalidManifest::because(sprintf('"requires.yef" "%s" is not a valid version constraint.', $constraint), $unexpectedValueException);
        }

        return $constraint;
    }

    /**
     * @param array<array-key, mixed> $data
     *
     * @throws InvalidManifest
     */
    private function text(array $data, string $field): string
    {
        /** @psalm-suppress MixedAssignment The type is checked right below. */
        $value = $data[$field] ?? null;

        return is_string($value) && $value !== ''
            ? $value
            : throw InvalidManifest::because(sprintf('"%s" is required and must be a non-empty string.', $field));
    }

    /**
     * @param array<array-key, mixed> $data
     *
     * @throws InvalidManifest
     */
    private function optionalText(array $data, string $field): string
    {
        if (!array_key_exists($field, $data)) {
            return '';
        }

        return is_string($data[$field]) ? $data[$field] : throw InvalidManifest::because(sprintf('"%s" must be a string.', $field));
    }
}
