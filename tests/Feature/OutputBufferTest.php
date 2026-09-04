<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;

it('does not leak an output buffer when the content throws', function () {
    $level = ob_get_level();

    try {
        Blade::render(
            '<x-icon:dynamic>{{ $boom->explode() }}</x-icon:dynamic>',
            ['boom' => null],
            deleteCachedView: true,
        );
    } catch (Throwable) {
        // the render is expected to fail; what matters is the buffer state
    }

    expect(ob_get_level())->toBe($level);
});

it('does not leak an output buffer when the icon is unknown', function () {
    $level = ob_get_level();

    try {
        Blade::render('<x-icon:dynamic>test-nope</x-icon:dynamic>', deleteCachedView: true);
    } catch (Throwable) {
        //
    }

    expect(ob_get_level())->toBe($level);
});

it('renders correctly inside a section and a push', function () {
    $rendered = Blade::render(<<<'BLADE'
        @push('scripts')<x-icon:dynamic>test-other</x-icon:dynamic>@endpush
        @section('body')<x-icon:test-icon />@endsection
        [@yield('body')][@stack('scripts')]
        BLADE, deleteCachedView: true);

    expect(substr_count($rendered, '<svg'))->toBe(2);
});
