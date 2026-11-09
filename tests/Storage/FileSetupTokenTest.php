<?php

declare(strict_types=1);

namespace Yef\Tests\Storage;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Yef\Storage\FileSetupToken;
use Yef\Storage\UlidUserIdGenerator;
use Yef\Tests\Support\TemporaryDirectory;

#[CoversClass(FileSetupToken::class)]
#[CoversClass(UlidUserIdGenerator::class)]
final class FileSetupTokenTest extends TestCase
{
    private TemporaryDirectory $directory;

    #[Override]
    protected function setUp(): void
    {
        $this->directory = new TemporaryDirectory();
    }

    #[Override]
    protected function tearDown(): void
    {
        $this->directory->delete();
    }

    public function testCodeIsCreatedOnFirstUseAndStaysTheSame(): void
    {
        $first = $this->token()->current();

        $this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $first);
        $this->assertSame($first, $this->token()->current());
    }

    public function testCodeCanBeReadFromTheFileByTheOwner(): void
    {
        $code = $this->token()->current();

        $this->assertSame($code, trim((string) file_get_contents($this->file())));
        $this->assertSame(0o600, (int) fileperms($this->file()) & 0o777);
    }

    public function testConsumedCodeIsGoneAndANewOneIsDifferent(): void
    {
        $old = $this->token()->current();

        $this->token()->consume();

        $this->assertFileDoesNotExist($this->file());
        $this->assertNotSame($old, $this->token()->current());
    }

    public function testConsumingWithoutACodeDoesNothing(): void
    {
        $this->token()->consume();

        $this->assertFileDoesNotExist($this->file());
    }

    public function testEmptyFileIsReplacedByANewCode(): void
    {
        $this->token()->current();
        file_put_contents($this->file(), '');

        $this->assertNotSame('', $this->token()->current());
    }

    public function testGeneratedUserIdsAreValidAndUnique(): void
    {
        $generator = new UlidUserIdGenerator();

        $ids = [$generator->next()->value, $generator->next()->value, $generator->next()->value];

        $this->assertCount(3, array_unique($ids));
    }

    private function token(): FileSetupToken
    {
        return new FileSetupToken($this->file(), $this->directory->path . '/var/setup.lock');
    }

    private function file(): string
    {
        return $this->directory->path . '/var/setup-token';
    }
}
