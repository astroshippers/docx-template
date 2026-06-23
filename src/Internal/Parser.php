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
    public function __construct(private Tokenizer $tokenizer = new Tokenizer) {}

    /**
     * @return list<Node>
     */
    #[\NoDiscard]
    public function parse(string $template): array
    {
        $tokens = $this->tokenizer->tokenize($template);
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
