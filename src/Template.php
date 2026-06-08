<?php

declare(strict_types=1);

namespace DocxTemplate;

use DocxTemplate\Internal\Parser;
use DocxTemplate\Internal\Render;
use DocxTemplate\Internal\SmartMerge;
use DocxTemplate\Internal\Structural;
use DocxTemplate\Internal\Zip;

final class Template
{
    private function __construct(private readonly string $bytes) {}

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
        return Render::run($this->bytes, $assigns);
    }

    /**
     * @return list<string>
     */
    public function variables(): array
    {
        $entries = Zip::unpack($this->bytes);
        $names = [];

        foreach ($entries as $name => $bin) {
            if (! Zip::isTemplatePart($name)) {
                continue;
            }
            $ast = Parser::parse(Structural::fixup(SmartMerge::heal($bin)));
            self::collectNames($ast, $names);
        }

        $out = array_keys($names);
        sort($out);

        return $out;
    }

    /**
     * @param  list<array<int, mixed>>  $nodes
     * @param  array<string, true>  $acc
     */
    private static function collectNames(array $nodes, array &$acc): void
    {
        foreach ($nodes as $node) {
            $kind = $node[0];
            if ($kind === 'text') {
                continue;
            }
            $acc[$node[1]] = true;
            if ($kind !== 'var') {
                self::collectNames($node[2], $acc);
            }
        }
    }
}
