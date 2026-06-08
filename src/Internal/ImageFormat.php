<?php

declare(strict_types=1);

namespace DocxTemplate\Internal;

enum ImageFormat: string
{
    case Png = 'png';
    case Jpeg = 'jpeg';
    case Gif = 'gif';

    public function extension(): string
    {
        return match ($this) {
            self::Png => 'png',
            self::Jpeg => 'jpg',
            self::Gif => 'gif',
        };
    }

    public function contentType(): string
    {
        return match ($this) {
            self::Png => 'image/png',
            self::Jpeg => 'image/jpeg',
            self::Gif => 'image/gif',
        };
    }
}
