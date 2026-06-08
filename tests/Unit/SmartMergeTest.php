<?php

declare(strict_types=1);

use DocxTemplate\Internal\SmartMerge;

it('leaves intact placeholders untouched', function (): void {
    $xml = '<w:r><w:t>Hello {{name}}!</w:t></w:r>';
    expect((new SmartMerge)->heal($xml))->toBe($xml);
});

it('merges a placeholder split across two runs', function (): void {
    $xml = '<w:r><w:t>{{</w:t></w:r><w:r><w:rPr/><w:t xml:space="preserve">name}}</w:t></w:r>';
    $out = (new SmartMerge)->heal($xml);
    expect($out)->toContain('{{name}}')->not->toContain('{{</w:t>');
});

it('merges a placeholder split across three runs', function (): void {
    $xml = '<w:r><w:t>{{</w:t></w:r><w:r><w:t>na</w:t></w:r><w:r><w:t>me}}</w:t></w:r>';
    expect((new SmartMerge)->heal($xml))->toContain('{{name}}');
});

it('does not merge across a paragraph boundary', function (): void {
    $xml = '<w:r><w:t>{{</w:t></w:r></w:p><w:p><w:r><w:t>name}}</w:t></w:r>';
    expect((new SmartMerge)->heal($xml))->toBe($xml);
});

it('handles multiple split placeholders in one pass', function (): void {
    $xml = '<w:r><w:t>{{</w:t></w:r><w:r><w:t>a}}</w:t></w:r> and <w:r><w:t>{{</w:t></w:r><w:r><w:t>b}}</w:t></w:r>';
    $out = (new SmartMerge)->heal($xml);
    expect($out)->toContain('{{a}}')->toContain('{{b}}');
});

it('leaves an unclosed placeholder as-is', function (): void {
    $xml = '<w:r><w:t>hello {{name';
    expect((new SmartMerge)->heal($xml))->toBe($xml);
});

it('leaves a split placeholder with no following text run as-is', function (): void {
    $xml = '<w:r><w:t>{{</w:t></w:r><w:r><w:rPr/></w:r>';
    expect((new SmartMerge)->heal($xml))->toBe($xml);
});

it('does not merge across an empty self-closing text run', function (): void {
    $xml = '<w:r><w:t>{{</w:t></w:r><w:r><w:t/></w:r><w:r><w:t>name}}</w:t></w:r>';
    expect((new SmartMerge)->heal($xml))->toBe($xml);
});
