<?php

if (!class_exists('LowSeg2CatDiagnostics', false)) {
    final class LowSeg2CatDiagnostics
    {
        private static $events = array();

        /**
         * Retain every diagnostic before a caller can catch its exception.
         *
         * @param int $severity Diagnostic level.
         * @param string $message Diagnostic message.
         * @param string $file Source file.
         * @param int $line Source line.
         * @return void
         * @throws ErrorException
         */
        public static function handle($severity, $message, $file, $line)
        {
            self::$events[] = array($severity, $message, $file, $line);
            throw new ErrorException($message, 0, $severity, $file, $line);
        }

        /**
         * Count diagnostics independently of mutable test globals.
         *
         * @return int
         */
        public static function count()
        {
            return count(self::$events);
        }

        /**
         * Fail a checked process even after a diagnostic exception was caught.
         *
         * @return void
         */
        public static function finish()
        {
            if (!self::$events) {
                return;
            }
            foreach (self::$events as $event) {
                file_put_contents('php://stderr', 'LOW_SEG2CAT_DIAGNOSTIC ' . json_encode($event, JSON_UNESCAPED_SLASHES) . "\n");
            }
            $last = error_get_last();
            if ($last && in_array($last['type'], array(E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR), true)) {
                return;
            }
            exit(2);
        }
    }
    register_shutdown_function(array('LowSeg2CatDiagnostics', 'finish'));
    $lowSeg2CatIdentityPath = getenv('LOW_SEG2CAT_RUNTIME_IDENTITIES');
    if ($lowSeg2CatIdentityPath) {
        file_put_contents($lowSeg2CatIdentityPath, json_encode(array('pid' => getmypid(), 'version' => PHP_VERSION,
            'binary' => PHP_BINARY, 'error_reporting' => error_reporting(), 'display_errors' => ini_get('display_errors'),
            'display_startup_errors' => ini_get('display_startup_errors'), 'loaded_ini' => php_ini_loaded_file(),
            'scanned_ini' => php_ini_scanned_files(), 'entry' => $_SERVER['SCRIPT_FILENAME'] ?? null)) . "\n", FILE_APPEND | LOCK_EX);
    }
}

error_reporting(-1);
set_error_handler(array('LowSeg2CatDiagnostics', 'handle'), E_ALL);
