<?php

namespace Xn\Orchestrator\Cli\Support;

use Closure;
use Laravel\Prompts\ConfirmPrompt;
use Laravel\Prompts\Key;
use Laravel\Prompts\MultiSelectPrompt;
use Laravel\Prompts\Prompt;
use Laravel\Prompts\SearchPrompt;
use Laravel\Prompts\SelectPrompt;

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
        $prompt = new SelectPrompt($label, $options, $default, hint: $hint);

        $escaped = self::arm($prompt);

        $result = $prompt->prompt();

        return $escaped() ? null : $result;
    }

    /**
     * @param  array<int|string, string>  $options
     * @param  list<int|string>  $default
     * @return list<int|string>|null
     */
    public static function multiselect(string $label, array $options, array $default = [], string $hint = ''): ?array
    {
        $prompt = new MultiSelectPrompt($label, $options, $default, hint: $hint);

        $escaped = self::arm($prompt);

        $result = $prompt->prompt();

        return $escaped() ? null : $result;
    }

    /**
     * @param  Closure(string): array<int|string, string>  $options
     */
    public static function search(string $label, Closure $options, string $hint = ''): int|string|null
    {
        $prompt = new SearchPrompt($label, $options, hint: $hint);

        $escaped = self::arm($prompt);

        $result = $prompt->prompt();

        return $escaped() ? null : $result;
    }

    public static function confirm(string $label, bool $default = true, string $hint = ''): ?bool
    {
        $prompt = new ConfirmPrompt($label, $default, hint: $hint);

        $escaped = self::arm($prompt);

        $result = $prompt->prompt();

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
                $prompt->state = 'submit';
            }
        });

        return function () use (&$escaped): bool {
            return $escaped;
        };
    }
}
