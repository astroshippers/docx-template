<?php

declare(strict_types=1);

namespace DocxTemplate\Internal;

final readonly class Render
{
    private const string IMAGE_PARAGRAPH = '/<w:p\b[^>]*>(?:(?!<\/w:p>).)*?\{\{\s*image\s+([a-zA-Z_][a-zA-Z0-9_.]*)\s*\}\}(?:(?!<\/w:p>).)*?<\/w:p>/s';

    private const string RID_PREFIX = 'rIdDocxTmpl';

    public function __construct(
        private Zip $zip,
        private Parser $parser,
        private Structural $structural,
        private SmartMerge $smartMerge,
        private Interpreter $interpreter,
        private Image $image,
    ) {}

    /**
     * @param  array<string, mixed>  $assigns
     */
    #[\NoDiscard]
    public function run(string $template, array $assigns): string
    {
        $entries = $this->zip->unpack($template);

        /** @var list<EmbeddedImage> $images */
        $images = [];
        foreach ($entries as $name => $bin) {
            $entries[$name] = $this->renderEntry($name, $bin, $assigns, $images);
        }

        return $this->zip->pack($this->injectImages($entries, $images));
    }

    /**
     * @param  array<string, mixed>  $assigns
     * @param  list<EmbeddedImage>  $images
     */
    private function renderEntry(string $name, string $bin, array $assigns, array &$images): string
    {
        if ($name === 'word/document.xml') {
            $healed = $bin
                |> $this->smartMerge->heal(...)
                |> $this->structural->fixup(...);
            $withDrawings = $this->extractImages($healed, $assigns, $images);

            return $withDrawings
                |> $this->parser->parse(...)
                |> (fn (array $ast): string => $this->interpreter->render($ast, $assigns));
        }

        if ($this->zip->isTemplatePart($name)) {
            $healed = $bin
                |> $this->smartMerge->heal(...)
                |> $this->structural->fixup(...);

            return $healed
                |> $this->parser->parse(...)
                |> (fn (array $ast): string => $this->interpreter->render($ast, $assigns));
        }

        return $bin;
    }

    /**
     * @param  array<string, mixed>  $assigns
     * @param  list<EmbeddedImage>  $images
     */
    private function extractImages(string $xml, array $assigns, array &$images): string
    {
        // @codeCoverageIgnoreStart
        if (preg_match_all(self::IMAGE_PARAGRAPH, $xml, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER) === false) {
            return $xml;
        }

        // @codeCoverageIgnoreEnd

        if ($matches === []) {
            return $xml;
        }

        $base = count($images);
        $newImages = [];
        $replacements = [];
        foreach ($matches as $i => $m) {
            $fullText = $m[0][0];
            $fullOffset = $m[0][1];
            $var = $m[1][0];

            $image = $this->tryBuildImage($assigns[$var] ?? null, $base + $i + 1);
            if ($image instanceof EmbeddedImage) {
                $replacement = $this->image->paragraphXml($image->rid, $image->cx, $image->cy, $image->n);
                $newImages[] = $image;
            } else {
                $replacement = '';
            }

            $replacements[] = [$fullOffset, strlen($fullText), $replacement];
        }

        $out = $xml;
        foreach (array_reverse($replacements) as [$offset, $len, $repl]) {
            $out = substr($out, 0, $offset).$repl.substr($out, $offset + $len);
        }

        foreach ($newImages as $img) {
            $images[] = $img;
        }

        return $out;
    }

    private function tryBuildImage(mixed $value, int $n): ?EmbeddedImage
    {
        if (! is_array($value)) {
            return null;
        }

        $bytes = $value['bytes'] ?? null;
        $format = $value['format'] ?? null;
        $width = $value['width_cm'] ?? null;
        $height = $value['height_cm'] ?? null;

        if (! is_string($bytes) || ! is_string($format)) {
            return null;
        }

        if (! is_int($width) && ! is_float($width)) {
            return null;
        }

        if (! is_int($height) && ! is_float($height)) {
            return null;
        }

        $fmt = ImageFormat::tryFrom($format);
        if ($fmt === null) {
            return null;
        }

        return new EmbeddedImage(
            rid: self::RID_PREFIX.$n,
            bytes: $bytes,
            format: $fmt,
            n: $n,
            cx: $this->image->cmToEmu($width),
            cy: $this->image->cmToEmu($height),
        );
    }

    /**
     * @param  array<string, string>  $entries
     * @param  list<EmbeddedImage>  $images
     * @return array<string, string>
     */
    private function injectImages(array $entries, array $images): array
    {
        if ($images === []) {
            return $entries;
        }

        foreach ($images as $img) {
            $entries['word/media/image'.$img->n.'.'.$img->format->extension()] = $img->bytes;
        }

        $entries = $this->updateRels($entries, $images);

        return $this->updateContentTypes($entries, $images);
    }

    /**
     * @param  array<string, string>  $entries
     * @param  list<EmbeddedImage>  $images
     * @return array<string, string>
     */
    private function updateRels(array $entries, array $images): array
    {
        $name = 'word/_rels/document.xml.rels';

        $newRels = '';
        foreach ($images as $img) {
            $target = 'media/image'.$img->n.'.'.$img->format->extension();
            $newRels .= '<Relationship Id="'.$img->rid.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="'.$target.'"/>';
        }

        if (isset($entries[$name])) {
            $entries[$name] = str_replace('</Relationships>', $newRels.'</Relationships>', $entries[$name]);
        } else {
            $entries[$name] = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                .$newRels
                .'</Relationships>';
        }

        return $entries;
    }

    /**
     * @param  array<string, string>  $entries
     * @param  list<EmbeddedImage>  $images
     * @return array<string, string>
     */
    private function updateContentTypes(array $entries, array $images): array
    {
        $name = '[Content_Types].xml';
        if (! isset($entries[$name])) {
            return $entries;
        }

        $xml = $entries[$name];
        $seen = [];
        foreach ($images as $img) {
            $ext = $img->format->extension();
            if (isset($seen[$ext]) || str_contains($xml, 'Extension="'.$ext.'"')) {
                $seen[$ext] = true;

                continue;
            }

            $seen[$ext] = true;
            $entry = '<Default Extension="'.$ext.'" ContentType="'.$img->format->contentType().'"/>';
            $replaced = preg_replace('/(<Types\b[^>]*>)/', '$1'.$entry, $xml, 1);
            if ($replaced !== null) {
                $xml = $replaced;
            }
        }

        $entries[$name] = $xml;

        return $entries;
    }
}
