<?php

declare(strict_types=1);

use DocxTemplate\Internal\Zip;
use DocxTemplate\Template;
use DocxTemplate\TemplateException;

// Smallest valid 1x1 transparent PNG.
const PNG_1x1 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

function part(string $docxBytes, string $name): string
{
    $entries = Zip::unpack($docxBytes);
    expect($entries)->toHaveKey($name);

    return $entries[$name];
}

function paragraphCount(string $xml): int
{
    return preg_match_all('/<w:p[\s>]/', $xml);
}

function rowCount(string $xml): int
{
    return preg_match_all('/<w:tr[\s>]/', $xml);
}

describe('Template::render', function (): void {
    it('substitutes a variable in a real .docx', function (): void {
        $bytes = Template::load(fixturePath('hello.docx'))->render(['name' => 'Ostap']);
        $doc = documentXml($bytes);
        expect($doc)->toContain('Ostap')->not->toContain('{{name}}');
    });

    it('renders missing assigns as empty', function (): void {
        $bytes = Template::load(fixturePath('hello.docx'))->render([]);
        expect(documentXml($bytes))->not->toContain('{{name}}');
    });

    it('heals a placeholder Word split across runs', function (): void {
        $bytes = Template::load(fixturePath('hello_split.docx'))->render(['name' => 'Ostap']);
        $doc = documentXml($bytes);
        expect($doc)->toContain('Ostap')->not->toContain('{{');
    });

    it('errors on non-zip input', function (): void {
        Template::fromString("\x00\x01\x02\x03")->render([]);
    })->throws(TemplateException::class);

    it('repeats a paragraph for each {{#each}} item', function (): void {
        $bytes = Template::load(fixturePath('each.docx'))->render([
            'guests' => [['name' => 'Ada'], ['name' => 'Grace'], ['name' => 'Linus']],
        ]);
        $doc = documentXml($bytes);
        expect($doc)->toContain('Ada')->toContain('Grace')->toContain('Linus')
            ->not->toContain('{{')->not->toContain('#each');
        expect(paragraphCount($doc))->toBe(4);
    });

    it('{{#each}} over empty list emits no body paragraphs', function (): void {
        $bytes = Template::load(fixturePath('each.docx'))->render(['guests' => []]);
        $doc = documentXml($bytes);
        expect($doc)->not->toContain('{{')->not->toContain('- ');
    });

    it('repeats a <w:tr> for each item when {{#each}} wraps a row', function (): void {
        $bytes = Template::load(fixturePath('each_table.docx'))->render([
            'guests' => [['name' => 'Ada'], ['name' => 'Grace'], ['name' => 'Linus']],
        ]);
        $doc = documentXml($bytes);
        expect($doc)->toContain('Ada')->toContain('Grace')->toContain('Linus')
            ->not->toContain('{{')->not->toContain('#each');
        expect(rowCount($doc))->toBe(3);
    });

    it('table {{#each}} over empty list emits no body rows', function (): void {
        $bytes = Template::load(fixturePath('each_table.docx'))->render(['guests' => []]);
        $doc = documentXml($bytes);
        expect($doc)->not->toContain('{{');
        expect(rowCount($doc))->toBe(0);
    });

    it('{{#if}} keeps body when truthy and drops when falsy', function (): void {
        $on = documentXml(Template::load(fixturePath('if.docx'))->render(['vip' => true]));
        expect($on)->toContain('Welcome, VIP!')->not->toContain('{{');

        $off = documentXml(Template::load(fixturePath('if.docx'))->render(['vip' => false]));
        expect($off)->not->toContain('Welcome, VIP!')->not->toContain('{{');
        expect(paragraphCount($off))->toBe(0);
    });

    it('substitutes variables inside footnotes and endnotes', function (): void {
        $bytes = Template::load(fixturePath('notes.docx'))->render(['name' => 'Ostap']);
        expect(part($bytes, 'word/footnotes.xml'))->toContain('Footnote for Ostap.');
        expect(part($bytes, 'word/endnotes.xml'))->toContain('Endnote for Ostap.');
    });

    it('{{image var}} inserts a drawing, media, rel, and content type', function (): void {
        $png = base64_decode(PNG_1x1, true);
        $bytes = Template::load(fixturePath('image.docx'))->render([
            'logo' => ['bytes' => $png, 'format' => 'png', 'width_cm' => 2, 'height_cm' => 2],
        ]);

        $doc = documentXml($bytes);
        expect($doc)->not->toContain('{{image')
            ->toContain('<w:drawing>')
            ->toContain('r:embed="rIdDocxTmpl1"');

        $entries = Zip::unpack($bytes);
        expect($entries)->toHaveKey('word/media/image1.png');
        expect($entries['word/media/image1.png'])->toBe($png);

        $rels = part($bytes, 'word/_rels/document.xml.rels');
        expect($rels)->toContain('Id="rIdDocxTmpl1"')->toContain('media/image1.png');

        expect(part($bytes, '[Content_Types].xml'))->toContain('Extension="png"');
    });

    it('{{image var}} with missing assigns drops the paragraph', function (): void {
        $bytes = Template::load(fixturePath('image.docx'))->render([]);
        $doc = documentXml($bytes);
        expect($doc)->not->toContain('{{image')->not->toContain('<w:drawing>');
    });

    it('output is a valid .docx that round-trips through unzip', function (): void {
        $bytes = Template::load(fixturePath('hello.docx'))->render(['name' => 'Ostap']);
        $entries = Zip::unpack($bytes);
        expect($entries)->toHaveKey('word/document.xml');
    });
});

describe('Template::variables', function (): void {
    it('lists the variable referenced in a simple template', function (): void {
        expect(Template::load(fixturePath('hello.docx'))->variables())->toBe(['name']);
    });

    it('includes names from {{#if}} and {{#each}} blocks and their bodies', function (): void {
        $vars = Template::load(fixturePath('each.docx'))->variables();
        expect($vars)->toBe(['guests', 'name']);
    });

    it('deduplicates references', function (): void {
        $vars = Template::load(fixturePath('if.docx'))->variables();
        expect($vars)->toContain('vip');
        expect(count($vars))->toBe(count(array_unique($vars)));
    });

    it('errors on non-zip input', function (): void {
        Template::fromString("\x00\x01\x02\x03")->variables();
    })->throws(TemplateException::class);
});
