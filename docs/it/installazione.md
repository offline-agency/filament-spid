# Installazione e configurazione

> Si applica a filament-spid **1.0.0** (non ancora su Packagist). English:
> [installation](../installation.md), che resta il riferimento completo.

## Prima di iniziare

- Un'applicazione Laravel 12 o 13 su PHP 8.3–8.5 con un pannello Filament 3, 4
  o 5 (`php artisan filament:install --panels` se manca).
- HTTPS: SPID lo richiede, e `italia/spid-laravel` conserva lo stato del login
  in cookie `Secure; SameSite=None`, che il browser non invia su HTTP.
- I dati reali del Service Provider (SP): l'ente che eroga il servizio (non lo
  sviluppatore né Offline Agency), il codice IPA (PA) o la partita IVA
  (privati), un'email di contatto.
- La coppia di chiavi di firma dell'SP ([certificati](#certificati)).
- La regola di accesso al pannello (`canAccessPanel()`) e la scelta se SPID
  possa creare account (`auto_create_users`, disattivato per default).

## Composer

Prima di `composer require offline-agency/filament-spid`:

```bash
composer config repositories.spid-laravel vcs https://github.com/offline-agency/spid-laravel
composer config allow-plugins.cweagans/composer-patches true
composer config extra.enable-patching true --json
composer require "italia/spid-laravel:dev-feat/laravel-13#b8dd1a484d8e6551a1a84e90220c2e9dc0d0a0fe as 2.1.0-beta" --no-update
composer require offline-agency/filament-spid:^1.0
```

- Il repository e il fork fissato a un commit servono finché upstream non
  pubblica una release con php-saml ^4.3.1 ([#16](https://github.com/offline-agency/filament-spid/issues/16)).
- `enable-patching` e il permesso a `cweagans/composer-patches` servono perché
  la patch SPID di php-saml venga applicata: senza, gli IdP rifiutano le richieste.
- Non far ignorare a Composer gli advisory di `onelogin/php-saml` o
  `robrichards/xmlseclibs`: il plugin dichiara anche un `conflict` con
  `onelogin/php-saml <4.3.1`.

## L'installer

```bash
cp vendor/offline-agency/filament-spid/examples/setup.public.json setup.json   # oppure setup.private.json
# modifica setup.json: dati reali dell'SP e percorsi delle chiavi
php artisan filament-spid:install --panel=admin --input=setup.json --dry-run
php artisan filament-spid:install --panel=admin --input=setup.json
php artisan migrate
```

| Opzione | Effetto |
|---|---|
| `--panel=<id>` | Il pannello di destinazione; obbligatorio se ce n'è più d'uno, mai indovinato. |
| `--input=<file>` | Le risposte in JSON (schema in [installation](../installation.md#input-schema-schema_version-1)). |
| `--dry-run` | Mostra il piano senza scrivere file, cache, database o chiavi. |
| `--migrate` | Esegue anche `php artisan migrate --force`. |
| `--no-interaction` | Nessuna domanda; richiede `--input`. Dati mancanti o non validi: nessuna scrittura. |

Cosa fa, dopo aver validato **tutto** l'input: pubblica solo i file mancanti
(config, migration, asset), senza sovrascrivere nulla; scrive
`config/spid-auth.php` solo se è ancora la copia della libreria o quella
scritta dall'installer (altrimenti stampa i valori da impostare); imposta in
`.env` `FILAMENT_SPID_PANEL`, `SPID_AUTO_CREATE_USERS`,
`FILAMENT_SPID_SP_PROFILE` e `FILAMENT_SPID_DEPLOYMENT`; esegue
`filament:assets`; registra il plugin subito dopo `->login()` se riconosce il
PanelProvider (tramite il tokenizer PHP, mai con espressioni regolari);
esenta dal CSRF solo l'ACS; migra solo con `--migrate`. Al termine riporta gli
URL di login, metadata e ACS, il profilo, i comandi successivi e le attività
manuali; con attività manuali termina con 1. Se un passo fallisce indica cosa è
stato applicato: rieseguendo lo stesso comando i passi già fatti vengono
saltati. Migration ed effetti esterni non vengono annullati.

## SP pubblici e privati

| | SP pubblico (`spid-sp-public`) | SP privato (`spid-sp-private`) |
|---|---|---|
| Chi | pubbliche amministrazioni presenti in IPA | aziende e altri soggetti privati |
| In `sp_contact_persons.other` | `Public: true`, `IPACode`, `EmailAddress` | `Private: true`, `VATNumber`, `EmailAddress` (`FiscalCode` se applicabile) |
| Contatto di fatturazione | non richiesto | richiesto dalle regole SPID (`billing` con `CessionarioCommittente`): l'installer lo lascia come attività manuale e non inventa dati di fatturazione |

## Certificati

Due certificati diversi: quello **HTTPS** del dominio (dalla tua CA abituale) e
quello **di firma SAML** dell'SP, che firma metadata e AuthnRequest e segue le
regole tecniche SPID (Avviso SPID n. 29 v3), diverse per SP pubblici e privati.
Le indicazioni AgID decidono cosa è accettabile in produzione; un
self-signed generato in locale va bene per il laboratorio.

[italia/spid-compliant-certificates-python](https://github.com/italia/spid-compliant-certificates-python)
(v0.5.5, strumento di Developers Italia, non un'autorità di accreditamento)
genera e verifica certificati secondo l'Avviso 29 v3:

```bash
pip install spid-compliant-certificates==0.5.5
spid-compliant-certificates generator --key-size 3072 --days 730 \
    --common-name "Comune di Esempio" --entity-id https://sp.example.test \
    --locality-name Roma --org-id "PA:IT-c_x000" --org-name "Comune di Esempio" --sector public
spid-compliant-certificates validator --sector public
```

La chiave privata va fuori da `public/` e da Git (per esempio
`storage/app/private/spid/`, `chmod 600`). Installer e doctor non stampano,
copiano o generano chiavi; il doctor verifica corrispondenza, scadenza (avviso a
30 giorni) e che non sia la coppia di esempio distribuita da `italia/spid-laravel`.

## URL

| | Esempio | Configurazione |
|---|---|---|
| entityID | `https://sp.example.test` | `spid-auth.sp_entity_id` |
| URL base | `https://sp.example.test` | `spid-auth.sp_base_url`: da qui derivano ACS e SLO nel metadata |
| Prefisso | `spid` | `spid-auth.routes_prefix` |
| Metadata | `https://sp.example.test/spid/metadata` | `<URL base>/<prefisso>/metadata` |
| ACS | `https://sp.example.test/spid/acs` | `<URL base>/<prefisso>/acs` |

## CSRF e ACS

L'IdP invia la Response all'ACS da un altro sito, senza token CSRF. Esenta
**solo quel percorso** in `bootstrap/app.php`:

```php
->withMiddleware(function (Middleware $middleware): void {
    // Laravel 13
    $middleware->preventRequestForgery(except: ['spid/acs']);
    // Laravel 12: $middleware->validateCsrfTokens(except: ['spid/acs']);
})
```

Mai `spid/*`: il form di login invia a `spid/login` con il token e deve
continuare a farlo.

## Dietro un proxy

Se il TLS termina su un proxy: `trustProxies()` per Laravel e
`\OneLogin\Saml2\Utils::setProxyVars(true)` per php-saml (che confronta la
`Destination` della Response con l'URL di ricezione), con il proxy che invia
`X-Forwarded-Proto`, `-Host` e `-Port`. Solo se l'applicazione è raggiungibile
esclusivamente dal proxy.

## Dopo l'installazione

```bash
php artisan filament-spid:doctor --strict
php artisan filament-spid:metadata:validate --url=https://sp.example.test/spid/metadata --profile=spid-sp-public
```

Poi [test con gli strumenti AgID](../testing/agid.md) (in inglese) e la
[checklist di produzione](../production-checklist.md).
