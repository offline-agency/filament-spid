<?php

declare(strict_types=1);

namespace OfflineAgency\FilamentSpid\Install\Editors;

use PhpToken;
use Symfony\Component\Process\Process;

/**
 * Small helpers over PHP's own tokenizer: edits are made at token positions,
 * never by matching source text with regular expressions.
 */
final class Tokens
{
    /**
     * @return list<PhpToken>
     */
    public static function of(string $source): array
    {
        return array_values(PhpToken::tokenize($source));
    }

    /**
     * The next significant token index from $i (inclusive), or null.
     *
     * @param  list<PhpToken>  $tokens
     */
    public static function next(array $tokens, int $i): ?int
    {
        for ($count = count($tokens); $i < $count; $i++) {
            if (! $tokens[$i]->isIgnorable()) {
                return $i;
            }
        }

        return null;
    }

    /**
     * The index of the parenthesis closing the one at $open, or null.
     *
     * @param  list<PhpToken>  $tokens
     */
    public static function closing(array $tokens, int $open): ?int
    {
        $depth = 0;
        for ($i = $open, $count = count($tokens); $i < $count; $i++) {
            if ($tokens[$i]->text === '(') {
                $depth++;
            } elseif ($tokens[$i]->text === ')' && --$depth === 0) {
                return $i;
            }
        }

        return null;
    }

    /**
     * Indexes of `->name(` method calls: the T_OBJECT_OPERATOR position.
     *
     * @param  list<PhpToken>  $tokens
     * @return list<int>
     */
    public static function calls(array $tokens, string $name): array
    {
        $found = [];
        foreach ($tokens as $i => $token) {
            if (! $token->is(T_OBJECT_OPERATOR)) {
                continue;
            }
            $method = self::next($tokens, $i + 1);
            $open = $method === null ? null : self::next($tokens, $method + 1);
            if ($method !== null && $open !== null && $tokens[$method]->text === $name && $tokens[$open]->text === '(') {
                $found[] = $i;
            }
        }

        return $found;
    }

    /**
     * The first argument of a call as a plain string literal, or null.
     *
     * @param  list<PhpToken>  $tokens
     */
    public static function stringArgument(array $tokens, int $operator): ?string
    {
        $method = self::next($tokens, $operator + 1);
        $open = $method === null ? null : self::next($tokens, $method + 1);
        $argument = $open === null ? null : self::next($tokens, $open + 1);
        $close = $argument === null ? null : self::next($tokens, $argument + 1);

        if ($argument === null || $close === null || ! $tokens[$argument]->is(T_CONSTANT_ENCAPSED_STRING) || $tokens[$close]->text !== ')') {
            return null;
        }

        return stripcslashes(substr($tokens[$argument]->text, 1, -1));
    }

    /**
     * @param  list<PhpToken>  $tokens
     */
    public static function join(array $tokens): string
    {
        return implode('', array_map(fn (PhpToken $token) => $token->text, $tokens));
    }

    /**
     * Whether the source parses.
     */
    public static function parses(string $source): bool
    {
        try {
            PhpToken::tokenize($source, TOKEN_PARSE);

            return true;
        } catch (\ParseError) {
            return false;
        }
    }

    /**
     * Whether the source compiles (`php -l`): parsing alone misses errors such
     * as an import whose name is already in use.
     */
    public static function compiles(string $source): bool
    {
        if (! self::parses($source)) {
            return false;
        }

        $file = sys_get_temp_dir().'/filament-spid-lint-'.bin2hex(random_bytes(8)).'.php';
        file_put_contents($file, $source);
        $process = new Process([PHP_BINARY, '-l', $file]);
        $process->run();
        unlink($file);

        return $process->isSuccessful();
    }

    /**
     * The whitespace that starts the line a token sits on.
     *
     * @param  list<PhpToken>  $tokens
     */
    public static function indentationBefore(array $tokens, int $i): string
    {
        $previous = $tokens[$i - 1] ?? null;
        if ($previous !== null && $previous->is(T_WHITESPACE) && str_contains($previous->text, "\n")) {
            return substr($previous->text, (int) strrpos($previous->text, "\n") + 1);
        }

        return '';
    }
}
