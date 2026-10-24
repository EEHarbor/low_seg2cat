<?php

if (!class_exists('LowSeg2CatDiagnostics', false)) {
final class LowSeg2CatDiagnostics
{
    private static $journal;
    private static $shutdown = false;

    /**
     * Bind this checked invocation and its native descendants to durable receipts.
     *
     * @return void
     * @throws RuntimeException When the parent cannot create its journal.
     */
    public static function initialize(): void
    {
        self::$journal = getenv('LOW_SEG2CAT_DIAGNOSTIC_JOURNAL');
        if (!self::$journal) {
            self::$journal = tempnam(sys_get_temp_dir(), 'low_seg2cat-diagnostic-');
            if (self::$journal === false) {
                throw new RuntimeException('Cannot create the checked diagnostic journal.');
            }
            putenv('LOW_SEG2CAT_DIAGNOSTIC_JOURNAL=' . self::$journal);
        }
    }

    /**
     * Return the immutable receipt destination owned by this invocation.
     *
     * @return string
     */
    public static function journal(): string
    {
        return self::$journal;
    }

    /**
     * Persist every diagnostic before throwing, including caught or masked events.
     *
     * @param int $severity PHP diagnostic level.
     * @param string $message Diagnostic text.
     * @param string $file Source filename.
     * @param int $line Source line.
     * @return void
     * @throws ErrorException
     */
    public static function handle($severity, $message, $file, $line): void
    {
        self::retain(array($severity, $message, $file, $line));
        if (self::$shutdown) {
            exit(2);
        }
        throw new ErrorException($message, 0, $severity, $file, $line);
    }

    /**
     * Write a receipt before shutdown, process termination, or caller exception handling.
     *
     * @param array $event Severity, message, source filename and line.
     * @return void
     */
    private static function retain(array $event): void
    {
        $encoded = json_encode($event, JSON_UNESCAPED_SLASHES) . "\n";
        file_put_contents(self::$journal, $encoded, FILE_APPEND | LOCK_EX);
        file_put_contents('php://stderr', 'LOW_SEG2CAT_DIAGNOSTIC ' . $encoded);
    }

    /**
     * Count durable parent and child events independently of returned process results.
     *
     * @return int
     */
    public static function count(): int
    {
        $bytes = file_get_contents(self::$journal);
        return $bytes === '' ? 0 : substr_count($bytes, "\n");
    }

    /**
     * Retain native startup or compilation diagnostics that cannot reach a PHP handler.
     *
     * @param string $stderr Complete captured child diagnostic stream.
     * @param string $journal Parent-owned child receipt destination.
     * @param int $before Receipt byte length before the child starts.
     * @return void
     */
    public static function observeNativeOutput($stderr, $journal, $before): void
    {
        if (!preg_match_all('/^(?:PHP )?(?:Warning|Notice|Deprecated|Fatal error|Parse error|Recoverable fatal error|Strict Standards): [^\\r\\n]+/m', $stderr, $diagnostics)) {
            return;
        }
        $events = array();
        foreach (explode("\n", trim(substr(file_get_contents($journal), $before))) as $encoded) {
            $event = json_decode($encoded, true);
            if (is_array($event) && count($event) === 4) {
                $events[] = $event;
            }
        }
        foreach ($diagnostics[0] as $diagnostic) {
            $native = preg_replace('/\\APHP /', '', $diagnostic);
            $recorded = false;
            foreach ($events as $event) {
                $fatal = 'Fatal error: Uncaught ErrorException: ' . $event[1] . ' in ' . $event[2] . ':' . $event[3];
                if (strpos($native, $fatal) === 0) {
                    $recorded = true;
                    break;
                }
            }
            if (!$recorded) {
                $event = array(E_WARNING, 'Unhandled native diagnostic: ' . $diagnostic, __FILE__, __LINE__);
                file_put_contents($journal, json_encode($event, JSON_UNESCAPED_SLASHES) . "\n", FILE_APPEND | LOCK_EX);
            }
        }
    }

    /**
     * Validate an explicitly supervised negative control and retain every other event.
     *
     * @param string $journal Isolated receipt file created by the parent.
     * @param array $expected Exact severity and anchored message patterns.
     * @return void
     */
    public static function joinControl($journal, array $expected): void
    {
        $bytes = file_get_contents($journal);
        if ($bytes === '') {
            self::retain(array(E_WARNING, 'Supervised diagnostic control produced no receipt.', __FILE__, __LINE__));
            return;
        }
        foreach (explode("\n", rtrim($bytes, "\n")) as $encoded) {
            $event = json_decode($encoded, true);
            if (!is_array($event) || count($event) !== 4 || !is_int($event[0]) || !is_string($event[1]) || !is_string($event[2]) || !is_int($event[3])) {
                self::retain(array(E_WARNING, 'Invalid supervised diagnostic receipt.', __FILE__, __LINE__));
                continue;
            }
            $accepted = false;
            foreach ($expected as $diagnostic) {
                if (in_array($event[0], (array) $diagnostic[0], true) && preg_match($diagnostic[1], $event[1]) === 1) {
                    $accepted = true;
                    break;
                }
            }
            if (!$accepted) {
                self::retain($event);
            }
        }
    }

    /**
     * Reject the invocation even when a native result or exception was ignored.
     *
     * @return void
     */
    public static function finish(): void
    {
        self::$shutdown = true;
        $count = self::count();
        if ($count !== 0) {
            foreach (explode("\n", rtrim(file_get_contents(self::$journal), "\n")) as $event) {
                file_put_contents('php://stderr', 'LOW_SEG2CAT_DIAGNOSTIC ' . $event . "\n");
            }
        }
        if ($count === 0) {
            return;
        }
        $last = error_get_last();
        if ($last && in_array($last['type'], array(E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR), true)) {
            return;
        }
        exit(2);
    }
}

LowSeg2CatDiagnostics::initialize();
error_reporting(-1);
set_error_handler(array(LowSeg2CatDiagnostics::class, 'handle'), E_ALL);
register_shutdown_function(array(LowSeg2CatDiagnostics::class, 'finish'));
$lowSeg2CatIdentityPath = getenv('LOW_SEG2CAT_RUNTIME_IDENTITIES');
    if ($lowSeg2CatIdentityPath) {
        file_put_contents($lowSeg2CatIdentityPath, json_encode(array('pid' => getmypid(), 'version' => PHP_VERSION,
            'binary' => PHP_BINARY, 'error_reporting' => error_reporting(), 'display_errors' => ini_get('display_errors'),
            'display_startup_errors' => ini_get('display_startup_errors'), 'loaded_ini' => php_ini_loaded_file(),
            'scanned_ini' => php_ini_scanned_files(), 'entry' => $_SERVER['SCRIPT_FILENAME'] ?? null)) . "\n", FILE_APPEND | LOCK_EX);
    }
}
error_reporting(-1);
set_error_handler(array(LowSeg2CatDiagnostics::class, 'handle'), E_ALL);
