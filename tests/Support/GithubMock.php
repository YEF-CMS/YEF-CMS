<?php

declare(strict_types=1);

namespace Yef\Tests\Support;

use RuntimeException;

/**
 * Starts the stand-in for GitHub (tests/Support/GithubMock/router.php) on a free local port.
 */
final class GithubMock
{
    /** @var resource|null */
    private $process;

    private int $port = 0;

    public function start(): void
    {
        $this->port = $this->freePort();
        $command = [PHP_BINARY, '-S', '127.0.0.1:' . $this->port, __DIR__ . '/GithubMock/router.php'];
        $process = proc_open($command, [['pipe', 'r'], ['file', '/dev/null', 'w'], ['file', '/dev/null', 'w']], $pipes);
        if (!is_resource($process)) {
            throw new RuntimeException('The stand-in for GitHub could not be started.');
        }

        $this->process = $process;
        for ($attempt = 0; $attempt < 100; ++$attempt) {
            $socket = @fsockopen('127.0.0.1', $this->port, $errorCode, $errorMessage, 0.1);
            if ($socket !== false) {
                fclose($socket);

                return;
            }

            usleep(50_000);
        }

        $this->stop();

        throw new RuntimeException('The stand-in for GitHub did not start in time.');
    }

    public function stop(): void
    {
        if ($this->process !== null) {
            proc_terminate($this->process);
            proc_close($this->process);
            $this->process = null;
        }
    }

    public function address(): string
    {
        return 'http://127.0.0.1:' . $this->port;
    }

    public function host(): string
    {
        return '127.0.0.1:' . $this->port;
    }

    /**
     * A local port that nothing listens on.
     */
    public function closedPort(): int
    {
        return $this->freePort();
    }

    private function freePort(): int
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0', $errorCode, $errorMessage);
        if ($socket === false) {
            throw new RuntimeException('No free port: ' . $errorMessage);
        }

        $name = stream_socket_get_name($socket, false);
        fclose($socket);

        return (int) substr((string) $name, (int) strrpos((string) $name, ':') + 1);
    }
}
