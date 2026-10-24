<?php

namespace Low\Seg2Cat\FluxCapacitor\Conduit;

class SerializedData
{
    /**
     * Decode persisted values with the original native valid-prefix tolerance.
     *
     * @param mixed $value Stored serialized bytes.
     * @param bool|null $valid Whether native parsing completed without a known parse error.
     * @return mixed Native decoded prefix value, or false when native parsing fails.
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
        }

        $valid = true;
        $parse_line = 0;
        $previous = null;
        $previous = set_error_handler(static function ($severity, $message, $file, $line) use (&$valid, &$previous, &$parse_line) {
            if (($severity === E_WARNING || $severity === E_NOTICE) && $file === __FILE__ && $line === $parse_line) {
                if (preg_match('/\Aunserialize\(\): (?:Error at offset [0-9]+ of [0-9]+ bytes|Unexpected end of serialized data|Maximum depth of [0-9]+ exceeded\. The depth limit can be changed using the max_depth unserialize\(\) option or the unserialize_max_depth ini setting)\z/', $message)) {
                    // Native failure cleanup still runs deferred object callbacks.
                    $valid = false;
                    return true;
                }
                if (preg_match('/\AInsufficient data for unserializing - -?[0-9]+ required, [0-9]+ present\z/', $message)) {
                    $valid = false;
                    return true;
                }
                // Native integer overflow clamps the value and completes parsing.
                if ($message === 'unserialize(): Numerical result out of range') {
                    return true;
                }
                // PHP 7.4 accepted a complete first value followed by unused bytes.
                if (preg_match('/\Aunserialize\(\): Extra data starting at offset [0-9]+ of [0-9]+ bytes\z/', $message)) {
                    return true;
                }
            }
            if ($previous !== null) {
                return $previous(...func_get_args());
            }
            return false;
        }, E_ALL);
        try {
            $parse_line = __LINE__ + 1;
            return unserialize($value);
        } finally {
            restore_error_handler();
        }
    }

    /**
     * Rewrite valid string tokens and leave malformed remainders to native callback cleanup.
     *
     * @param string $value
     * @return string Normalized prefix and untouched unrecognized remainder.
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
                return $result . substr($value, $start);
            }

            $size = (int) $match[2];
            $position += strlen($match[0]);
            $contents = '';

            if ($type === 'S') {
                for ($i = 0; $i < $size; $i++) {
                    if ($position >= $length) {
                        return $result . substr($value, $start);
                    }
                    $byte = $value[$position++];
                    if ($byte === '\\') {
                        $hex = substr($value, $position, 2);
                        if (strlen($hex) !== 2 || strspn($hex, '0123456789abcdefABCDEF') !== 2) {
                            return $result . substr($value, $start);
                        }
                        $byte = chr(hexdec($hex));
                        $position += 2;
                    }
                    $contents .= $byte;
                }
            } else {
                if ($size > $length - $position) {
                    return $result . substr($value, $start);
                }
                $position += $size;
            }

            if ($type === 'O' || $type === 'C') {
                if (!preg_match('/\G":([+-]?[0-9]+):\{/', $value, $tail, 0, $position)) {
                    return $result . substr($value, $start);
                }
                $position += strlen($tail[0]);
                if ($type === 'C') {
                    $payload_length = (int) $tail[1];
                    if ($payload_length < 0 || $payload_length >= $length - $position || $value[$position + $payload_length] !== '}') {
                        return $result . substr($value, $start);
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
                return $result . substr($value, $start);
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
