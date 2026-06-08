<?php

declare(strict_types=1);

namespace DocxTemplate\Internal\Token;

final readonly class OpenToken implements Token
{
    public function __construct(public BlockKind $kind, public string $path) {}
}
