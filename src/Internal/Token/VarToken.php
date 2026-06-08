<?php

declare(strict_types=1);

namespace DocxTemplate\Internal\Token;

final readonly class VarToken implements Token
{
    public function __construct(public string $path) {}
}
