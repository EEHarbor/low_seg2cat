<?php

namespace Low\Seg2Cat\FluxCapacitor\Conduit;

class SerializedData
{
    /**
     * Decode persisted values with the original native valid-prefix tolerance.
     *
     * @param mixed $value Stored serialized bytes.
     * @param bool|null $valid Whether a native value prefix parsed successfully.
     * @return mixed Decoded prefix value, or false for invalid storage or a serialized false.
     * @throws \Throwable When a user callback or external diagnostic handler throws.
     */
    public static function decode($value, &$valid = null)
    {
        $valid = false;
        if (!is_string($value) || $value === '') {
            return false;
        }
        if (strpos($value, 'S:') !== false) {
            $value = self::normalizeEscapedStrings($value);
            if ($value === false) {
                return false;
            }
        }

        $valid = true;
        $parser_line = 0;
        $previous = null;
        $previous = set_error_handler(static function ($severity, $message, $file, $line) use (&$valid, &$previous, &$parser_line) {
            if (($severity === E_WARNING || $severity === E_NOTICE) && $file === __FILE__ && $line === $parser_line) {
                if (preg_match('/\Aunserialize\(\): (?:Error at offset [0-9]+ of [0-9]+ bytes|Unexpected end of serialized data)\z/', $message)) {
                    $valid = false;
                    return true;
                }
                // PHP 7.4 accepted a complete first value followed by unused bytes.
                if (preg_match('/\Aunserialize\(\): Extra data starting at offset [0-9]+ of [0-9]+ bytes\z/', $message)) {
                    return true;
                }
            }
            if ($previous !== null) {
                return $previous($severity, $message, $file, $line);
            }
            return false;
        }, -1);
        try {
            $parser_line = __LINE__ + 1;
            return unserialize($value);
        } finally {
            restore_error_handler();
        }
    }

    /**
     * Rewrite string tokens without interpreting bytes inside ordinary strings.
     *
     * @param string $value
     * @return string|false
     */
    private static function normalizeEscapedStrings($value)
    {
        $length = strlen($value);
        $position = 0;
        $result = '';
        $depth = 0;

        while ($position < $length) {
            $start = $position;
            $type = $value[$position];

            if ($type === '}') {
                $result .= '}';
                $position++;
                $depth--;
                if ($depth <= 0) {
                    return $result . substr($value, $position);
                }
                continue;
            }

            if (preg_match('/\G(?:N;|[bidrR]:[^;]*;|a:[0-9]+:\{)/', $value, $match, 0, $position)) {
                $result .= $match[0];
                $position += strlen($match[0]);
                if (substr($match[0], -1) === '{') {
                    $depth++;
                }
                if ($depth === 0) {
                    return $result . substr($value, $position);
                }
                continue;
            }

            if (!preg_match('/\G([sSEOC]):([0-9]+):"/', $value, $match, 0, $position)) {
                return false;
            }

            $size = (int) $match[2];
            $position += strlen($match[0]);
            $contents = '';

            if ($type === 'S') {
                for ($i = 0; $i < $size; $i++) {
                    if ($position >= $length) {
                        return false;
                    }
                    $byte = $value[$position++];
                    if ($byte === '\\') {
                        $hex = substr($value, $position, 2);
                        if (strlen($hex) !== 2 || strspn($hex, '0123456789abcdefABCDEF') !== 2) {
                            return false;
                        }
                        $byte = chr(hexdec($hex));
                        $position += 2;
                    }
                    $contents .= $byte;
                }
            } else {
                if ($size > $length - $position) {
                    return false;
                }
                $position += $size;
            }

            if ($type === 'O' || $type === 'C') {
                if (!preg_match('/\G":([0-9]+):\{/', $value, $tail, 0, $position)) {
                    return false;
                }
                $position += strlen($tail[0]);
                if ($type === 'C') {
                    $payload_length = (int) $tail[1];
                    if ($payload_length >= $length - $position || $value[$position + $payload_length] !== '}') {
                        return false;
                    }
                    $position += $payload_length + 1;
                }
                $result .= substr($value, $start, $position - $start);
                if ($type === 'O') {
                    $depth++;
                }
                if ($depth === 0) {
                    return $result . substr($value, $position);
                }
                continue;
            }

            if (substr($value, $position, 2) !== '";') {
                return false;
            }
            $position += 2;
            $result .= $type === 'S' ? 's:' . strlen($contents) . ':"' . $contents . '";' : substr($value, $start, $position - $start);
            if ($depth === 0) {
                return $result . substr($value, $position);
            }
        }

        return $result;
    }
}
