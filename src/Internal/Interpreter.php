<?php

declare(strict_types=1);

namespace DocxTemplate\Internal;

use DocxTemplate\Internal\Ast\EachNode;
use DocxTemplate\Internal\Ast\IfNode;
use DocxTemplate\Internal\Ast\Node;
use DocxTemplate\Internal\Ast\TextNode;
use DocxTemplate\Internal\Ast\UnlessNode;
use DocxTemplate\Internal\Ast\VarNode;

final readonly class Interpreter
{
    /**
     * @param  list<Node>  $ast
     * @param  array<string, mixed>  $assigns
     */
    #[\NoDiscard]
    public function render(array $ast, array $assigns): string
    {
        return $this->renderNodes($ast, [$assigns]);
    }

    /**
     * @param  list<Node>  $nodes
     * @param  list<array<string, mixed>>  $scopes
     */
    private function renderNodes(array $nodes, array $scopes): string
    {
        $out = '';
        foreach ($nodes as $node) {
            $out .= $this->renderNode($node, $scopes);
        }

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $scopes
     */
    private function renderNode(Node $node, array $scopes): string
    {
        if ($node instanceof TextNode) {
            return $node->text;
        }

        if ($node instanceof VarNode) {
            return $this->lookupScoped($scopes, $node->path)
                |> $this->stringify(...)
                |> $this->xmlEscape(...);
        }

        if ($node instanceof IfNode) {
            return $this->truthy($this->lookupScoped($scopes, $node->path))
                ? $this->renderNodes($node->children, $scopes)
                : '';
        }

        if ($node instanceof UnlessNode) {
            return $this->truthy($this->lookupScoped($scopes, $node->path))
                ? ''
                : $this->renderNodes($node->children, $scopes);
        }

        if ($node instanceof EachNode) {
            $value = $this->lookupScoped($scopes, $node->path);
            if (! is_array($value) || ! array_is_list($value)) {
                return '';
            }

            $out = '';
            foreach ($value as $item) {
                $out .= $this->renderNodes($node->children, [$this->itemScope($item), ...$scopes]);
            }

            return $out;
        }

        return ''; // @codeCoverageIgnore
    }

    /**
     * @return array<string, mixed>
     */
    private function itemScope(mixed $item): array
    {
        if (is_array($item) && ! array_is_list($item)) {
            /** @var array<string, mixed> $item */
            $item['this'] = $item;

            return $item;
        }

        return ['this' => $item];
    }

    /**
     * @param  list<array<string, mixed>>  $scopes
     */
    private function lookupScoped(array $scopes, string $path): mixed
    {
        $parts = explode('.', $path);
        $miss = $this->miss();
        foreach ($scopes as $scope) {
            $found = $this->fetchPath($scope, $parts);
            if ($found !== $miss) {
                return $found;
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $parts
     */
    private function fetchPath(mixed $value, array $parts): mixed
    {
        foreach ($parts as $part) {
            if (! is_array($value) || ! array_key_exists($part, $value)) {
                return $this->miss();
            }

            $value = $value[$part];
        }

        return $value;
    }

    private function miss(): \stdClass
    {
        /** @var ?\stdClass $sentinel */
        static $sentinel = null;

        return $sentinel ??= new \stdClass;
    }

    private function truthy(mixed $v): bool
    {
        return ! in_array($v, [null, false, '', []], true);
    }

    private function stringify(mixed $v): string
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

    private function xmlEscape(string $s): string
    {
        return strtr($s, ['&' => '&amp;', '<' => '&lt;', '>' => '&gt;']);
    }
}
