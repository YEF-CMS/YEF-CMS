<?php

declare(strict_types=1);

namespace Yef\Tests\Publishing;

use FilesystemIterator;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Yef\Domain\Publishing\PublicPath;
use Yef\Publishing\FilePublicArtifactPublisher;
use Yef\Tests\Support\TemporaryDirectory;

#[CoversClass(FilePublicArtifactPublisher::class)]
final class FilePublicArtifactPublisherTest extends TestCase
{
    private TemporaryDirectory $directory;

    private FilePublicArtifactPublisher $publisher;

    #[Override]
    protected function setUp(): void
    {
        $this->directory = new TemporaryDirectory();
        $this->publisher = new FilePublicArtifactPublisher($this->directory->path);
    }

    #[Override]
    protected function tearDown(): void
    {
        $this->directory->delete();
    }

    public function testPublishesIntoNestedDirectories(): void
    {
        $this->publisher->publish(new PublicPath('blog/hello/index.html'), '<p>Hi</p>');

        $this->assertSame('<p>Hi</p>', file_get_contents($this->directory->path . '/blog/hello/index.html'));
    }

    public function testPublishingAgainReplacesTheArtifactAndLeavesNoTemporaryFiles(): void
    {
        $path = new PublicPath('index.html');

        $this->publisher->publish($path, 'old');
        $this->publisher->publish($path, 'new');

        $this->assertSame('new', file_get_contents($this->directory->path . '/index.html'));
        $this->assertSame(['index.html'], $this->files());
    }

    public function testRemovingDeletesArtifactAndEmptyDirectoriesButKeepsTheRoot(): void
    {
        $this->publisher->publish(new PublicPath('blog/hello/index.html'), 'x');

        $this->publisher->remove(new PublicPath('blog/hello/index.html'));

        $this->assertDirectoryExists($this->directory->path);
        $this->assertSame([], $this->files());
        $this->assertDirectoryDoesNotExist($this->directory->path . '/blog');
    }

    public function testRemovingKeepsDirectoryThatStillHoldsAnotherPage(): void
    {
        $this->publisher->publish(new PublicPath('blog/index.html'), 'list');
        $this->publisher->publish(new PublicPath('blog/hello/index.html'), 'post');

        $this->publisher->remove(new PublicPath('blog/hello/index.html'));

        $this->assertSame(['blog/index.html'], $this->files());
    }

    public function testRemovingMissingArtifactDoesNothing(): void
    {
        $this->publisher->remove(new PublicPath('missing/index.html'));

        $this->assertSame([], $this->files());
    }

    /**
     * @return list<string>
     */
    private function files(): array
    {
        $found = [];
        $root = $this->directory->path;
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $found[] = substr($file->getPathname(), strlen($root) + 1);
            }
        }

        sort($found);

        return $found;
    }
}
