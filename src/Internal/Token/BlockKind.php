<?php

declare(strict_types=1);

namespace DocxTemplate\Internal\Token;

use DocxTemplate\Internal\Ast\EachNode;
use DocxTemplate\Internal\Ast\IfNode;
use DocxTemplate\Internal\Ast\Node;
use DocxTemplate\Internal\Ast\UnlessNode;

enum BlockKind: string
{
    case If_ = 'if';
    case Unless = 'unless';
    case Each = 'each';

    /**
     * @param  list<Node>  $children
     */
    public function buildNode(string $path, array $children): Node
    {
        return match ($this) {
            self::If_ => new IfNode($path, $children),
            self::Unless => new UnlessNode($path, $children),
            self::Each => new EachNode($path, $children),
        };
    }

    public static function openListForError(): string
    {
        return self::joinForError('#%s');
    }

    public static function closeListForError(): string
    {
        return self::joinForError('{{/%s}}');
    }

    private static function joinForError(string $format): string
    {
        return implode(', ', array_map(
            static fn (self $k): string => sprintf($format, $k->value),
            self::cases(),
        ));
    }
}
