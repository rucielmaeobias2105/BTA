<?php

namespace App\Support;

use DateTimeImmutable;
use Throwable;

/**
 * Appends prompt/instruction entries to prompt.log.
 *
 * Entries are ALWAYS appended so previous history is never lost. Each line is
 * formatted as: [YYYY-MM-DD HH:MM:SS] <prompt text>
 */
class PromptLogger
{
    /**
     * Absolute path of the project-root prompt.log.
     */
    public static function path(): string
    {
        return base_path('prompt.log');
    }

    /**
     * Append a single prompt entry.
     */
    public static function log(string $prompt, ?string $context = null): void
    {
        static::write($prompt, $context);
    }

    /**
     * Append a module-level summary entry.
     */
    public static function module(string $module, string $summary): void
    {
        static::write(sprintf('[MODULE] %s — %s', $module, $summary));
    }

    /**
     * Append many entries at once.
     *
     * @param  iterable<string>  $prompts
     */
    public static function many(iterable $prompts, ?string $context = null): void
    {
        foreach ($prompts as $prompt) {
            static::write((string) $prompt, $context);
        }
    }

    /**
     * Perform the append.
     */
    protected static function write(string $prompt, ?string $context = null): void
    {
        $prompt = trim($prompt);

        if ($prompt === '') {
            return;
        }

        $timestamp = (new DateTimeImmutable)->format('Y-m-d H:i:s');

        $line = sprintf('[%s] %s', $timestamp, $prompt);

        if ($context !== null && trim($context) !== '') {
            $line .= sprintf(' (context: %s)', trim($context));
        }

        $handle = @fopen(static::path(), 'a');

        if ($handle === false) {
            return;
        }

        // Normalise to a single line so each entry stays greppable.
        $line = preg_replace('/\R/u', ' ', $line) ?? $line;

        try {
            fwrite($handle, $line.PHP_EOL);
        } catch (Throwable) {
            // Logging must never break the application.
        } finally {
            fclose($handle);
        }
    }
}
