<?php

use Laravel\Prompts\Key;
use Laravel\Prompts\Prompt;
use Xn\Orchestrator\Cli\Support\EscapablePrompts;

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
