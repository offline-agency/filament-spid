<?php

declare(strict_types=1);

namespace OfflineAgency\FilamentSpid\Install;

/**
 * Checks on the SP signing key pair the installer is pointed at. It reads
 * the files to compare them, and never copies, prints or generates keys.
 */
final class KeyPairFiles
{
    /**
     * @return list<string> errors
     */
    public static function validate(string $certificateFile, string $privateKeyFile): array
    {
        $errors = [];

        foreach (['sp.certificate_file' => $certificateFile, 'sp.private_key_file' => $privateKeyFile] as $field => $path) {
            if ($path === '') {
                continue;
            }
            if (str_contains($path, '-----BEGIN') || str_contains($path, "\n")) {
                $errors[] = "{$field} must be a path to a PEM file, not the key itself.";

                continue;
            }
            if (! is_file($path) || ! is_readable($path)) {
                $errors[] = "{$field}: {$path} is not a readable file.";

                continue;
            }
            $public = realpath(public_path());
            $real = realpath($path);
            if ($public !== false && $real !== false && str_starts_with($real, $public.DIRECTORY_SEPARATOR)) {
                $errors[] = "{$field} is under public/: anyone could download it. Keep keys in storage/ or outside the application.";
            }
        }

        if ($errors !== [] || $certificateFile === '' || $privateKeyFile === '') {
            return $errors;
        }

        $certificate = @openssl_x509_read((string) file_get_contents($certificateFile));
        $key = @openssl_pkey_get_private((string) file_get_contents($privateKeyFile));

        if ($certificate === false) {
            return ['sp.certificate_file is not a PEM X.509 certificate.'];
        }
        if ($key === false) {
            return ['sp.private_key_file is not a PEM private key (an encrypted key is not supported by italia/spid-laravel).'];
        }
        if (! openssl_x509_check_private_key($certificate, $key)) {
            return ['sp.private_key_file does not belong to sp.certificate_file.'];
        }

        return [];
    }

    /**
     * Whether group or others may read the key file.
     */
    public static function isExposed(string $privateKeyFile): bool
    {
        $permissions = @fileperms($privateKeyFile);

        return $permissions !== false && ($permissions & 0o077) !== 0;
    }
}
