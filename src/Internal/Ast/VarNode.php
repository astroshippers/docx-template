<?php

declare(strict_types=1);

namespace DocxTemplate\Internal\Ast;

final readonly class VarNode implements Node
{
    public function __construct(public string $path) {}
}
