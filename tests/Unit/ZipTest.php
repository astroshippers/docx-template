<?php

declare(strict_types=1);

use DocxTemplate\Internal\Zip;
use DocxTemplate\TemplateException;

it('round-trips pack and unpack', function (): void {
    $zip = new Zip;
    $entries = ['a.txt' => 'hello', 'b/c.txt' => 'world'];
    $bin = $zip->pack($entries);
    expect($zip->unpack($bin))->toBe($entries);
});

it('throws on non-zip input', function (): void {
    (new Zip)->unpack("\x00\x01\x02\x03");
})->throws(TemplateException::class);

it('identifies template parts', function (): void {
    $zip = new Zip;
    expect($zip->isTemplatePart('word/document.xml'))->toBeTrue()
        ->and($zip->isTemplatePart('word/header1.xml'))->toBeTrue()
        ->and($zip->isTemplatePart('word/footer2.xml'))->toBeTrue()
        ->and($zip->isTemplatePart('word/footnotes.xml'))->toBeTrue()
        ->and($zip->isTemplatePart('word/endnotes.xml'))->toBeTrue()
        ->and($zip->isTemplatePart('word/styles.xml'))->toBeFalse()
        ->and($zip->isTemplatePart('[Content_Types].xml'))->toBeFalse();
});
