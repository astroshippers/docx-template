<?php

declare(strict_types=1);

use DocxTemplate\Template;

it('substitutes a variable in a real .docx', function (): void {
    $bytes = Template::load(fixturePath('hello.docx'))->render(['name' => 'Ostap']);

    $doc = documentXml($bytes);

    expect($doc)->toContain('Ostap')
        ->not->toContain('{{name}}');
});
