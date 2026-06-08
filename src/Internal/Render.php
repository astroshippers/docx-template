<?php

declare(strict_types=1);

namespace DocxTemplate\Internal;

final class Render
{
    private const IMAGE_PARAGRAPH = '/<w:p\b[^>]*>(?:(?!<\/w:p>).)*?\{\{\s*image\s+([a-zA-Z_][a-zA-Z0-9_.]*)\s*\}\}(?:(?!<\/w:p>).)*?<\/w:p>/s';

    private const RID_PREFIX = 'rIdDocxTmpl';

    /**
     * @param  array<string, mixed>  $assigns
     */
    public static function run(string $template, array $assigns): string
    {
        $entries = Zip::unpack($template);

        $images = [];
        foreach ($entries as $name => $bin) {
            $entries[$name] = self::renderEntry($name, $bin, $assigns, $images);
        }

        return Zip::pack(self::injectImages($entries, $images));
    }

    /**
     * @param  array<string, mixed>  $assigns
     * @param  list<array{rid: string, bytes: string, format: string, n: int}>  $images
     */
    private static function renderEntry(string $name, string $bin, array $assigns, array &$images): string
    {
        if ($name === 'word/document.xml') {
            $healed = Structural::fixup(SmartMerge::heal($bin));
            $withDrawings = self::extractImages($healed, $assigns, $images);

            return Interpreter::render(Parser::parse($withDrawings), $assigns);
        }

        if (Zip::isTemplatePart($name)) {
            $healed = Structural::fixup(SmartMerge::heal($bin));

            return Interpreter::render(Parser::parse($healed), $assigns);
        }

        return $bin;
    }

    /**
     * @param  array<string, mixed>  $assigns
     * @param  list<array{rid: string, bytes: string, format: string, n: int}>  $images
     */
    private static function extractImages(string $xml, array $assigns, array &$images): string
    {
        if (preg_match_all(self::IMAGE_PARAGRAPH, $xml, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER) === false) {
            return $xml;
        }
        if ($matches === []) {
            return $xml;
        }

        // Walk matches from right to left so offsets stay valid as we splice.
        $base = count($images);
        $newImages = [];
        $replacements = [];
        foreach ($matches as $i => $m) {
            $fullText = $m[0][0];
            $fullOffset = $m[0][1];
            $var = $m[1][0];

            $value = $assigns[$var] ?? null;
            if (self::isValidImageAssign($value)) {
                $n = $base + $i + 1;
                $rid = self::RID_PREFIX.$n;
                $cx = Image::cmToEmu($value['width_cm']);
                $cy = Image::cmToEmu($value['height_cm']);
                $replacement = Image::paragraphXml($rid, $cx, $cy, $n);
                $newImages[] = ['rid' => $rid, 'bytes' => $value['bytes'], 'format' => $value['format'], 'n' => $n];
            } else {
                $replacement = '';
            }

            $replacements[] = [$fullOffset, strlen($fullText), $replacement];
        }

        // Apply right-to-left.
        $out = $xml;
        foreach (array_reverse($replacements) as [$offset, $len, $repl]) {
            $out = substr($out, 0, $offset).$repl.substr($out, $offset + $len);
        }

        foreach ($newImages as $img) {
            $images[] = $img;
        }

        return $out;
    }

    private static function isValidImageAssign(mixed $v): bool
    {
        return is_array($v)
            && isset($v['bytes'], $v['format'], $v['width_cm'], $v['height_cm'])
            && is_string($v['bytes'])
            && in_array($v['format'], ['png', 'jpeg', 'gif'], true);
    }

    /**
     * @param  array<string, string>  $entries
     * @param  list<array{rid: string, bytes: string, format: string, n: int}>  $images
     * @return array<string, string>
     */
    private static function injectImages(array $entries, array $images): array
    {
        if ($images === []) {
            return $entries;
        }

        foreach ($images as $img) {
            $entries['word/media/image'.$img['n'].'.'.Image::extension($img['format'])] = $img['bytes'];
        }

        $entries = self::updateRels($entries, $images);

        return self::updateContentTypes($entries, $images);
    }

    /**
     * @param  array<string, string>  $entries
     * @param  list<array{rid: string, bytes: string, format: string, n: int}>  $images
     * @return array<string, string>
     */
    private static function updateRels(array $entries, array $images): array
    {
        $name = 'word/_rels/document.xml.rels';

        $newRels = '';
        foreach ($images as $img) {
            $target = 'media/image'.$img['n'].'.'.Image::extension($img['format']);
            $newRels .= '<Relationship Id="'.$img['rid'].'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="'.$target.'"/>';
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
     * @param  list<array{rid: string, bytes: string, format: string, n: int}>  $images
     * @return array<string, string>
     */
    private static function updateContentTypes(array $entries, array $images): array
    {
        $name = '[Content_Types].xml';
        if (! isset($entries[$name])) {
            return $entries;
        }

        $needed = array_values(array_unique(array_map(fn (array $i): string => $i['format'], $images)));
        $xml = $entries[$name];

        foreach ($needed as $fmt) {
            $ext = Image::extension($fmt);
            if (str_contains($xml, 'Extension="'.$ext.'"')) {
                continue;
            }
            $entry = '<Default Extension="'.$ext.'" ContentType="'.Image::contentType($fmt).'"/>';
            $xml = preg_replace('/(<Types\b[^>]*>)/', '$1'.$entry, $xml, 1);
        }

        $entries[$name] = $xml;

        return $entries;
    }
}
