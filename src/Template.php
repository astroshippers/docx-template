<?php

declare(strict_types=1);

namespace DocxTemplate;

use DocxTemplate\Internal\Ast\EachNode;
use DocxTemplate\Internal\Ast\IfNode;
use DocxTemplate\Internal\Ast\Node;
use DocxTemplate\Internal\Ast\UnlessNode;
use DocxTemplate\Internal\Ast\VarNode;
use DocxTemplate\Internal\Image;
use DocxTemplate\Internal\Interpreter;
use DocxTemplate\Internal\Parser;
use DocxTemplate\Internal\Render;
use DocxTemplate\Internal\SmartMerge;
use DocxTemplate\Internal\Structural;
use DocxTemplate\Internal\Zip;

final readonly class Template
{
    private function __construct(
        private string $bytes,
        private Zip $zip,
        private Parser $parser,
        private Structural $structural,
        private SmartMerge $smartMerge,
        private Render $render,
    ) {}

    public static function load(string $path): self
    {
        $bytes = @file_get_contents($path);
        if ($bytes === false) {
            throw new TemplateException(sprintf('Could not read template at %s.', $path));
        }

        return self::create($bytes);
    }

    public static function fromString(string $bytes): self
    {
        return self::create($bytes);
    }

    /**
     * @param  array<string, mixed>  $assigns
     */
    public function render(array $assigns = []): string
    {
        return $this->render->run($this->bytes, $assigns);
    }

    /**
     * @return list<string>
     */
    public function variables(): array
    {
        $entries = $this->zip->unpack($this->bytes);
        $names = [];

        foreach ($entries as $name => $bin) {
            if (! $this->zip->isTemplatePart($name)) {
                continue;
            }

            $ast = $this->parser->parse($this->structural->fixup($this->smartMerge->heal($bin)));
            $this->collectNames($ast, $names);
        }

        $out = array_keys($names);
        sort($out);

        return $out;
    }

    private static function create(string $bytes): self
    {
        $zip = new Zip;
        $parser = new Parser;
        $structural = new Structural;
        $smartMerge = new SmartMerge;
        $interpreter = new Interpreter;
        $image = new Image;
        $render = new Render($zip, $parser, $structural, $smartMerge, $interpreter, $image);

        return new self($bytes, $zip, $parser, $structural, $smartMerge, $render);
    }

    /**
     * @param  list<Node>  $nodes
     * @param  array<string, true>  $acc
     */
    private function collectNames(array $nodes, array &$acc): void
    {
        foreach ($nodes as $node) {
            if ($node instanceof VarNode) {
                $acc[$node->path] = true;

                continue;
            }

            if ($node instanceof IfNode || $node instanceof UnlessNode || $node instanceof EachNode) {
                $acc[$node->path] = true;
                $this->collectNames($node->children, $acc);
            }
        }
    }
}
