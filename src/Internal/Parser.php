<?php

declare(strict_types=1);

namespace DocxTemplate\Internal;

use DocxTemplate\Internal\Ast\EachNode;
use DocxTemplate\Internal\Ast\IfNode;
use DocxTemplate\Internal\Ast\Node;
use DocxTemplate\Internal\Ast\TextNode;
use DocxTemplate\Internal\Ast\UnlessNode;
use DocxTemplate\Internal\Ast\VarNode;
use DocxTemplate\Internal\Token\BlockKind;
use DocxTemplate\Internal\Token\CloseToken;
use DocxTemplate\Internal\Token\OpenToken;
use DocxTemplate\Internal\Token\TextToken;
use DocxTemplate\Internal\Token\Token;
use DocxTemplate\Internal\Token\VarToken;
use DocxTemplate\TemplateException;

final readonly class Parser
{
    private const string TAG = '/\{\{\s*([#\/]?)\s*([a-zA-Z_][a-zA-Z0-9_.]*)(?:\s+([a-zA-Z_][a-zA-Z0-9_.]*))?\s*\}\}/';

    /**
     * @return list<Node>
     */
    public function parse(string $template): array
    {
        $tokens = $this->tokenize($template);
        [$ast, $rest] = $this->parseNodes($tokens, null, 0);

        if ($rest < count($tokens)) {
            $tok = $tokens[$rest];
            if ($tok instanceof CloseToken) {
                throw new TemplateException(sprintf('unbalanced template: unexpected {{/%s}}', $tok->kind->value));
            }
        }

        return $ast;
    }

    /**
     * @return list<Token>
     */
    private function tokenize(string $template): array
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
                $tokens[] = new TextToken(substr($template, $cursor, $fullOffset - $cursor));
            }

            $prefix = $m[1][0];
            $name = $m[2][0];
            $arg = isset($m[3]) ? $m[3][0] : '';
            $tokens[] = $this->classify($prefix, $name, $arg);
            $cursor = $fullOffset + strlen($fullText);
        }

        if ($cursor < strlen($template)) {
            $tokens[] = new TextToken(substr($template, $cursor));
        }

        return $tokens;
    }

    private function classify(string $prefix, string $name, string $arg): Token
    {
        if ($prefix === '' && $arg === '') {
            return new VarToken($name);
        }

        if ($prefix === '#' && $arg !== '') {
            $kind = BlockKind::tryFrom($name);
            if ($kind !== null) {
                return new OpenToken($kind, $arg);
            }
        }

        if ($prefix === '/' && $arg === '') {
            $kind = BlockKind::tryFrom($name);
            if ($kind !== null) {
                return new CloseToken($kind);
            }
        }

        throw new TemplateException(sprintf('invalid tag: {{%s%s %s}}', $prefix, $name, $arg));
    }

    /**
     * @param  list<Token>  $tokens
     * @return array{0: list<Node>, 1: int}
     */
    private function parseNodes(array $tokens, ?BlockKind $expected, int $i): array
    {
        $acc = [];
        $n = count($tokens);

        while ($i < $n) {
            $tok = $tokens[$i];

            if ($tok instanceof CloseToken) {
                if (! $expected instanceof BlockKind) {
                    return [$acc, $i];
                }

                if ($expected === $tok->kind) {
                    return [$acc, $i + 1];
                }

                throw new TemplateException(sprintf('mismatched close: expected {{/%s}}, got {{/%s}}', $expected->value, $tok->kind->value));
            }

            if ($tok instanceof TextToken) {
                $acc[] = new TextNode($tok->text);
                $i++;

                continue;
            }

            if ($tok instanceof VarToken) {
                $acc[] = new VarNode($tok->path);
                $i++;

                continue;
            }

            if ($tok instanceof OpenToken) {
                [$children, $next] = $this->parseNodes($tokens, $tok->kind, $i + 1);
                $acc[] = match ($tok->kind) {
                    BlockKind::If_ => new IfNode($tok->path, $children),
                    BlockKind::Unless => new UnlessNode($tok->path, $children),
                    BlockKind::Each => new EachNode($tok->path, $children),
                };
                $i = $next;
            }
        }

        if ($expected instanceof BlockKind) {
            throw new TemplateException(sprintf('unbalanced template: missing {{/%s}}', $expected->value));
        }

        return [$acc, $i];
    }
}
