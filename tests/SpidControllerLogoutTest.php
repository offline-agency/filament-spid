<?php

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Italia\SPIDAuth\Exceptions\SPIDLogoutException;
use Italia\SPIDAuth\SPIDAuth;
use Italia\SPIDAuth\SPIDUser;
use Mockery as m;
use OfflineAgency\FilamentSpid\Http\Controllers\SpidController;
use OfflineAgency\FilamentSpid\Tests\Fixtures\User;

/**
 * What italia/spid-laravel's acs() leaves in the session after a SPID login.
 */
function spidSession(): array
{
    return [
        'spid_idp' => 'test',
        'spid_idpEntityName' => 'Test IdP',
        'spid_sessionId' => 'ID_1',
        'spid_nameId' => 'NAME_1',
        'spid_user' => new SPIDUser(['fiscalNumber' => ['TINIT-RSSMRA80A01H501U']]),
    ];
}

function panelUser(): User
{
    return User::create([
        'name' => 'Mario Rossi',
        'email' => 'mario.rossi@example.com',
        'password' => bcrypt('secret'),
        'fiscal_code' => 'RSSMRA80A01H501U',
    ]);
}

beforeEach(function () {
    Model::unguard();
    $this->app['router']->middleware('web')->get('/spid-test/logout', [SpidController::class, 'logout']);
});

it('hands a SPID session to the IdP for single logout', function () {
    // php-saml sends the SLO redirect with header() and exit(), so the SAML
    // round trip itself is the one thing mocked here.
    $spid = m::mock(SPIDAuth::class);
    $spid->shouldReceive('isAuthenticated')->andReturnTrue();
    $spid->shouldReceive('logout')->once()->andReturn(redirect('https://spid-testenv/slo?SAMLRequest=abc'));
    $this->app->instance(SPIDAuth::class, $spid);

    $this->actingAs(panelUser())
        ->withSession(spidSession())
        ->get('/spid-test/logout')
        ->assertRedirect('https://spid-testenv/slo?SAMLRequest=abc');
});

it('tears the panel session down through the library when only the SP logs out', function () {
    config()->set('spid-auth.only_sp_logout', true);
    config()->set('spid-auth.after_logout_url', '/goodbye');

    $this->actingAs(panelUser())
        ->withSession(spidSession())
        ->get('/spid-test/logout')
        ->assertRedirect('/goodbye');

    expect(Auth::guard('web')->check())->toBeFalse()
        ->and(app('SPIDAuth')->isAuthenticated())->toBeFalse();
});

it('logs a session without SPID out of the panel guard', function () {
    $this->actingAs(panelUser())
        ->withSession(['marker' => 'present'])
        ->get('/spid-test/logout')
        ->assertRedirect(route('filament.admin.auth.login'));

    expect(Auth::guard('web')->check())->toBeFalse()
        ->and(session()->has('marker'))->toBeFalse();
});

it('still ends the local session when the IdP logout fails', function () {
    // Real singleton: an IdP without a single logout endpoint makes php-saml
    // throw before redirecting, which the library wraps in SPIDLogoutException.
    config()->set('spid-auth.test_idp.slo_endpoint', '');
    Log::spy();

    $this->actingAs(panelUser())
        ->withSession(spidSession())
        ->get('/spid-test/logout')
        ->assertRedirect(route('filament.admin.auth.login'));

    expect(Auth::guard('web')->check())->toBeFalse()
        ->and(session()->has('spid_sessionId'))->toBeFalse();
    Log::shouldHaveReceived('error')->withArgs(fn (string $message) => str_contains($message, SPIDLogoutException::class));
});
