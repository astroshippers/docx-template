<?php

declare(strict_types=1);

namespace DocxTemplate\Internal;

use DocxTemplate\TemplateException;

final class Parser
{
    private const TAG = '/\{\{\s*([#\/]?)\s*([a-zA-Z_][a-zA-Z0-9_.]*)(?:\s+([a-zA-Z_][a-zA-Z0-9_.]*))?\s*\}\}/';

    /**
     * @return list<array{0: string, 1: mixed, 2?: list<mixed>}>
     */
    public static function parse(string $template): array
    {
        $tokens = self::tokenize($template);
        [$ast, $rest] = self::parseNodes($tokens, null, 0);

        if ($rest < count($tokens)) {
            $tok = $tokens[$rest];
            throw new TemplateException("unbalanced template: unexpected {{/{$tok[1]}}}");
        }

        return $ast;
    }

    /**
     * @return list<array{0: string, 1?: string, 2?: string}>
     */
    private static function tokenize(string $template): array
    {
        $tokens = [];
        $cursor = 0;
        if (preg_match_all(self::TAG, $template, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER) === false) {
            return [];
        }

        foreach ($matches as $m) {
            $fullText = $m[0][0];
            $fullOffset = $m[0][1];
            if ($fullOffset > $cursor) {
                $tokens[] = ['text', substr($template, $cursor, $fullOffset - $cursor)];
            }
            $prefix = $m[1][0] ?? '';
            $name = $m[2][0] ?? '';
            $arg = $m[3][0] ?? '';
            $tokens[] = self::classify($prefix, $name, $arg);
            $cursor = $fullOffset + strlen($fullText);
        }
        if ($cursor < strlen($template)) {
            $tokens[] = ['text', substr($template, $cursor)];
        }

        return $tokens;
    }

    /**
     * @return array{0: string, 1?: string, 2?: string}
     */
    private static function classify(string $prefix, string $name, string $arg): array
    {
        if ($prefix === '' && $arg === '') {
            return ['var', $name];
        }
        if ($prefix === '#' && in_array($name, ['if', 'unless', 'each'], true) && $arg !== '') {
            return ['open', $name, $arg];
        }
        if ($prefix === '/' && in_array($name, ['if', 'unless', 'each'], true) && $arg === '') {
            return ['close', $name];
        }
        throw new TemplateException("invalid tag: {{{$prefix}{$name} {$arg}}}");
    }

    /**
     * @param  list<array<int, string>>  $tokens
     * @return array{0: list<mixed>, 1: int}
     */
    private static function parseNodes(array $tokens, ?string $expected, int $i): array
    {
        $acc = [];
        $n = count($tokens);

        while ($i < $n) {
            $tok = $tokens[$i];
            $kind = $tok[0];

            if ($kind === 'close') {
                if ($expected === null) {
                    return [$acc, $i];
                }
                if ($expected === $tok[1]) {
                    return [$acc, $i + 1];
                }
                throw new TemplateException("mismatched close: expected {{/{$expected}}}, got {{/{$tok[1]}}}");
            }

            if ($kind === 'text') {
                $acc[] = ['text', $tok[1]];
                $i++;
                continue;
            }

            if ($kind === 'var') {
                $acc[] = ['var', $tok[1]];
                $i++;
                continue;
            }

            if ($kind === 'open') {
                $blockKind = $tok[1];
                $path = $tok[2];
                [$children, $next] = self::parseNodes($tokens, $blockKind, $i + 1);
                $acc[] = [$blockKind, $path, $children];
                $i = $next;
                continue;
            }
        }

        if ($expected !== null) {
            throw new TemplateException("unbalanced template: missing {{/{$expected}}}");
        }

        return [$acc, $i];
    }
}
