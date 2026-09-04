<?php

declare(strict_types=1);

namespace ErickComp\LazyBladeIcons\Exceptions;

use LogicException;

class InvalidIconTagUsage extends LogicException
{
    public static function unexpectedContent(string $tag, string $keyword): self
    {
        return new self(
            "The [<{$tag}>] tag already names its icon, so it cannot have content. "
            . "Use [<{$tag} />] instead, or [<x-…:{$keyword}>] to name the icon dynamically."
        );
    }

    public static function unexpectedIsAttribute(string $tag, string $keyword): self
    {
        return new self(
            "The [<{$tag}>] tag already names its icon, so it cannot take an [is] attribute. "
            . "Use [<x-…:{$keyword} :is=\"\$icon\" />] to name the icon dynamically."
        );
    }
}
