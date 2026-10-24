<?php

use Symfony\Component\Finder\Finder;

/*
| Test names start with the acceptance criterion they prove (CLAUDE.md, spec-driven loop). This
| fails when a test names a REQ ID that no spec defines, e.g. after a spec was rewritten.
*/

test('every REQ ID used in a test name is defined in docs/specs', function () {
    $defined = [];

    foreach ((new Finder)->files()->in(base_path('docs/specs'))->name('requirements.md') as $file) {
        preg_match_all('/\*\*(REQ-\d{2}-\d{2})\*\*/', $file->getContents(), $matches);
        $defined = [...$defined, ...$matches[1]];
    }

    $used = [];

    foreach ((new Finder)->files()->in(base_path('tests'))->name('*.php') as $file) {
        preg_match_all("/(?:test|it)\\(\\s*['\"](REQ-\\d{2}-\\d{2})/", $file->getContents(), $matches);
        $used = [...$used, ...$matches[1]];
    }

    expect(array_values(array_diff(array_unique($used), $defined)))->toBe([]);
});
