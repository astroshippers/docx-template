<?php

declare(strict_types=1);

namespace DocxTemplate\Internal\Token;

enum BlockKind: string
{
    case If_ = 'if';
    case Unless = 'unless';
    case Each = 'each';
}
