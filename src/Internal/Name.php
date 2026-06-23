<?php

declare(strict_types=1);

namespace DocxTemplate\Internal;

/**
 * A validated template name. One or more segments separated by `.` (and,
 * for variable names, single spaces). Each segment starts with a letter
 * or underscore and continues with letters, digits, or underscore.
 */
final readonly class Name
{
    private function __construct(public string $value) {}

    public static function variable(string $s): ?self
    {
        return self::isValid($s, allowSpaces: true) ? new self($s) : null;
    }

    public static function blockArg(string $s): ?self
    {
        return self::isValid($s, allowSpaces: false) ? new self($s) : null;
    }

    private static function isValid(string $s, bool $allowSpaces): bool
    {
        $atSegmentStart = true;
        for ($i = 0, $len = strlen($s); $i < $len; $i++) {
            $c = $s[$i];
            $isSep = $c === '.' || ($allowSpaces && $c === ' ');

            if ($isSep) {
                if ($atSegmentStart) {
                    return false;
                }

                $atSegmentStart = true;
            } elseif ($atSegmentStart) {
                if (! self::isNameStart($c)) {
                    return false;
                }

                $atSegmentStart = false;
            } elseif (! self::isNameCont($c)) {
                return false;
            }
        }

        return ! $atSegmentStart;
    }

    private static function isNameStart(string $c): bool
    {
        return ($c >= 'a' && $c <= 'z') || ($c >= 'A' && $c <= 'Z') || $c === '_';
    }

    private static function isNameCont(string $c): bool
    {
        if (self::isNameStart($c)) {
            return true;
        }

        return $c >= '0' && $c <= '9';
    }
}
