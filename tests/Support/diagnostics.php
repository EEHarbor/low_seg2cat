<?php

error_reporting(E_ALL);
/**
 * Reject startup diagnostics before test support or PHPUnit owns the handler.
 *
 * @param int $severity
 * @param string $message
 * @param string $file
 * @param int $line
 * @return void
 * @throws \ErrorException
 */
set_error_handler(static function ($severity, $message, $file, $line) {
    throw new \ErrorException($message, 0, $severity, $file, $line);
});
