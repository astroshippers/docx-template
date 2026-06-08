<?php

declare(strict_types=1);

namespace DocxTemplate\Internal\Token;

final readonly class TextToken implements Token
{
    public function __construct(public string $text) {}
}
