<?php

namespace OfflineAgency\FilamentSpid\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;
use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Contracts\Foundation\Application;

class VerifyCsrfToken extends Middleware
{
    public function __construct(Application $app, Encrypter $encrypter)
    {
        parent::__construct($app, $encrypter);
    }

    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * @var array<int, string>
     */
    protected $except = [
        '*/spid/*',
        '/spid/*',
    ];
}
