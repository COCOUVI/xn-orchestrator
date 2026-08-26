<?php

namespace Xn\Orchestrator\Cli\Support;

use Closure;
use Laravel\Prompts\ConfirmPrompt;
use Laravel\Prompts\Key;
use Laravel\Prompts\MultiSelectPrompt;
use Laravel\Prompts\Prompt;
use Laravel\Prompts\SearchPrompt;
use Laravel\Prompts\SelectPrompt;
use Throwable;

/**
 * Thin wrappers around Laravel Prompts' select()/multiselect() that additionally
 * return null when the user presses Escape, so screens can go back a step.
 *
 * This hooks into Prompt's own public event API (on('key', ...)) rather than
 * reimplementing raw keyboard reading, so it inherits whatever terminal support
 * Laravel Prompts already has (interactive on POSIX/WSL, dotted fallback on
 * native Windows or non-interactive runs, where Escape has no special meaning).
 */
final class EscapablePrompts
{
    /**
     * @param  array<int|string, string>  $options
     */
    public static function select(string $label, array $options, int|string|null $default = null, string $hint = ''): int|string|null
    {
        return self::run(new SelectPrompt($label, $options, $default, hint: $hint));
    }

    /**
     * @param  array<int|string, string>  $options
     * @param  list<int|string>  $default
     * @return list<int|string>|null
     */
    public static function multiselect(string $label, array $options, array $default = [], string $hint = ''): ?array
    {
        return self::run(new MultiSelectPrompt($label, $options, $default, hint: $hint));
    }

    /**
     * @param  Closure(string): array<int|string, string>  $options
     */
    public static function search(string $label, Closure $options, string $hint = ''): int|string|null
    {
        return self::run(new SearchPrompt($label, $options, hint: $hint));
    }

    public static function confirm(string $label, bool $default = true, string $hint = ''): ?bool
    {
        return self::run(new ConfirmPrompt($label, $default, hint: $hint));
    }

    /**
     * Arms the Escape listener, runs the prompt, and returns null if the user escaped.
     */
    private static function run(Prompt $prompt): mixed
    {
        $escaped = self::arm($prompt);

        try {
            $result = $prompt->prompt();
        } catch (Throwable $exception) {
            // Forcing submission on Escape steps outside states Prompts' own
            // renderers assume are reachable (e.g. SearchPrompt rendering its
            // "submit" box while nothing is highlighted yet). The result is
            // discarded either way once escaped, so swallow the render crash
            // rather than let an internal Prompts invariant break navigation.
            if (! $escaped()) {
                throw $exception;
            }

            $result = null;
        }

        return $escaped() ? null : $result;
    }

    /**
     * Registers an Escape listener on the prompt and returns a closure that
     * reports whether it fired.
     */
    private static function arm(Prompt $prompt): Closure
    {
        $escaped = false;

        $prompt->on('key', function (string $key) use ($prompt, &$escaped): void {
            if ($key === Key::ESCAPE) {
                $escaped = true;

                // SearchPrompt treats an unrecognized key (including Escape) as
                // "refresh search", which resets its internal matches cache to
                // null, and defaults to no highlighted match. Repopulate both
                // before forcing submission below where possible; any state
                // Prompts still can't render is caught in run() above.
                if ($prompt instanceof SearchPrompt) {
                    $matches = $prompt->matches();

                    if ($matches !== [] && $prompt->highlighted === null) {
                        $prompt->highlighted = 0;
                    }
                }

                $prompt->state = 'submit';
            }
        });

        return function () use (&$escaped): bool {
            return $escaped;
        };
    }
}
