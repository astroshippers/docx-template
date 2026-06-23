<?php

declare(strict_types=1);

namespace DocxTemplate\Internal;

use DocxTemplate\TemplateException;

/**
 * One `{{...}}` tag's source context: the raw substring, its byte offset
 * in the template, and the whitespace-normalized inner text. Carrying
 * these together keeps Parser methods to one parameter and lets `fail`
 * centralize error formatting.
 */
final readonly class TagSource
{
    public function __construct(
        public string $raw,
        public int $offset,
        public string $inner,
    ) {}

    /**
     * The placeholders `%s` and `%d` may appear anywhere in the message;
     * use them with $this->raw / $this->offset as you would with sprintf.
     */
    public function fail(string $message, string|int ...$args): never
    {
        throw new TemplateException(sprintf($message, ...$args));
    }
}
