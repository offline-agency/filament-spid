<?php

declare(strict_types=1);

namespace OfflineAgency\FilamentSpid\Validation;

use Illuminate\Support\Facades\Http;

/**
 * Downloads the metadata an SP really publishes: HTTPS only, TLS verified,
 * no redirects (a redirect usually means a login page), bounded in time and
 * size. The bytes are kept as received: the document is signed.
 */
class MetadataFetcher
{
    public const MAX_BYTES = 1048576;

    /**
     * @throws MetadataUnavailable
     */
    public function fetch(string $url, int $timeout, ?string $caFile = null): string
    {
        try {
            $response = Http::timeout($timeout)
                ->withoutRedirecting()
                ->withOptions(['verify' => $caFile ?? true])
                ->accept('application/samlmetadata+xml, application/xml, text/xml')
                ->get($url);
        } catch (\Throwable $e) {
            throw new MetadataUnavailable("The metadata could not be downloaded from {$url} (".$e::class.').');
        }

        if ($response->status() !== 200) {
            throw new MetadataUnavailable("{$url} answered HTTP {$response->status()}, not the metadata document.");
        }

        return self::ensureMetadata($response->body(), $url);
    }

    /**
     * @throws MetadataUnavailable
     */
    public static function ensureMetadata(string $bytes, string $source): string
    {
        if (strlen($bytes) > self::MAX_BYTES) {
            throw new MetadataUnavailable("{$source} is larger than ".self::MAX_BYTES.' bytes.');
        }

        $document = new \DOMDocument;
        $loaded = $bytes !== '' && @$document->loadXML($bytes, LIBXML_NONET);
        $root = $loaded ? $document->documentElement : null;

        if ($root === null || $root->localName !== 'EntityDescriptor' || $root->namespaceURI !== 'urn:oasis:names:tc:SAML:2.0:metadata') {
            throw new MetadataUnavailable("{$source} is not SAML metadata (no md:EntityDescriptor root).");
        }

        return $bytes;
    }
}
