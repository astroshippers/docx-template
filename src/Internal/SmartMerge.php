<?php

declare(strict_types=1);

namespace DocxTemplate\Internal;

final readonly class SmartMerge
{
    public function heal(string $xml): string
    {
        $out = '';
        $i = 0;
        $n = strlen($xml);

        while ($i < $n) {
            $open = strpos($xml, '{{', $i);
            if ($open === false) {
                $out .= substr($xml, $i);
                break;
            }

            $out .= substr($xml, $i, $open - $i);
            $afterOpen = $open + 2;

            $result = $this->findClose($xml, $afterOpen);
            if ($result === null) {
                $out .= substr($xml, $open);
                break;
            }

            [$inner, $afterClose] = $result;
            $out .= '{{'.$inner.'}}';
            $i = $afterClose;
        }

        return $out;
    }

    /**
     * Scan from $start for `}}`, splicing out `</w:t>…<w:t…>` gaps along the way.
     * Returns [inner, indexAfterClose] or null if unclosed.
     *
     * @return array{0: string, 1: int}|null
     */
    private function findClose(string $xml, int $start): ?array
    {
        $n = strlen($xml);
        $inner = '';
        $i = $start;

        while ($i < $n) {
            if ($i + 1 < $n && $xml[$i] === '}' && $xml[$i + 1] === '}') {
                return [$inner, $i + 2];
            }

            if (substr($xml, $i, 6) === '</w:t>') {
                $afterOpen = $this->skipToNextTextRun($xml, $i + 6);
                if ($afterOpen === null) {
                    return null;
                }

                $i = $afterOpen;

                continue;
            }

            $inner .= $xml[$i];
            $i++;
        }

        return null;
    }

    private function skipToNextTextRun(string $xml, int $from): ?int
    {
        $pos = strpos($xml, '<w:t', $from);
        if ($pos === false) {
            return null;
        }

        $between = substr($xml, $from, $pos - $from);
        if (! $this->safeBetween($between)) {
            return null;
        }

        if (substr($xml, $pos, 6) === '<w:t/>') {
            return null;
        }

        $gt = strpos($xml, '>', $pos);
        if ($gt === false) {
            return null;
        }

        return $gt + 1;
    }

    private function safeBetween(string $s): bool
    {
        return array_all(['<w:p ', '<w:p>', '</w:p>', '<w:br'], fn ($needle): bool => ! str_contains($s, (string) $needle));
    }
}
