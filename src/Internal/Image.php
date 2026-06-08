<?php

declare(strict_types=1);

namespace DocxTemplate\Internal;

final class Image
{
    private const int CM_TO_EMU = 360_000;

    public static function cmToEmu(float|int $cm): int
    {
        return (int) round($cm * self::CM_TO_EMU);
    }

    public static function paragraphXml(string $rid, int $cx, int $cy, int $n): string
    {
        return '<w:p><w:r><w:drawing>'
            .'<wp:inline xmlns:wp="http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing" distT="0" distB="0" distL="0" distR="0">'
            .'<wp:extent cx="'.$cx.'" cy="'.$cy.'"/>'
            .'<wp:effectExtent l="0" t="0" r="0" b="0"/>'
            .'<wp:docPr id="'.$n.'" name="Image'.$n.'"/>'
            .'<wp:cNvGraphicFramePr/>'
            .'<a:graphic xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main">'
            .'<a:graphicData uri="http://schemas.openxmlformats.org/drawingml/2006/picture">'
            .'<pic:pic xmlns:pic="http://schemas.openxmlformats.org/drawingml/2006/picture">'
            .'<pic:nvPicPr><pic:cNvPr id="'.$n.'" name="Image'.$n.'"/><pic:cNvPicPr/></pic:nvPicPr>'
            .'<pic:blipFill>'
            .'<a:blip xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" r:embed="'.$rid.'"/>'
            .'<a:stretch><a:fillRect/></a:stretch></pic:blipFill>'
            .'<pic:spPr><a:xfrm><a:off x="0" y="0"/><a:ext cx="'.$cx.'" cy="'.$cy.'"/></a:xfrm>'
            .'<a:prstGeom prst="rect"><a:avLst/></a:prstGeom></pic:spPr>'
            .'</pic:pic></a:graphicData></a:graphic></wp:inline></w:drawing></w:r></w:p>';
    }
}
