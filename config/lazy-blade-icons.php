<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Tag Prefix
    |--------------------------------------------------------------------------
    |
    | The prefix that marks a Blade tag as an icon tag. With the default
    | value, icons are rendered with <x-icon:fas-camera />.
    |
    */

    'prefix' => 'icon:',

    /*
    |--------------------------------------------------------------------------
    | Dynamic Keyword
    |--------------------------------------------------------------------------
    |
    | The reserved tag suffix used to render icons whose name is only known
    | at runtime: <x-icon:dynamic :is="$icon" /> or the element content
    | form <x-icon:dynamic>{{ $icon }}</x-icon:dynamic>.
    |
    | Change it if it ever collides with a real icon name.
    |
    */

    'dynamic_keyword' => 'dynamic',

    /*
    |--------------------------------------------------------------------------
    | Defer Stack
    |--------------------------------------------------------------------------
    |
    | The Blade stack deferred icons are pushed to. The default matches
    | blade-icons, so deferred icons interoperate with its own syntax
    | under a single @stack('bladeicons') in your layout.
    |
    */

    'stack' => 'bladeicons',

    /*
    |--------------------------------------------------------------------------
    | Default Defer
    |--------------------------------------------------------------------------
    |
    | false    no icon is deferred unless the tag says so
    | true     every icon is deferred, id is icon-{md5}
    | 'string' every icon is deferred, id is icon-{string}-{md5}
    |
    | A `defer` attribute on the tag always wins: `defer="false"` opts out,
    | and `defer="my-id"` pins that icon's id to icon-my-id.
    |
    | Anything other than false requires @stack('bladeicons') (or your
    | configured stack) in the layout, or deferred icons render nothing.
    |
    */

    'defer' => false,

];
