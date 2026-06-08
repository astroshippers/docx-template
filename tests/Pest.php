<?php

declare(strict_types=1);

use DocxTemplate\Template;

function fixturePath(string $name): string
{
    return __DIR__.'/fixtures/'.$name;
}

function documentXml(string $docxBytes): string
{
    $tmp = tempnam(sys_get_temp_dir(), 'docx');
    file_put_contents($tmp, $docxBytes);

    $zip = new ZipArchive;
    if ($zip->open($tmp) !== true) {
        unlink($tmp);
        throw new RuntimeException('Output is not a valid zip archive.');
    }

    $xml = $zip->getFromName('word/document.xml');
    $zip->close();
    unlink($tmp);

    if ($xml === false) {
        throw new RuntimeException('word/document.xml missing from output.');
    }

    return $xml;
}
