<?php

declare(strict_types=1);

namespace OfflineAgency\FilamentSpid\Install\Editors;

/**
 * Registers SpidPlugin on a panel provider: right after ->login() (or after
 * ->id('<panel>') when the panel has no ->login()), so a ->login() chained
 * later cannot put the default login page back.
 *
 * Only a file with exactly one ->id('<panel>') and at most one ->login() is
 * edited; anything else is reported for a manual edit.
 */
final class PanelProviderEditor
{
    public const SNIPPET = '->plugin(\OfflineAgency\FilamentSpid\SpidPlugin::make())';

    public function edit(string $source, string $panel): Edit
    {
        $tokens = Tokens::of($source);

        foreach ($tokens as $i => $token) {
            $next = Tokens::next($tokens, $i + 1);
            if ($token->text === 'SpidPlugin' && $next !== null && $tokens[$next]->is(T_DOUBLE_COLON)) {
                return new Edit($source, true);
            }
        }

        $ids = array_values(array_filter(Tokens::calls($tokens, 'id'), fn (int $i) => Tokens::stringArgument($tokens, $i) === $panel));
        if (count($ids) !== 1) {
            return Edit::unrecognised($source, "no single ->id('{$panel}') call");
        }

        $logins = Tokens::calls($tokens, 'login');
        if (count($logins) > 1) {
            return Edit::unrecognised($source, 'more than one ->login() call');
        }

        $anchor = $logins[0] ?? $ids[0];
        $open = Tokens::next($tokens, (int) Tokens::next($tokens, $anchor + 1) + 1);
        $close = $open === null ? null : Tokens::closing($tokens, $open);
        if ($close === null) {
            return Edit::unrecognised($source, 'unbalanced call');
        }

        $indentation = Tokens::indentationBefore($tokens, $anchor);
        $insertion = "\n{$indentation}->plugin(SpidPlugin::make())";

        $edited = Tokens::join(array_slice($tokens, 0, $close + 1)).$insertion.Tokens::join(array_slice($tokens, $close + 1));
        $edited = $this->import($edited);

        if ($edited === null || ! Tokens::parses($edited)) {
            return Edit::unrecognised($source, 'no namespace to import SpidPlugin into');
        }

        return new Edit($edited, true);
    }

    /**
     * Add `use OfflineAgency\FilamentSpid\SpidPlugin;` after the last import,
     * or after the namespace declaration.
     */
    protected function import(string $source): ?string
    {
        $tokens = Tokens::of($source);
        $after = null;

        foreach ($tokens as $i => $token) {
            if ($token->is([T_CLASS, T_FUNCTION])) {
                break;
            }
            if ($token->is([T_USE, T_NAMESPACE])) {
                for ($j = $i; $j < count($tokens) && $tokens[$j]->text !== ';' && $tokens[$j]->text !== '{'; $j++);
                if (($tokens[$j]->text ?? '') === ';') {
                    $after = $j;
                }
            }
        }

        if ($after === null) {
            return null;
        }

        return Tokens::join(array_slice($tokens, 0, $after + 1))
            ."\nuse OfflineAgency\\FilamentSpid\\SpidPlugin;"
            .Tokens::join(array_slice($tokens, $after + 1));
    }
}
