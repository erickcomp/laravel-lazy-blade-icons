<?php

declare(strict_types=1);

namespace ErickComp\LazyBladeIcons\Exceptions;

use LogicException;

class AmbiguousIconName extends LogicException
{
    public static function forTag(string $tag, string $fromAttribute, string $fromContent): self
    {
        return new self(
            "The [<{$tag}>] tag got an icon name from both the [is] attribute ([{$fromAttribute}]) "
            . "and the tag content ([{$fromContent}]). Use only one of them."
        );
    }
}
