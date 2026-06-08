<?php

declare(strict_types=1);

use DocxTemplate\Internal\Ast\EachNode;
use DocxTemplate\Internal\Ast\IfNode;
use DocxTemplate\Internal\Ast\TextNode;
use DocxTemplate\Internal\Ast\UnlessNode;
use DocxTemplate\Internal\Ast\VarNode;
use DocxTemplate\Internal\Interpreter;
use DocxTemplate\Internal\Parser;
use DocxTemplate\TemplateException;

function parser(): Parser
{
    return new Parser;
}

function render(string $template, array $assigns): string
{
    return (new Interpreter)->render(parser()->parse($template), $assigns);
}

describe('Parser::parse', function (): void {
    it('returns a single text node for plain text', function (): void {
        expect(parser()->parse('hello'))->toEqual([new TextNode('hello')]);
    });

    it('parses a variable', function (): void {
        expect(parser()->parse('Hi {{name}}!'))->toEqual([
            new TextNode('Hi '),
            new VarNode('name'),
            new TextNode('!'),
        ]);
    });

    it('parses if/end blocks', function (): void {
        expect(parser()->parse('a{{#if flag}}b{{/if}}c'))->toEqual([
            new TextNode('a'),
            new IfNode('flag', [new TextNode('b')]),
            new TextNode('c'),
        ]);
    });

    it('parses unless blocks', function (): void {
        expect(parser()->parse('{{#unless flag}}x{{/unless}}'))->toEqual([
            new UnlessNode('flag', [new TextNode('x')]),
        ]);
    });

    it('parses each blocks', function (): void {
        expect(parser()->parse('{{#each items}}-{{name}}{{/each}}'))->toEqual([
            new EachNode('items', [new TextNode('-'), new VarNode('name')]),
        ]);
    });

    it('parses nested blocks', function (): void {
        expect(parser()->parse('{{#each xs}}{{#if on}}{{name}}{{/if}}{{/each}}'))->toEqual([
            new EachNode('xs', [
                new IfNode('on', [new VarNode('name')]),
            ]),
        ]);
    });

    it('raises on unbalanced tags', function (): void {
        parser()->parse('{{#if a}}nope');
    })->throws(TemplateException::class, 'unbalanced');

    it('raises on mismatched close', function (): void {
        parser()->parse('{{#if a}}x{{/each}}');
    })->throws(TemplateException::class, 'mismatched');

    it('tolerates whitespace around #, /, and inside block tags', function (): void {
        expect(render('{{ #if  flag }}y{{ /if }}', ['flag' => true]))->toBe('y');
        expect(render('{{# if flag}}y{{/ if}}', ['flag' => true]))->toBe('y');
        expect(render('{{  #each  xs  }}-{{ this }}{{  /each  }}', ['xs' => ['a', 'b']]))->toBe('-a-b');
    });

    it('tolerates newlines inside tag braces', function (): void {
        expect(render("Hi {{\n  name\n}}!", ['name' => 'Ostap']))->toBe('Hi Ostap!');
        expect(render("{{#if\n flag\n}}y{{/if}}", ['flag' => true]))->toBe('y');
    });

    it('leaves empty braces as literal text', function (): void {
        expect(render('a{{}}b', []))->toBe('a{{}}b');
        expect(render('a{{   }}b', []))->toBe('a{{   }}b');
    });

    it('leaves single curly braces as literal text', function (): void {
        expect(render('{name} = {{name}}', ['name' => 'Ostap']))->toBe('{name} = Ostap');
    });

    it('treats extra outer curly braces as literal', function (): void {
        expect(render('{{{name}}}', ['name' => 'Ostap']))->toBe('{Ostap}');
    });

    it('handles adjacent tags with no separator', function (): void {
        expect(render('{{a}}{{b}}', ['a' => '1', 'b' => '2']))->toBe('12');
    });

    it('renders empty block bodies without error', function (): void {
        expect(render('a{{#if flag}}{{/if}}b', ['flag' => true]))->toBe('ab');
        expect(render('a{{#each xs}}{{/each}}b', ['xs' => [1, 2, 3]]))->toBe('ab');
    });
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

    it('treats numeric zero as truthy in if', function (): void {
        expect(render('{{#if n}}y{{/if}}', ['n' => 0]))->toBe('y');
        expect(render('{{#if n}}y{{/if}}', ['n' => '0']))->toBe('y');
        expect(render('{{#if n}}y{{/if}}', ['n' => 0.0]))->toBe('y');
    });

    it('renders booleans as the literal strings true/false', function (): void {
        expect(render('[{{flag}}]', ['flag' => true]))->toBe('[true]');
        expect(render('[{{flag}}]', ['flag' => false]))->toBe('[false]');
    });

    it('renders integers and floats', function (): void {
        expect(render('{{n}}', ['n' => 42]))->toBe('42');
        expect(render('{{n}}', ['n' => 1.5]))->toBe('1.5');
    });

    it('renders missing values in dotted paths as empty', function (): void {
        expect(render('[{{a.b}}]', ['a' => 'hi']))->toBe('[]');
        expect(render('[{{a.b.c}}]', ['a' => ['b' => 'hi']]))->toBe('[]');
        expect(render('[{{a.b}}]', ['a' => null]))->toBe('[]');
    });

    it('renders nothing for each over assoc array or scalar', function (): void {
        expect(render('x{{#each xs}}y{{/each}}z', ['xs' => ['k' => 'v']]))->toBe('xz');
        expect(render('x{{#each xs}}y{{/each}}z', ['xs' => 'string']))->toBe('xz');
        expect(render('x{{#each xs}}y{{/each}}z', ['xs' => 42]))->toBe('xz');
    });

    it('exposes assoc-item fields through this dotted access', function (): void {
        expect(render('{{#each users}}[{{this.name}}]{{/each}}', [
            'users' => [['name' => 'A'], ['name' => 'B']],
        ]))->toBe('[A][B]');
    });

    it('renders complex (non-scalar) values as empty', function (): void {
        expect(render('[{{x}}]', ['x' => ['a', 'b']]))->toBe('[]');
        expect(render('[{{x}}]', ['x' => new stdClass]))->toBe('[]');
    });
});
