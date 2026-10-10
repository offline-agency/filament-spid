<?php

declare(strict_types=1);

namespace OfflineAgency\FilamentSpid\Install\Editors;

/**
 * Exempts exactly the ACS path from CSRF verification in a Laravel 11+
 * bootstrap/app.php: `$middleware->validateCsrfTokens(except: ['<acs>'])` as
 * the first statement of the withMiddleware() closure. An existing
 * validateCsrfTokens() call is never merged automatically.
 */
final class CsrfExceptionEditor
{
    public static function snippet(string $acs): string
    {
        return "\$middleware->validateCsrfTokens(except: ['{$acs}']);";
    }

    public function edit(string $source, string $acs): Edit
    {
        $tokens = Tokens::of($source);
        $literal = var_export($acs, true);

        $existing = Tokens::calls($tokens, 'validateCsrfTokens');
        if ($existing !== []) {
            foreach ($tokens as $token) {
                if ($token->is(T_CONSTANT_ENCAPSED_STRING) && $token->text === $literal) {
                    return new Edit($source, true);
                }
            }

            return Edit::unrecognised($source, 'validateCsrfTokens() is already called; add the ACS path to its except list');
        }

        $calls = Tokens::calls($tokens, 'withMiddleware');
        if (count($calls) !== 1) {
            return Edit::unrecognised($source, 'no single ->withMiddleware() call');
        }

        $open = Tokens::next($tokens, (int) Tokens::next($tokens, $calls[0] + 1) + 1);
        $function = $open === null ? null : Tokens::next($tokens, $open + 1);
        if ($function === null || ! $tokens[$function]->is(T_FUNCTION)) {
            return Edit::unrecognised($source, 'withMiddleware() does not take a closure');
        }

        $parameters = Tokens::next($tokens, $function + 1);
        $parametersEnd = $parameters === null ? null : Tokens::closing($tokens, $parameters);
        $variable = null;
        for ($i = (int) $parameters; $parametersEnd !== null && $i < $parametersEnd; $i++) {
            if ($tokens[$i]->is(T_VARIABLE)) {
                $variable = $tokens[$i]->text;
            }
        }

        $body = null;
        for ($i = (int) $parametersEnd; $parametersEnd !== null && $i < count($tokens); $i++) {
            if ($tokens[$i]->text === '{') {
                $body = $i;

                break;
            }
        }

        if ($variable === null || $body === null) {
            return Edit::unrecognised($source, 'the withMiddleware() closure takes no Middleware parameter');
        }

        $after = Tokens::next($tokens, $body + 1);
        $indentation = $after === null ? '' : Tokens::indentationBefore($tokens, $after);
        if ($after !== null && $tokens[$after]->text === '}') {
            $indentation .= '    ';
        }

        $statement = "\n{$indentation}{$variable}->validateCsrfTokens(except: [{$literal}]);";
        $edited = Tokens::join(array_slice($tokens, 0, $body + 1)).$statement.Tokens::join(array_slice($tokens, $body + 1));

        return Tokens::parses($edited) ? new Edit($edited, true) : Edit::unrecognised($source, 'the edit would not parse');
    }
}
