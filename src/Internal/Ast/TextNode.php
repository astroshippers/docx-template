<?php

declare(strict_types=1);

namespace DocxTemplate\Internal\Ast;

final readonly class TextNode implements Node
{
    public function __construct(public string $text) {}
}
