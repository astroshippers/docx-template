<?php

declare(strict_types=1);

use DocxTemplate\Internal\Zip;
use DocxTemplate\TemplateException;

it('round-trips pack and unpack', function (): void {
    $entries = ['a.txt' => 'hello', 'b/c.txt' => 'world'];
    $bin = Zip::pack($entries);
    expect(Zip::unpack($bin))->toBe($entries);
});

it('throws on non-zip input', function (): void {
    Zip::unpack("\x00\x01\x02\x03");
})->throws(TemplateException::class);

it('identifies template parts', function (): void {
    expect(Zip::isTemplatePart('word/document.xml'))->toBeTrue()
        ->and(Zip::isTemplatePart('word/header1.xml'))->toBeTrue()
        ->and(Zip::isTemplatePart('word/footer2.xml'))->toBeTrue()
        ->and(Zip::isTemplatePart('word/footnotes.xml'))->toBeTrue()
        ->and(Zip::isTemplatePart('word/endnotes.xml'))->toBeTrue()
        ->and(Zip::isTemplatePart('word/styles.xml'))->toBeFalse()
        ->and(Zip::isTemplatePart('[Content_Types].xml'))->toBeFalse();
});
