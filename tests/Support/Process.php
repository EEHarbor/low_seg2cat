<?php

namespace Tests\Support;

class Process
{
    /**
     * Run a command without shell interpolation and retain both output streams.
     *
     * @param array $command
     * @param array|null $environment
     * @param float $timeoutSeconds
     * @return array
     * @throws \InvalidArgumentException
     * @throws \RuntimeException
     */
    public static function execute(array $command, ?array $environment = null, $timeoutSeconds = 15.0)
    {
        if (!is_numeric($timeoutSeconds) || !is_finite((float) $timeoutSeconds) || $timeoutSeconds <= 0) {
            throw new \InvalidArgumentException('Process deadline must be positive and finite.');
        }
        $output = tmpfile();
        $errors = tmpfile();
        if ($output === false || $errors === false) {
            if (is_resource($output)) {
                fclose($output);
            }
            if (is_resource($errors)) {
                fclose($errors);
            }
            throw new \RuntimeException('Could not allocate process output streams.');
        }
        $process = null;
        try {
            $process = proc_open($command, array(0 => array('pipe', 'r'), 1 => $output, 2 => $errors), $pipes, null, $environment);
            if (!is_resource($process)) {
                throw new \RuntimeException('Could not start PHP process.');
            }
            fclose($pipes[0]);
            $deadline = hrtime(true) + (int) ($timeoutSeconds * 1000000000);
            $state = self::waitForExit($process, $deadline);
            $timedOut = $state['running'];
            if ($timedOut) {
                proc_terminate($process);
                $state = self::waitForExit($process, hrtime(true) + 200000000);
            }
            if ($state['running']) {
                proc_terminate($process, 9);
                $state = self::waitForExit($process, hrtime(true) + 1000000000);
            }
            if ($state['running']) {
                throw new \RuntimeException('Owned PHP child did not terminate after SIGKILL.');
            }
            // Older PHP loses the collected exit code when proc_close reaps it.
            $exitCode = $state['exitcode'];
            $closed = proc_close($process);
            $process = null;
            $status = $exitCode >= 0 ? $exitCode : $closed;
            if ($status < 0 && $state['signaled']) {
                $status = 128 + $state['termsig'];
            }
            rewind($output);
            rewind($errors);
            return array('status' => $timedOut ? 124 : $status, 'timed_out' => $timedOut,
                'stdout' => stream_get_contents($output), 'stderr' => stream_get_contents($errors));
        } finally {
            if (is_resource($process)) {
                proc_terminate($process, 9);
                proc_close($process);
            }
            fclose($output);
            fclose($errors);
        }
    }

    /**
     * Poll one owned child until it exits or the monotonic deadline arrives.
     *
     * @param resource $process
     * @param int $deadline
     * @return array
     */
    private static function waitForExit($process, $deadline)
    {
        do {
            $state = proc_get_status($process);
            if (!$state['running'] || hrtime(true) >= $deadline) {
                return $state;
            }
            usleep(10000);
        } while (true);
    }

}
