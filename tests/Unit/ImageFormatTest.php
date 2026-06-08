<?php

declare(strict_types=1);

use DocxTemplate\Internal\ImageFormat;

it('maps each format to its file extension', function (): void {
    expect(ImageFormat::Png->extension())->toBe('png');
    expect(ImageFormat::Jpeg->extension())->toBe('jpg');
    expect(ImageFormat::Gif->extension())->toBe('gif');
});

it('maps each format to its content type', function (): void {
    expect(ImageFormat::Png->contentType())->toBe('image/png');
    expect(ImageFormat::Jpeg->contentType())->toBe('image/jpeg');
    expect(ImageFormat::Gif->contentType())->toBe('image/gif');
});
