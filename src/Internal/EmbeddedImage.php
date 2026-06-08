<?php

declare(strict_types=1);

namespace DocxTemplate\Internal;

final readonly class EmbeddedImage
{
    public function __construct(
        public string $rid,
        public string $bytes,
        public ImageFormat $format,
        public int $n,
        public int $cx,
        public int $cy,
    ) {}
}
