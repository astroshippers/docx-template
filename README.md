# DocxTemplate

Make new `.docx` files from a Handlebars-like template.

Write a Word document with `{{placeholders}}`, `{{#if}}`, `{{#unless}}`, and `{{#each}}` blocks — then render it with a map of assigns to produce a fresh `.docx`. Word frequently splits a single placeholder across multiple `<w:r>` runs (fonts, spellcheck markers, tracked changes); DocxTemplate heals those before substitution so your templates just work.

> **Requires [PHP 8.5+](https://php.net/releases/)**

## Installation

```bash
composer require osbre/docx-template
```

## Usage

```php
use DocxTemplate\Template;

$bytes = Template::load('invoice.docx')->render([
    'customer' => 'Acme Corp',
    'items' => [
        ['name' => 'Widget', 'qty' => 3],
        ['name' => 'Gadget', 'qty' => 1],
    ],
    'paid' => true,
]);

file_put_contents('invoice-acme.docx', $bytes);
```

Template syntax inside the `.docx`:

```
Hello {{customer}}!

{{#each items}}
  - {{name}} × {{qty}}
{{/each}}

{{#if paid}}Thanks for your payment.{{/if}}
{{#unless paid}}Please remit within 30 days.{{/unless}}
```

`{{#each}}` wrapping a single `<w:tr>` repeats the table row.

`{{image var}}` standalone in a paragraph embeds an image. Pass it as `['bytes' => binary, 'format' => 'png', 'width_cm' => 5, 'height_cm' => 3]` in assigns.

Inspect a template's variables without rendering:

```php
$vars = Template::load('invoice.docx')->variables();
// => ['customer', 'items', 'name', 'paid', 'qty']
```

## Development

```bash
composer test      # Run the full test suite
composer lint      # Run Pint linter
composer refactor  # Run Rector dry-run
```

## License

MIT
