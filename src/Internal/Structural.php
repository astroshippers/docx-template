<?php

declare(strict_types=1);

namespace DocxTemplate\Internal;

final class Structural
{
    private const string CONTROL_TAG_CAPTURE = '(\{\{\s*[#\/](?:if|unless|each)(?:\s+[a-zA-Z_][a-zA-Z0-9_.]*)?\s*\}\})';

    public static function fixup(string $xml): string
    {
        $xml = self::unwrap($xml, '/<w:tr\b[^>]*>(?:(?!<\/w:tr>).)*?'.self::CONTROL_TAG_CAPTURE.'(?:(?!<\/w:tr>).)*?<\/w:tr>/s');

        return self::unwrap($xml, '/<w:p\b[^>]*>(?:(?!<\/w:p>).)*?'.self::CONTROL_TAG_CAPTURE.'(?:(?!<\/w:p>).)*?<\/w:p>/s');
    }

    private static function unwrap(string $xml, string $regex): string
    {
        $out = preg_replace_callback($regex, function (array $m): string {
            $match = $m[0];
            $tag = $m[1];

            return trim(self::extractText($match)) === $tag ? $tag : $match;
        }, $xml);

        return $out ?? $xml;
    }

    private static function extractText(string $xml): string
    {
        if (preg_match_all('/<w:t\b[^>]*>(.*?)<\/w:t>/s', $xml, $matches) === false) {
            return '';
        }

        /** @var list<string> $texts */
        $texts = $matches[1];

        return implode('', $texts);
    }
}
