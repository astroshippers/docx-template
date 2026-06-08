<?php

declare(strict_types=1);

namespace DocxTemplate;

final class Template
{
    /**
     * @param  array<string, mixed>  $assigns
     */
    private function __construct(
        private readonly string $bytes,
        private readonly array $assigns = [],
    ) {}

    public static function load(string $path): self
    {
        $bytes = @file_get_contents($path);

        if ($bytes === false) {
            throw new TemplateException("Could not read template at {$path}.");
        }

        return new self($bytes);
    }

    public static function fromString(string $bytes): self
    {
        return new self($bytes);
    }

    /**
     * @param  array<string, mixed>  $assigns
     */
    public function render(array $assigns = []): string
    {
        throw new TemplateException('Not implemented yet.');
    }

    /**
     * @return list<string>
     */
    public function variables(): array
    {
        throw new TemplateException('Not implemented yet.');
    }
}
