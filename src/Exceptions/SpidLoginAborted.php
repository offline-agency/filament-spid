<?php

declare(strict_types=1);

namespace OfflineAgency\FilamentSpid\Exceptions;

use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * The redirect HandleSpidLogin ends a refused login with.
 *
 * Its own type, so the listener can tell its own redirect from one thrown by
 * an observer or callback: those are failures, never a way around them.
 */
class SpidLoginAborted extends HttpResponseException {}
