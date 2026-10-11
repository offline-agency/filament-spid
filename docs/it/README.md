# Filament SPID: autenticazione SPID per Laravel Filament

Accesso ai pannelli [Filament](https://filamentphp.com) con
[SPID](https://www.spid.gov.it), il Sistema Pubblico di Identità Digitale,
tramite SAML 2.0. La parte SAML è di [italia/spid-laravel](https://github.com/italia/spid-laravel);
questo plugin mette il pulsante SPID nella pagina di login del pannello,
trasforma un'identità SPID validata in un accesso sul guard del pannello e
aggiunge un installer, un comando di diagnostica e un adapter per il validatore
dei metadata di AgID.

Pacchetto della comunità mantenuto da [Offline Agency](https://offlineagency.it),
con licenza [MIT](../../LICENSE.md). Non è un plugin ufficiale Filament né un
prodotto AgID, e installarlo non rende un servizio accreditato SPID: serve la
procedura di AgID ([test con gli strumenti AgID](../testing/agid.md)).

English: [README](../../README.md).

![La pagina di login SPID di un pannello Filament 5 con il menu degli IdP aperto (dati di test sintetici)](../images/login-light.png)

> **Pre-release.** Questa guida descrive la **1.0.0, non ancora su Packagist**:
> `composer require offline-agency/filament-spid:^1.0` funzionerà dopo il tag.
> La release pubblicata è la 0.1.8 ([README alla 0.1.8](https://github.com/offline-agency/filament-spid/blob/0.1.8/README.md));
> la serie 0.1.x non è più mantenuta ([SECURITY.md](../../SECURITY.md)).

## Requisiti

| | Supportati (ogni combinazione testata in CI) |
|---|---|
| PHP | 8.3, 8.4, 8.5 |
| Laravel | 12.x, 13.x |
| Filament | 3.x, 4.x, 5.x |
| Service Provider SPID | SP pubblici e privati; aggregatori e gestori non sono coperti |

Laravel 11 è fuori dal supporto di sicurezza dal 12 marzo 2026 e PHP 8.2 lo sarà
dal 31 dicembre 2026 ([Laravel](https://laravel.com/docs/13.x/releases#support-policy),
[PHP](https://www.php.net/supported-versions.php), verificato l'11 ottobre 2026).

## Chi fa cosa

| | Responsabile |
|---|---|
| SAML (AuthnRequest, validazione delle Response, firme, metadata), patch SPID di `onelogin/php-saml`, elenco degli IdP | [italia/spid-laravel](https://github.com/italia/spid-laravel) |
| Pulsante SPID, accesso sul guard del pannello, errori fail-closed, provisioning opzionale, eventi, `filament-spid:install`, `filament-spid:doctor`, `filament-spid:metadata:validate` | questo plugin |
| Chi può entrare (`canAccessPanel()`), identità, contatti e chiavi dell'SP, sessioni, logout, conservazione dei dati, HTTPS e proxy, collaudo AgID | la tua applicazione e il tuo ente |

## Avvio rapido

Servono un'applicazione Laravel 12 o 13 con un pannello Filament (`php artisan
filament:install --panels`), la coppia di chiavi di firma dell'SP
([certificati](installazione.md#certificati)) e i dati reali dell'SP (ente,
codice IPA o partita IVA, email di contatto).

```bash
# Nella root di un'applicazione Laravel 12 o 13 con un pannello Filament.

# 1. italia/spid-laravel con la patch SPID per php-saml ^4.3.1: finché upstream
#    non pubblica una release, il fork offline-agency a un commit fissato.
composer config repositories.spid-laravel vcs https://github.com/offline-agency/spid-laravel
composer config allow-plugins.cweagans/composer-patches true
composer config extra.enable-patching true --json
composer require "italia/spid-laravel:dev-feat/laravel-13#b8dd1a484d8e6551a1a84e90220c2e9dc0d0a0fe as 2.1.0-beta" --no-update

# 2. Il plugin.
composer require offline-agency/filament-spid:^1.0

# 3. Installazione e configurazione da setup.json (copia examples/setup.public.json
#    o setup.private.json dal pacchetto e inserisci i dati reali dell'SP e i
#    percorsi delle chiavi). --dry-run mostra il piano e non scrive nulla.
php artisan filament-spid:install --panel=admin --input=setup.json --dry-run
php artisan filament-spid:install --panel=admin --input=setup.json

# 4. Verifica.
php artisan migrate
php artisan filament-spid:doctor
```

Il passo 1 serve perché ogni tag di `italia/spid-laravel` (fino a v2.1.0-beta)
fissa `onelogin/php-saml` 4.1.0, colpito da
[CVE-2025-66475](https://github.com/advisories/GHSA-5j8p-438x-rgg5). La
correzione è integrata upstream ([#132](https://github.com/italia/spid-laravel/pull/132))
ma non ancora rilasciata; il fork è fissato a un commit, e così l'URL della sua
patch. Solo `italia/spid-laravel` riceve un vincolo dev: la `minimum-stability`
dell'applicazione non cambia.

Senza `--input` l'installer pone le domande. In ogni caso valida tutto prima di
scrivere, mostra il piano, conserva i file esistenti, registra il plugin dopo
`->login()`, esenta dal CSRF solo l'ACS ed elenca come attività manuali ciò che
non può fare in sicurezza (e allora termina con 1). Rieseguirlo non cambia nulla.

Poi decidi chi può entrare: SPID dimostra chi è il cittadino, non cosa può fare.

```php
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;

class User extends Authenticatable implements FilamentUser
{
    protected $fillable = ['name', 'email', 'password', 'fiscal_code', 'spid_data'];

    protected $casts = ['spid_data' => 'array'];

    public function canAccessPanel(Panel $panel): bool
    {
        // La tua regola, ad esempio una colonna di ruolo gestita dall'applicazione.
        return $panel->getId() === 'admin' && $this->role === 'administrator';
    }
}
```

Gli account si collegano per codice fiscale, mai per email. Nessun account viene
creato per un cittadino SPID se non attivi `auto_create_users`, e anche in quel
caso decide `canAccessPanel()`.

## Documentazione

- [Installazione e configurazione](installazione.md) (in italiano).
- In inglese: [configurazione](../configuration.md), [funzionamento del login](../authentication.md), [diagnostica](../diagnostics.md), [test con gli strumenti AgID](../testing/agid.md), [evidenze](../testing/evidence.md), [checklist di produzione](../production-checklist.md), [risoluzione dei problemi](../troubleshooting.md), [FAQ](../faq.md), [aggiornamento dalla 0.1.x](../upgrading.md).

## Sicurezza e supporto

Le vulnerabilità vanno segnalate via email come indicato in
[SECURITY.md](../../SECURITY.md), mai in una issue pubblica. Per domande e bug:
[issue su GitHub](https://github.com/offline-agency/filament-spid/issues) o
support@offlineagency.it.
