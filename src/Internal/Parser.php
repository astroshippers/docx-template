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
    /**
     * @return list<Node>
     */
    #[\NoDiscard]
    public function parse(string $template): array
    {
        $tokens = $this->tokenize($template);
        [$ast, $consumed] = $this->parseNodes($tokens, null, 0);

        if ($consumed < count($tokens) && $tokens[$consumed] instanceof CloseToken) {
            throw new TemplateException(sprintf(
                'unbalanced template: unexpected {{/%s}}',
                $tokens[$consumed]->kind->value,
            ));
        }

        return $ast;
    }

    /**
     * @return list<Token>
     */
    private function tokenize(string $template): array
    {
        $tokens = [];
        $len = strlen($template);
        $textStart = 0; // start of the current run of literal text
        $scanFrom = 0;  // where to look for the next `{{`

        while ($scanFrom < $len) {
            $tag = $this->findNextTag($template, $scanFrom);
            if ($tag === null) {
                break;
            }

            [$tagStart, $tagEnd, $rawInner] = $tag;
            $inner = $this->normalizeWhitespace($rawInner);

            // {{}} and {{   }} are treated as literal text — likely a typo, but
            // surfacing them verbatim in the document is less disruptive than throwing.
            if ($inner === '') {
                $scanFrom = $tagEnd;

                continue;
            }

            if ($tagStart > $textStart) {
                $tokens[] = new TextToken(substr($template, $textStart, $tagStart - $textStart));
            }

            $tokens[] = $this->classify($inner, substr($template, $tagStart, $tagEnd - $tagStart), $tagStart);
            $textStart = $tagEnd;
            $scanFrom = $tagEnd;
        }

        if ($textStart < $len) {
            $tokens[] = new TextToken(substr($template, $textStart));
        }

        return $tokens;
    }

    /**
     * Scan forward for the next `{{ ... }}` whose body contains no further braces.
     *
     * @return array{0: int, 1: int, 2: string}|null [startOffset, endOffsetExclusive, innerText]
     */
    private function findNextTag(string $s, int $from): ?array
    {
        while (true) {
            $start = strpos($s, '{{', $from);
            if ($start === false) {
                return null;
            }

            $end = strpos($s, '}}', $start + 2);
            if ($end === false) {
                return null;
            }

            $inner = substr($s, $start + 2, $end - $start - 2);
            if (strpbrk($inner, '{}') === false) {
                return [$start, $end + 2, $inner];
            }

            // Stray `{` or `}` inside — not a valid tag; keep scanning past this `{{`.
            $from = $start + 1;
        }
    }

    private function normalizeWhitespace(string $s): string
    {
        $out = '';
        $prevSpace = true; // leading whitespace gets trimmed

        for ($i = 0, $len = strlen($s); $i < $len; $i++) {
            $c = $s[$i];
            if ($this->isWhitespace($c)) {
                if (! $prevSpace) {
                    $out .= ' ';
                    $prevSpace = true;
                }
            } else {
                $out .= $c;
                $prevSpace = false;
            }
        }

        return rtrim($out);
    }

    private function classify(string $inner, string $rawTag, int $offset): Token
    {
        if ($inner === 'else' || str_starts_with($inner, 'else ')) {
            throw new TemplateException(sprintf(
                '%s at offset %d is not supported; if/unless blocks have no else branch',
                $rawTag, $offset,
            ));
        }

        if ($inner[0] === '#') {
            return $this->classifyOpen(ltrim(substr($inner, 1)), $rawTag, $offset);
        }

        if ($inner[0] === '/') {
            return $this->classifyClose(ltrim(substr($inner, 1)), $rawTag, $offset);
        }

        if ($this->isValidName($inner, allowSpaces: true)) {
            return new VarToken($inner);
        }

        throw new TemplateException(sprintf('invalid tag %s at offset %d', $rawTag, $offset));
    }

    private function classifyOpen(string $body, string $rawTag, int $offset): OpenToken
    {
        [$name, $arg] = $this->splitFirstSpace($body);
        $kind = BlockKind::tryFrom($name);

        if (! $kind instanceof BlockKind) {
            throw new TemplateException(sprintf(
                'unknown block %s at offset %d; expected one of: #if, #unless, #each',
                $rawTag, $offset,
            ));
        }

        if ($arg === '') {
            throw new TemplateException(sprintf(
                '%s at offset %d is missing a variable name (e.g. {{#%s items}})',
                $rawTag, $offset, $name,
            ));
        }

        if (! $this->isValidName($arg, allowSpaces: false)) {
            throw new TemplateException(sprintf(
                'invalid syntax in %s at offset %d; expected a single variable name after #%s',
                $rawTag, $offset, $name,
            ));
        }

        return new OpenToken($kind, $arg);
    }

    private function classifyClose(string $body, string $rawTag, int $offset): CloseToken
    {
        [$name, $arg] = $this->splitFirstSpace($body);
        $kind = BlockKind::tryFrom($name);

        if (! $kind instanceof BlockKind) {
            throw new TemplateException(sprintf(
                'unknown close tag %s at offset %d; expected one of: {{/if}}, {{/unless}}, {{/each}}',
                $rawTag, $offset,
            ));
        }

        if ($arg !== '') {
            throw new TemplateException(sprintf(
                'close tag %s at offset %d should not take arguments',
                $rawTag, $offset,
            ));
        }

        return new CloseToken($kind);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function splitFirstSpace(string $s): array
    {
        $parts = explode(' ', $s, 2);

        return [$parts[0], $parts[1] ?? ''];
    }

    /**
     * Identifiers start with a letter or underscore and continue with letters,
     * digits, underscore, or `.`. When `$allowSpaces` is true, single spaces
     * between segments are also allowed (e.g. variable names like "My column").
     */
    private function isValidName(string $s, bool $allowSpaces): bool
    {
        $len = strlen($s);
        if ($len === 0 || ! $this->isNameStart($s[0])) {
            return false;
        }

        $prevSpace = false;
        for ($i = 1; $i < $len; $i++) {
            $c = $s[$i];
            if ($c === ' ' && $allowSpaces) {
                if ($prevSpace) {
                    return false;
                }
                $prevSpace = true;
            } elseif ($this->isNameCont($c)) {
                $prevSpace = false;
            } else {
                return false;
            }
        }

        return ! $prevSpace;
    }

    private function isNameStart(string $c): bool
    {
        return ($c >= 'a' && $c <= 'z') || ($c >= 'A' && $c <= 'Z') || $c === '_';
    }

    private function isNameCont(string $c): bool
    {
        return $this->isNameStart($c) || ($c >= '0' && $c <= '9') || $c === '.';
    }

    private function isWhitespace(string $c): bool
    {
        return $c === ' ' || $c === "\t" || $c === "\n" || $c === "\r";
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

                throw new TemplateException(sprintf(
                    'mismatched close: expected {{/%s}}, got {{/%s}}',
                    $expected->value, $tok->kind->value,
                ));
            }

            if ($tok instanceof TextToken) {
                $acc[] = new TextNode($tok->text);
                $i++;
            } elseif ($tok instanceof VarToken) {
                $acc[] = new VarNode($tok->path);
                $i++;
            } elseif ($tok instanceof OpenToken) {
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
