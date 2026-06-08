<?php

declare(strict_types=1);

namespace DocxTemplate\Internal;

use DocxTemplate\TemplateException;
use ZipArchive;

final class Zip
{
    /**
     * @return array<string, string>
     */
    public static function unpack(string $bin): array
    {
        $tmp = self::tempFile($bin);

        $zip = new ZipArchive;
        $opened = $zip->open($tmp);
        if ($opened !== true) {
            @unlink($tmp);
            throw new TemplateException('Could not open .docx archive.');
        }

        $entries = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            $content = $zip->getFromIndex($i);
            if ($name === false || $content === false) {
                $zip->close();
                @unlink($tmp);
                throw new TemplateException('Could not read entry from .docx archive.');
            }

            $entries[$name] = $content;
        }

        $zip->close();
        @unlink($tmp);

        return $entries;
    }

    /**
     * @param  array<string, string>  $entries
     */
    public static function pack(array $entries): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'docx-out-');
        if ($tmp === false) {
            throw new TemplateException('Could not allocate temp file.');
        }

        @unlink($tmp);

        $zip = new ZipArchive;
        if ($zip->open($tmp, ZipArchive::CREATE) !== true) {
            throw new TemplateException('Could not create .docx archive.');
        }

        foreach ($entries as $name => $content) {
            $zip->addFromString($name, $content);
        }

        $zip->close();

        $bytes = @file_get_contents($tmp);
        @unlink($tmp);
        if ($bytes === false) {
            throw new TemplateException('Could not read packed .docx.');
        }

        return $bytes;
    }

    public static function isTemplatePart(string $name): bool
    {
        return in_array($name, ['word/document.xml', 'word/footnotes.xml', 'word/endnotes.xml'], true)
            || str_starts_with($name, 'word/header')
            || str_starts_with($name, 'word/footer');
    }

    private static function tempFile(string $bin): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'docx-in-');
        if ($tmp === false || @file_put_contents($tmp, $bin) === false) {
            if ($tmp !== false) {
                @unlink($tmp);
            }

            throw new TemplateException('Could not write temp file.');
        }

        return $tmp;
    }
}
