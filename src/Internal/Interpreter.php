<?php

declare(strict_types=1);

namespace DocxTemplate\Internal;

final class Interpreter
{
    /**
     * @param  list<array<int, mixed>>  $ast
     * @param  array<string, mixed>  $assigns
     */
    public static function render(array $ast, array $assigns): string
    {
        return self::renderNodes($ast, [$assigns]);
    }

    /**
     * @param  list<array<int, mixed>>  $nodes
     * @param  list<array<string, mixed>>  $scopes
     */
    private static function renderNodes(array $nodes, array $scopes): string
    {
        $out = '';
        foreach ($nodes as $node) {
            $out .= self::renderNode($node, $scopes);
        }

        return $out;
    }

    /**
     * @param  array<int, mixed>  $node
     * @param  list<array<string, mixed>>  $scopes
     */
    private static function renderNode(array $node, array $scopes): string
    {
        $kind = $node[0];

        if ($kind === 'text') {
            return $node[1];
        }

        if ($kind === 'var') {
            return self::xmlEscape(self::stringify(self::lookupScoped($scopes, $node[1])));
        }

        if ($kind === 'if') {
            return self::truthy(self::lookupScoped($scopes, $node[1]))
                ? self::renderNodes($node[2], $scopes)
                : '';
        }

        if ($kind === 'unless') {
            return self::truthy(self::lookupScoped($scopes, $node[1]))
                ? ''
                : self::renderNodes($node[2], $scopes);
        }

        if ($kind === 'each') {
            $value = self::lookupScoped($scopes, $node[1]);
            if (! is_array($value) || ! array_is_list($value)) {
                return '';
            }
            $out = '';
            foreach ($value as $item) {
                $out .= self::renderNodes($node[2], [self::itemScope($item), ...$scopes]);
            }

            return $out;
        }

        return '';
    }

    /**
     * @return array<string, mixed>
     */
    private static function itemScope(mixed $item): array
    {
        if (is_array($item) && ! array_is_list($item)) {
            $item['this'] = $item;

            return $item;
        }

        return ['this' => $item];
    }

    /**
     * @param  list<array<string, mixed>>  $scopes
     */
    private static function lookupScoped(array $scopes, string $path): mixed
    {
        $parts = explode('.', $path);
        foreach ($scopes as $scope) {
            $found = self::fetchPath($scope, $parts);
            if ($found !== self::miss()) {
                return $found;
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $parts
     */
    private static function fetchPath(mixed $value, array $parts): mixed
    {
        foreach ($parts as $part) {
            if (! is_array($value) || ! array_key_exists($part, $value)) {
                return self::miss();
            }
            $value = $value[$part];
        }

        return $value;
    }

    private static function miss(): object
    {
        static $sentinel;

        return $sentinel ??= new \stdClass;
    }

    private static function truthy(mixed $v): bool
    {
        if ($v === null || $v === false || $v === '' || $v === []) {
            return false;
        }

        return true;
    }

    private static function stringify(mixed $v): string
    {
        if ($v === null) {
            return '';
        }
        if (is_bool($v)) {
            return $v ? 'true' : 'false';
        }
        if (is_scalar($v)) {
            return (string) $v;
        }

        return '';
    }

    private static function xmlEscape(string $s): string
    {
        return strtr($s, ['&' => '&amp;', '<' => '&lt;', '>' => '&gt;']);
    }
}
