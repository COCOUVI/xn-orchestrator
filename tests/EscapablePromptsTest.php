<?php

use Laravel\Prompts\Key;
use Laravel\Prompts\Prompt;
use Xn\Orchestrator\Cli\Support\EscapablePrompts;


beforeEach(function () {
    // Laravel Prompts hard-blocks Windows unconditionally (Prompt::checkEnvironment()),
    // even under Prompt::fake() for tests, so these can never run there.
    if (PHP_OS_FAMILY === 'Windows') {
        $this->markTestSkipped('Laravel Prompts does not support Windows, even under Prompt::fake().');
    }

    // Illuminate\Console\Concerns\ConfiguresPrompts sets Prompt::$shouldFallback
    // to true (one-way, never reset) the moment any Artisan command runs under
    // tests, since Application::runningUnitTests() is always true there. Other
    // test files in this suite do run real commands via $this->artisan(...),
    // and that pollutes this static flag for the rest of the process — which
    // would make Prompt::fake() below silently fall back to Symfony's
    // ConfirmationQuestion/ChoiceQuestion instead of the faked interactive
    // terminal. Force it back off so these tests exercise the real prompt loop
    // regardless of what ran earlier or Pest's random test order.
    $property = new ReflectionProperty(Prompt::class, 'shouldFallback');
    $property->setAccessible(true);
    $property->setValue(null, false);
});

it('returns null instead of crashing when escape is pressed before any search results are highlighted', function () {
    Prompt::fake([Key::ESCAPE]);

    $result = EscapablePrompts::search(
        label: 'Search for a package',
        options: fn (string $value) => ['laravel/sanctum' => 'laravel/sanctum'],
    );

    expect($result)->toBeNull();
});

it('returns null when escape is pressed on a select prompt', function () {
    Prompt::fake([Key::ESCAPE]);

    $result = EscapablePrompts::select('Main Menu', ['Browse categories', 'Quit']);

    expect($result)->toBeNull();
});

it('returns null when escape is pressed on a multiselect prompt', function () {
    Prompt::fake([Key::ESCAPE]);

    $result = EscapablePrompts::multiselect('Select packages', ['a' => 'a', 'b' => 'b']);

    expect($result)->toBeNull();
});

it('returns null when escape is pressed on a confirm prompt', function () {
    Prompt::fake([Key::ESCAPE]);

    $result = EscapablePrompts::confirm('Proceed with installation?');

    expect($result)->toBeNull();
});
