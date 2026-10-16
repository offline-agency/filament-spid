<?php

declare(strict_types=1);

namespace OfflineAgency\FilamentSpid\Doctor\Checks;

use Italia\SPIDAuth\ServiceProvider as SpidAuthServiceProvider;
use OfflineAgency\FilamentSpid\Doctor\Check;
use OfflineAgency\FilamentSpid\Doctor\Result;
use OfflineAgency\FilamentSpid\Support\TypedConfig;

/**
 * The SP certificate and private key: readable, matching, not expired and
 * not the example pair italia/spid-laravel ships. Only the expiry date is
 * reported; no key material reaches the output.
 */
class CertificateCheck implements Check
{
    public const EXPIRY_WARNING_DAYS = 30;

    public function run(): Result
    {
        $certificate = $this->read('sp_certificate', 'CERTIFICATE');
        $key = $this->read('sp_private_key', 'PRIVATE KEY');

        $x509 = $certificate !== null ? @openssl_x509_read($certificate) : false;
        if ($x509 === false) {
            return Result::fail('sp_certificate', 'spid-auth.sp_certificate (or sp_certificate_file) is missing or not a readable X.509 certificate.', 'Generate the SP certificate as the SPID technical rules require and set it in config/spid-auth.php.');
        }

        $privateKey = $key !== null ? @openssl_pkey_get_private($key) : false;
        if ($privateKey === false) {
            return Result::fail('sp_certificate', 'spid-auth.sp_private_key (or sp_private_key_file) is missing or not a readable private key.', 'Set the private key that belongs to the SP certificate.');
        }

        if (! openssl_x509_check_private_key($x509, $privateKey)) {
            return Result::fail('sp_certificate', 'spid-auth.sp_private_key does not belong to spid-auth.sp_certificate.', 'Configure the key pair that was generated together.');
        }

        if ($this->isLibraryExample($certificate)) {
            $message = 'spid-auth.sp_certificate is the example certificate shipped by italia/spid-laravel; its private key is public.';

            return app()->isProduction()
                ? Result::fail('sp_certificate', $message, 'Generate your own SP key pair before going to production.')
                : Result::warn('sp_certificate', $message, 'Generate your own SP key pair before going to production.');
        }

        $parsed = openssl_x509_parse($x509);
        $validTo = is_array($parsed) && is_int($parsed['validTo_time_t'] ?? null) ? $parsed['validTo_time_t'] : 0;
        $expiry = date('Y-m-d', $validTo);
        $now = now()->getTimestamp();

        if ($validTo <= $now) {
            return Result::fail('sp_certificate', "The SP certificate expired on {$expiry}.", 'Renew the SP certificate and update the metadata registered with AgID.');
        }

        if ($validTo <= $now + self::EXPIRY_WARNING_DAYS * 86400) {
            return Result::warn('sp_certificate', "The SP certificate expires on {$expiry}.", 'Renew the SP certificate and update the metadata registered with AgID before it expires.');
        }

        return Result::pass('sp_certificate', "The SP certificate and key match; the certificate expires on {$expiry}.");
    }

    /**
     * The PEM for spid-auth.<key>, inline (bare base64 or PEM) or from <key>_file.
     */
    protected function read(string $key, string $label): ?string
    {
        $value = TypedConfig::string("spid-auth.{$key}");

        if ($value === null || $value === '') {
            $file = TypedConfig::string("spid-auth.{$key}_file");
            $value = $file !== null && is_readable($file) ? (string) file_get_contents($file) : null;
        }

        if ($value === null || $value === '') {
            return null;
        }

        return str_contains($value, '-----BEGIN')
            ? $value
            : "-----BEGIN {$label}-----\n".chunk_split(preg_replace('/\s+/', '', $value) ?? '', 64)."-----END {$label}-----\n";
    }

    protected function isLibraryExample(string $certificate): bool
    {
        $file = dirname((string) (new \ReflectionClass(SpidAuthServiceProvider::class))->getFileName(), 2).'/config/spid-auth.php';
        $defaults = is_file($file) ? require $file : [];
        $example = is_array($defaults) && is_string($defaults['sp_certificate'] ?? null) ? $defaults['sp_certificate'] : null;

        $normalise = fn (string $pem): string => preg_replace('/-----[^-]+-----|\s+/', '', $pem) ?? '';

        return $example !== null && $normalise($certificate) === $normalise($example);
    }
}
