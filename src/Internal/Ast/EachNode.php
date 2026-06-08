<?php

declare(strict_types=1);

namespace DocxTemplate\Internal\Ast;

final readonly class EachNode implements Node
{
    /**
     * @param  list<Node>  $children
     */
    public function __construct(public string $path, public array $children) {}
}
