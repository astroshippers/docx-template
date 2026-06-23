<?php

declare(strict_types=1);

namespace DocxTemplate\Internal;

use DocxTemplate\Internal\Ast\Node;
use DocxTemplate\Internal\Ast\TextNode;
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

            $tokens[] = $this->classify(new TagSource(
                raw: substr($template, $tagStart, $tagEnd - $tagStart),
                offset: $tagStart,
                inner: $inner,
            ));
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

    private function classify(TagSource $tag): Token
    {
        $inner = $tag->inner;

        if ($inner === 'else' || str_starts_with($inner, 'else ')) {
            $tag->fail(
                '%s at offset %d is not supported; if/unless blocks have no else branch',
                $tag->raw, $tag->offset,
            );
        }

        if ($inner[0] === '#') {
            return $this->classifyOpen(ltrim(substr($inner, 1)), $tag);
        }

        if ($inner[0] === '/') {
            return $this->classifyClose(ltrim(substr($inner, 1)), $tag);
        }

        $name = Name::variable($inner);
        if (! $name instanceof Name) {
            $tag->fail('invalid tag %s at offset %d', $tag->raw, $tag->offset);
        }

        return new VarToken($name->value);
    }

    private function classifyOpen(string $body, TagSource $tag): OpenToken
    {
        [$keyword, $arg] = $this->splitFirstSpace($body);
        $kind = BlockKind::tryFrom($keyword);

        if (! $kind instanceof BlockKind) {
            $tag->fail(
                'unknown block %s at offset %d; expected one of: %s',
                $tag->raw, $tag->offset, BlockKind::openListForError(),
            );
        }

        if ($arg === '') {
            $tag->fail(
                '%s at offset %d is missing a variable name (e.g. {{#%s items}})',
                $tag->raw, $tag->offset, $keyword,
            );
        }

        $name = Name::blockArg($arg);
        if (! $name instanceof Name) {
            $tag->fail(
                'invalid syntax in %s at offset %d; expected a single variable name after #%s',
                $tag->raw, $tag->offset, $keyword,
            );
        }

        return new OpenToken($kind, $name->value);
    }

    private function classifyClose(string $body, TagSource $tag): CloseToken
    {
        [$keyword, $arg] = $this->splitFirstSpace($body);
        $kind = BlockKind::tryFrom($keyword);

        if (! $kind instanceof BlockKind) {
            $tag->fail(
                'unknown close tag %s at offset %d; expected one of: %s',
                $tag->raw, $tag->offset, BlockKind::closeListForError(),
            );
        }

        if ($arg !== '') {
            $tag->fail(
                'close tag %s at offset %d should not take arguments',
                $tag->raw, $tag->offset,
            );
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

    private function isWhitespace(string $c): bool
    {
        return in_array($c, [' ', "\t", "\n", "\r"], true);
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
                $acc[] = $tok->kind->buildNode($tok->path, $children);
                $i = $next;
            }
        }

        if ($expected instanceof BlockKind) {
            throw new TemplateException(sprintf('unbalanced template: missing {{/%s}}', $expected->value));
        }

        return [$acc, $i];
    }
}
