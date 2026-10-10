<?php

use Illuminate\Auth\Events\Login;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Italia\SPIDAuth\Events\LoginEvent;
use Italia\SPIDAuth\SPIDUser;
use OfflineAgency\FilamentSpid\Events\SpidAuthenticationFailed;
use OfflineAgency\FilamentSpid\Events\SpidAuthenticationSucceeded;
use OfflineAgency\FilamentSpid\Events\SpidUserCreated;
use OfflineAgency\FilamentSpid\Events\SpidUserProvisioning;
use OfflineAgency\FilamentSpid\Events\SpidUserUpdated;
use OfflineAgency\FilamentSpid\Exceptions\SpidPanelNotFoundException;
use OfflineAgency\FilamentSpid\Listeners\HandleSpidLogin;
use OfflineAgency\FilamentSpid\Tests\Fixtures\PanelUser;
use OfflineAgency\FilamentSpid\Tests\Fixtures\RecordingGuard;
use OfflineAgency\FilamentSpid\Tests\Fixtures\User;
use Symfony\Component\HttpFoundation\Response;

function spidUser(array $overrides = []): SPIDUser
{
    return new SPIDUser(array_merge([
        'fiscalNumber' => ['TINIT-RSSMRA80A01H501U'],
        'name' => ['Mario'],
        'familyName' => ['Rossi'],
        'email' => ['mario.rossi@example.com'],
        'spidCode' => ['SPID123'],
    ], $overrides));
}

function loginEvent(array $overrides = []): LoginEvent
{
    return new LoginEvent(spidUser($overrides), 'Poste ID');
}

beforeEach(function () {
    Model::unguard();
    // Provisioning is opt-in; these tests exercise it with the shipped
    // field_mapping against the stock (password NOT NULL) users table.
    Config::set('filament-spid.auto_create_users', true);
});

it('provisions and authenticates the user on the panel guard', function () {
    app(HandleSpidLogin::class)->handle(loginEvent());

    expect(Auth::guard('web')->check())->toBeTrue()
        ->and(Auth::guard('web')->user()->fiscal_code)->toBe('RSSMRA80A01H501U')
        ->and(User::where('fiscal_code', 'RSSMRA80A01H501U')->count())->toBe(1);
});

it('strips the TINIT prefix from the fiscal number', function () {
    app(HandleSpidLogin::class)->handle(loginEvent());

    expect(User::first()->fiscal_code)->toBe('RSSMRA80A01H501U');
});

it('regenerates the session on login', function () {
    $before = session()->getId();

    app(HandleSpidLogin::class)->handle(loginEvent());

    expect(session()->getId())->not->toBe($before);
});

it('dispatches SpidAuthenticationSucceeded', function () {
    Event::fake([SpidAuthenticationSucceeded::class]);

    app(HandleSpidLogin::class)->handle(loginEvent());

    Event::assertDispatched(SpidAuthenticationSucceeded::class);
});

it('does not set a remember cookie', function () {
    app(HandleSpidLogin::class)->handle(loginEvent());

    expect(Auth::guard('web')->user()->getRememberToken())->toBeEmpty();
});

it('redirects to the panel login page when provisioning returns no user', function () {
    Config::set('filament-spid.auto_create_users', false);

    expect(fn () => app(HandleSpidLogin::class)->handle(loginEvent()))
        ->toThrow(HttpResponseException::class);

    expect(Auth::guard('web')->check())->toBeFalse();
});

it('dispatches SpidAuthenticationFailed when provisioning fails', function () {
    Config::set('filament-spid.auto_create_users', false);
    Event::fake([SpidAuthenticationFailed::class]);

    try {
        app(HandleSpidLogin::class)->handle(loginEvent());
    } catch (HttpResponseException $e) {
        // expected
    }

    Event::assertDispatched(SpidAuthenticationFailed::class);
});

it('carries a flash error on the failure redirect', function () {
    Config::set('filament-spid.auto_create_users', false);

    try {
        app(HandleSpidLogin::class)->handle(loginEvent());
        $response = null;
    } catch (HttpResponseException $e) {
        $response = $e->getResponse();
    }

    expect($response)->not->toBeNull()
        ->and($response->getTargetUrl())->toContain('/admin/login')
        ->and(session()->get('spid_error'))->toBe(__('filament-spid::spid.authentication_failed'));
});

it('clears the SPID session when provisioning fails so the citizen can retry', function () {
    Config::set('filament-spid.auto_create_users', false);
    // What italia/spid-laravel's acs() stores before it fires LoginEvent.
    session([
        'spid_idp' => 'test',
        'spid_idpEntityName' => 'Test IdP',
        'spid_sessionId' => 'ID_1',
        'spid_nameId' => 'NAME_1',
        'spid_user' => spidUser(),
    ]);

    try {
        app(HandleSpidLogin::class)->handle(loginEvent());
    } catch (HttpResponseException) {
        // expected
    }

    expect(app('SPIDAuth')->isAuthenticated())->toBeFalse()
        ->and(session()->only(['spid_idp', 'spid_idpEntityName', 'spid_sessionId', 'spid_nameId', 'spid_user']))->toBe([]);
});

it('refuses the login when the requested SPID level is below the minimum', function () {
    // The library only enforces spid-auth.sp_spid_level on the assertion, so
    // a SpidL1 request lets password-only identities into the panel.
    Config::set('spid-auth.sp_spid_level', 'https://www.spid.gov.it/SpidL1');
    Event::fake([SpidAuthenticationFailed::class]);
    session(['spid_sessionId' => 'ID_1']);

    try {
        app(HandleSpidLogin::class)->handle(loginEvent());
        $response = null;
    } catch (HttpResponseException $e) {
        $response = $e->getResponse();
    }

    expect($response)->not->toBeNull()
        ->and(session()->get('spid_error'))->toBe(__('filament-spid::spid.insufficient_level'))
        ->and(Auth::guard('web')->check())->toBeFalse()
        ->and(User::count())->toBe(0)
        ->and(app('SPIDAuth')->isAuthenticated())->toBeFalse();
    Event::assertDispatched(SpidAuthenticationFailed::class);
});

it('honours a stricter minimum level', function () {
    Config::set('filament-spid.minimum_level', 'https://www.spid.gov.it/SpidL3');

    expect(fn () => app(HandleSpidLogin::class)->handle(loginEvent()))
        ->toThrow(HttpResponseException::class);
    expect(Auth::guard('web')->check())->toBeFalse();
});

it('logs in an existing user without creating a duplicate', function () {
    User::create([
        'name' => 'Mario Rossi',
        'email' => 'mario.rossi@example.com',
        'password' => bcrypt('secret'),
        'fiscal_code' => 'RSSMRA80A01H501U',
    ]);

    app(HandleSpidLogin::class)->handle(loginEvent());

    expect(User::count())->toBe(1)
        ->and(Auth::guard('web')->check())->toBeTrue();
});

it('keeps exception messages out of the log', function () {
    Log::spy();
    Config::set('filament-spid.user_model', 'A\Class\That\Does\Not\Exist');

    try {
        app(HandleSpidLogin::class)->handle(loginEvent());
    } catch (HttpResponseException $e) {
        // expected
    }

    Log::shouldHaveReceived('error')->withArgs(function (string $message) {
        return ! str_contains($message, 'A\Class\That\Does\Not\Exist');
    });
});

it('authenticates against the configured panel', function () {
    Config::set('filament-spid.panel', 'admin');

    app(HandleSpidLogin::class)->handle(loginEvent());

    expect(Auth::guard('web')->check())->toBeTrue();
});

it('refuses to log in when the configured panel is unknown', function () {
    // A typo in FILAMENT_SPID_PANEL must not silently authenticate the
    // citizen on whatever guard happens to be the default.
    Config::set('filament-spid.panel', 'does-not-exist');

    expect(fn () => app(HandleSpidLogin::class)->handle(loginEvent()))
        ->toThrow(SpidPanelNotFoundException::class, 'does-not-exist');

    expect(Auth::guard(config('auth.defaults.guard'))->check())->toBeFalse();
});

it('refuses citizens the panel does not admit', function () {
    // Logging them in would only land them on Filament's 403 with a live
    // SPID session; outside production Filament does not even check.
    Config::set('filament-spid.user_model', PanelUser::class);
    PanelUser::$canAccessPanel = false;
    Event::fake([SpidAuthenticationFailed::class]);
    session(['spid_sessionId' => 'ID_1']);

    try {
        app(HandleSpidLogin::class)->handle(loginEvent());
        $response = null;
    } catch (HttpResponseException $e) {
        $response = $e->getResponse();
    }

    expect($response)->not->toBeNull()
        ->and(session()->get('spid_error'))->toBe(__('filament-spid::spid.access_denied'))
        ->and(Auth::guard('web')->check())->toBeFalse()
        ->and(app('SPIDAuth')->isAuthenticated())->toBeFalse();
    Event::assertDispatched(SpidAuthenticationFailed::class);
});

it('logs in citizens the panel admits', function () {
    Config::set('filament-spid.user_model', PanelUser::class);
    PanelUser::$canAccessPanel = true;

    app(HandleSpidLogin::class)->handle(loginEvent());

    expect(Auth::guard('web')->user())->toBeInstanceOf(PanelUser::class);
});

it('clears the SPID session when the configured panel is unknown', function () {
    // Otherwise, once the config is fixed, doLogin() keeps short-circuiting
    // on the stale spid_sessionId until the session expires.
    Config::set('filament-spid.panel', 'does-not-exist');
    session(['spid_idp' => 'test', 'spid_idpEntityName' => 'Test IdP', 'spid_sessionId' => 'ID_1', 'spid_nameId' => 'NAME_1', 'spid_user' => spidUser()]);

    try {
        app(HandleSpidLogin::class)->handle(loginEvent());
    } catch (SpidPanelNotFoundException) {
        // expected
    }

    expect(app('SPIDAuth')->isAuthenticated())->toBeFalse()
        ->and(session()->only(['spid_idp', 'spid_idpEntityName', 'spid_sessionId', 'spid_nameId', 'spid_user']))->toBe([]);
});

/**
 * Run the listener, swallowing the redirect a refused login throws.
 */
function attemptSpidLogin(): void
{
    try {
        app(HandleSpidLogin::class)->handle(loginEvent());
    } catch (HttpResponseException) {
    }
}

describe('a citizen the panel refuses', function () {
    beforeEach(function () {
        Config::set('filament-spid.user_model', PanelUser::class);
        PanelUser::$canAccessPanel = false;
    });

    afterEach(function () {
        PanelUser::$canAccessPanel = true;
    });

    it('lets a SpidUserProvisioning listener prepare the user before the gate', function () {
        // The role-on-create pattern: a listener grants access to new accounts.
        Event::listen(SpidUserProvisioning::class, function (SpidUserProvisioning $event) {
            if ($event->created) {
                PanelUser::$canAccessPanel = true;
            }
        });

        app(HandleSpidLogin::class)->handle(loginEvent());

        expect(Auth::guard('web')->check())->toBeTrue()
            ->and(User::count())->toBe(1);
    });

    it('gets no account and fires no SpidUserCreated', function () {
        Event::fake([SpidUserCreated::class]);

        attemptSpidLogin();

        expect(User::count())->toBe(0)
            ->and(session()->get('spid_error'))->toBe(__('filament-spid::spid.access_denied'));
        Event::assertNotDispatched(SpidUserCreated::class);
    });

    it('keeps the existing account as it was and fires no SpidUserUpdated', function () {
        User::create([
            'name' => 'Before Login',
            'email' => 'before@example.com',
            'password' => 'x',
            'fiscal_code' => 'RSSMRA80A01H501U',
        ]);
        Event::fake([SpidUserUpdated::class]);

        attemptSpidLogin();

        expect(User::sole())
            ->name->toBe('Before Login')
            ->email->toBe('before@example.com')
            ->spid_data->toBeNull();
        Event::assertNotDispatched(SpidUserUpdated::class);
    });
});

describe('fail-closed authentication', function () {
    /**
     * Run the listener as the ACS request would, with the SPID session the
     * library stores before firing LoginEvent, and return the redirect a
     * refused login ends in (null when the login succeeded).
     */
    function spidAcs(): ?Response
    {
        session(['spid_sessionId' => 'ID_1', 'spid_user' => 'serialized', 'spid_idp' => 'posteid']);

        try {
            app(HandleSpidLogin::class)->handle(loginEvent());
        } catch (HttpResponseException $e) {
            return $e->getResponse();
        }

        return null;
    }

    function expectSpidSessionCleared(): void
    {
        foreach (['spid_sessionId', 'spid_nameId', 'spid_user', 'spid_idp', 'spid_idpEntityName'] as $key) {
            expect(session()->has($key))->toBeFalse("{$key} survived the failed login");
        }
    }

    it('keeps the citizen logged in when every observer succeeds', function () {
        $before = session()->getId();

        expect(spidAcs())->toBeNull()
            ->and(Auth::guard('web')->check())->toBeTrue()
            ->and(session()->getId())->not->toBe($before)
            ->and(session('spid_sessionId'))->toBe('ID_1');
    });

    it('logs the citizen out when a SpidAuthenticationSucceeded listener throws', function () {
        Event::listen(SpidAuthenticationSucceeded::class, fn () => throw new RuntimeException('observer'));

        $response = spidAcs();

        expect($response?->isRedirect())->toBeTrue()
            ->and(session('spid_error'))->toBe(__('filament-spid::spid.acs_error'))
            ->and(Auth::guard('web')->check())->toBeFalse()
            ->and(User::count())->toBe(1);
        expectSpidSessionCleared();
    });

    it('does not let a listener redirect around the failure path', function () {
        // A redirect thrown by an observer is not a successful login.
        Event::listen(SpidAuthenticationSucceeded::class, fn () => throw new HttpResponseException(redirect('/elsewhere')));

        $response = spidAcs();

        expect($response?->headers->get('Location'))->not->toEndWith('/elsewhere')
            ->and(session('spid_error'))->toBe(__('filament-spid::spid.acs_error'))
            ->and(Auth::guard('web')->check())->toBeFalse();
        expectSpidSessionCleared();
    });

    it('logs the citizen out when the guard fails half way through login()', function () {
        // SessionGuard::login() stores the user before firing Login.
        Event::listen(Login::class, fn () => throw new RuntimeException('login observer'));

        spidAcs();

        Auth::forgetGuards();

        expect(Auth::guard('web')->check())->toBeFalse()
            ->and(session('spid_error'))->toBe(__('filament-spid::spid.acs_error'));
        expectSpidSessionCleared();
    });

    it('cleans up before a throwing SpidAuthenticationFailed listener, and reports the original failure once', function () {
        Config::set('filament-spid.auto_create_users', false);
        $dispatched = 0;
        Event::listen(SpidAuthenticationFailed::class, function () use (&$dispatched) {
            $dispatched++;

            throw new RuntimeException('failure observer');
        });

        $response = spidAcs();

        expect($response?->isRedirect())->toBeTrue()
            ->and(session('spid_error'))->toBe(__('filament-spid::spid.authentication_failed'))
            ->and($dispatched)->toBe(1)
            ->and(Auth::guard('web')->check())->toBeFalse();
        expectSpidSessionCleared();
    });

    it('lets the citizen retry after a failed login', function () {
        Event::listen(SpidAuthenticationSucceeded::class, fn () => throw new RuntimeException('observer'));
        spidAcs();
        Event::forget(SpidAuthenticationSucceeded::class);

        expect(spidAcs())->toBeNull()
            ->and(Auth::guard('web')->check())->toBeTrue();
    });

    it('leaves the session unauthenticated for the next request after a failure', function () {
        Event::listen(SpidAuthenticationSucceeded::class, fn () => throw new RuntimeException('observer'));
        spidAcs();
        Auth::forgetGuards();

        $this->get('/admin')->assertRedirect();

        expect(Auth::guard('web')->check())->toBeFalse();
    });

    it('logs a guard that is not a SessionGuard out through logout()', function (bool $throwOnLogout) {
        $guard = new RecordingGuard;
        $guard->throwOnLogout = $throwOnLogout;
        Auth::extend('recording', fn () => $guard);
        Config::set('auth.guards.web', ['driver' => 'recording']);
        Log::spy();
        Event::listen(SpidAuthenticationSucceeded::class, fn () => throw new RuntimeException('observer'));

        $response = spidAcs();

        expect($guard->logouts)->toBe(1)
            ->and($response?->isRedirect())->toBeTrue();
        expectSpidSessionCleared();
        if ($throwOnLogout) {
            Log::shouldHaveReceived('error')->with('SPID login could not log the guard out: RuntimeException');
        }
    })->with(['logout succeeds' => [false], 'logout throws' => [true]]);

    it('does not log out an account authenticated on another guard', function () {
        Config::set('auth.guards.other', ['driver' => 'session', 'provider' => 'users']);
        Config::set('auth.providers.users.model', User::class);
        $other = User::create(['name' => 'Other', 'email' => 'other@example.com', 'password' => 'x', 'fiscal_code' => 'THRGRD80A01H501U']);
        Auth::guard('other')->login($other);
        Event::listen(SpidAuthenticationSucceeded::class, fn () => throw new RuntimeException('observer'));

        spidAcs();
        Auth::forgetGuards();

        expect(Auth::guard('web')->check())->toBeFalse()
            ->and(Auth::guard('other')->id())->toBe($other->id);
    });
});
