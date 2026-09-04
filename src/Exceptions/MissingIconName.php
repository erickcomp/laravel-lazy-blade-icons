<?php

declare(strict_types=1);

namespace ErickComp\LazyBladeIcons\Exceptions;

use LogicException;

class MissingIconName extends LogicException
{
    public static function forTag(string $tag): self
    {
        return new self(
            "The [<{$tag}>] tag has no icon name. Provide it with the [is] attribute "
            . "(<{$tag} :is=\"\$icon\" />) or as the tag content (<{$tag}>{{ \$icon }}</{$tag}>)."
        );
    }
}
