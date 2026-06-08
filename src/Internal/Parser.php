<?php

declare(strict_types=1);

namespace DocxTemplate\Internal;

use DocxTemplate\Internal\Ast\EachNode;
use DocxTemplate\Internal\Ast\IfNode;
use DocxTemplate\Internal\Ast\Node;
use DocxTemplate\Internal\Ast\TextNode;
use DocxTemplate\Internal\Ast\UnlessNode;
use DocxTemplate\Internal\Ast\VarNode;
use DocxTemplate\TemplateException;

final class Parser
{
    private const string TAG = '/\{\{\s*([#\/]?)\s*([a-zA-Z_][a-zA-Z0-9_.]*)(?:\s+([a-zA-Z_][a-zA-Z0-9_.]*))?\s*\}\}/';

    /**
     * @return list<Node>
     */
    public static function parse(string $template): array
    {
        $tokens = self::tokenize($template);
        [$ast, $rest] = self::parseNodes($tokens, null, 0);

        if ($rest < count($tokens)) {
            $tok = $tokens[$rest];
            throw new TemplateException(sprintf('unbalanced template: unexpected {{/%s}}', $tok[1]));
        }

        return $ast;
    }

    /**
     * Tokens shape: ['text', string] | ['var', string] | ['open', string, string] | ['close', string]
     *
     * @return list<array<int, string>>
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

            $prefix = $m[1][0];
            $name = $m[2][0];
            $arg = isset($m[3]) ? $m[3][0] : '';
            $tokens[] = self::classify($prefix, $name, $arg);
            $cursor = $fullOffset + strlen($fullText);
        }

        if ($cursor < strlen($template)) {
            $tokens[] = ['text', substr($template, $cursor)];
        }

        return $tokens;
    }

    /**
     * @return list<string>
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

        throw new TemplateException(sprintf('invalid tag: {{%s%s %s}}', $prefix, $name, $arg));
    }

    /**
     * @param  list<array<int, string>>  $tokens
     * @return array{0: list<Node>, 1: int}
     */
    private static function parseNodes(array $tokens, ?string $expected, int $i): array
    {
        $acc = [];
        $n = count($tokens);

        while ($i < $n) {
            $tok = $tokens[$i];
            $kind = $tok[0];

            if ($kind === 'close') {
                $closeKind = $tok[1];
                if ($expected === null) {
                    return [$acc, $i];
                }

                if ($expected === $closeKind) {
                    return [$acc, $i + 1];
                }

                throw new TemplateException(sprintf('mismatched close: expected {{/%s}}, got {{/%s}}', $expected, $closeKind));
            }

            if ($kind === 'text') {
                $acc[] = new TextNode($tok[1]);
                $i++;

                continue;
            }

            if ($kind === 'var') {
                $acc[] = new VarNode($tok[1]);
                $i++;

                continue;
            }

            // open
            $blockKind = $tok[1];
            $path = $tok[2];
            [$children, $next] = self::parseNodes($tokens, $blockKind, $i + 1);
            $acc[] = match ($blockKind) {
                'if' => new IfNode($path, $children),
                'unless' => new UnlessNode($path, $children),
                'each' => new EachNode($path, $children),
                default => throw new TemplateException('unknown block: '.$blockKind),
            };
            $i = $next;
        }

        if ($expected !== null) {
            throw new TemplateException(sprintf('unbalanced template: missing {{/%s}}', $expected));
        }

        return [$acc, $i];
    }
}
