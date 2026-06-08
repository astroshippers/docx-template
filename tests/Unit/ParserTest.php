<?php

declare(strict_types=1);

use DocxTemplate\Internal\Interpreter;
use DocxTemplate\Internal\Parser;
use DocxTemplate\TemplateException;

function render(string $template, array $assigns): string
{
    return Interpreter::render(Parser::parse($template), $assigns);
}

describe('Parser::parse', function (): void {
    it('returns a single text node for plain text', function (): void {
        expect(Parser::parse('hello'))->toBe([['text', 'hello']]);
    });

    it('parses a variable', function (): void {
        expect(Parser::parse('Hi {{name}}!'))->toBe([
            ['text', 'Hi '],
            ['var', 'name'],
            ['text', '!'],
        ]);
    });

    it('parses if/end blocks', function (): void {
        expect(Parser::parse('a{{#if flag}}b{{/if}}c'))->toBe([
            ['text', 'a'],
            ['if', 'flag', [['text', 'b']]],
            ['text', 'c'],
        ]);
    });

    it('parses unless blocks', function (): void {
        expect(Parser::parse('{{#unless flag}}x{{/unless}}'))->toBe([
            ['unless', 'flag', [['text', 'x']]],
        ]);
    });

    it('parses each blocks', function (): void {
        expect(Parser::parse('{{#each items}}-{{name}}{{/each}}'))->toBe([
            ['each', 'items', [['text', '-'], ['var', 'name']]],
        ]);
    });

    it('parses nested blocks', function (): void {
        expect(Parser::parse('{{#each xs}}{{#if on}}{{name}}{{/if}}{{/each}}'))->toBe([
            ['each', 'xs', [
                ['if', 'on', [['var', 'name']]],
            ]],
        ]);
    });

    it('raises on unbalanced tags', function (): void {
        Parser::parse('{{#if a}}nope');
    })->throws(TemplateException::class, 'unbalanced');

    it('raises on mismatched close', function (): void {
        Parser::parse('{{#if a}}x{{/each}}');
    })->throws(TemplateException::class, 'mismatched');
});

describe('Interpreter::render', function (): void {
    it('renders variables', function (): void {
        expect(render('Hi {{name}}', ['name' => 'Ostap']))->toBe('Hi Ostap');
    });

    it('renders dotted paths', function (): void {
        expect(render('Hi {{user.name}}', ['user' => ['name' => 'Ostap']]))->toBe('Hi Ostap');
    });

    it('renders missing keys as empty', function (): void {
        expect(render('[{{missing}}]', []))->toBe('[]');
    });

    it('tolerates whitespace inside braces', function (): void {
        expect(render('{{  name  }}', ['name' => 'Ostap']))->toBe('Ostap');
    });

    it('renders if-truthy and skips if-falsy', function (): void {
        expect(render('[{{#if flag}}yes{{/if}}]', ['flag' => true]))->toBe('[yes]');
        expect(render('[{{#if flag}}yes{{/if}}]', ['flag' => false]))->toBe('[]');
        expect(render('[{{#if flag}}yes{{/if}}]', []))->toBe('[]');
    });

    it('treats empty list/string as falsy', function (): void {
        expect(render('{{#if xs}}y{{/if}}', ['xs' => []]))->toBe('');
        expect(render('{{#if xs}}y{{/if}}', ['xs' => '']))->toBe('');
    });

    it('inverts unless', function (): void {
        expect(render('{{#unless flag}}n{{/unless}}', ['flag' => false]))->toBe('n');
        expect(render('{{#unless flag}}n{{/unless}}', ['flag' => true]))->toBe('');
    });

    it('iterates each with scoped lookup', function (): void {
        expect(render('{{#each users}}<{{name}}>{{/each}}', [
            'users' => [['name' => 'A'], ['name' => 'B']],
        ]))->toBe('<A><B>');
    });

    it('exposes scalar items as this', function (): void {
        expect(render('{{#each xs}}[{{this}}]{{/each}}', ['xs' => ['a', 'b']]))->toBe('[a][b]');
    });

    it('each falls back to outer scope', function (): void {
        expect(render('{{#each xs}}{{title}}-{{name}}|{{/each}}', [
            'title' => 'Mr',
            'xs' => [['name' => 'A'], ['name' => 'B']],
        ]))->toBe('Mr-A|Mr-B|');
    });

    it('each over missing/empty/non-list emits nothing', function (): void {
        expect(render('x{{#each xs}}y{{/each}}z', []))->toBe('xz');
        expect(render('x{{#each xs}}y{{/each}}z', ['xs' => []]))->toBe('xz');
        expect(render('x{{#each xs}}y{{/each}}z', ['xs' => null]))->toBe('xz');
    });

    it('xml-escapes variable output', function (): void {
        expect(render('{{x}}', ['x' => 'a & b < c']))->toBe('a &amp; b &lt; c');
    });
});
