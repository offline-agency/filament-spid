<?php

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use OfflineAgency\FilamentSpid\DTOs\SpidUserData;
use OfflineAgency\FilamentSpid\Events\SpidUserCreated;
use OfflineAgency\FilamentSpid\Events\SpidUserProvisioning;
use OfflineAgency\FilamentSpid\Events\SpidUserUpdated;
use OfflineAgency\FilamentSpid\Exceptions\SpidAccessDeniedException;
use OfflineAgency\FilamentSpid\Services\SpidUserService;
use OfflineAgency\FilamentSpid\Tests\Fixtures\SecondaryUser;
use OfflineAgency\FilamentSpid\Tests\Fixtures\User;

function transactionSpidData(string $name = 'Mario'): SpidUserData
{
    return new SpidUserData(fiscalNumber: 'TRNSCT80A01H501U', name: $name, familyName: 'Rossi');
}

beforeEach(function () {
    Config::set('filament-spid.auto_create_users', true);
});

describe('a user model on another connection', function () {
    beforeEach(function () {
        Config::set('filament-spid.user_model', SecondaryUser::class);
    });

    it('rolls a refused new account back on that connection', function () {
        expect(fn () => app(SpidUserService::class)->findOrCreateUser(transactionSpidData(), fn () => false))
            ->toThrow(SpidAccessDeniedException::class);

        expect(SecondaryUser::count())->toBe(0);
    });

    it('rolls a refused update back on that connection', function () {
        $service = app(SpidUserService::class);
        $service->findOrCreateUser(transactionSpidData('Before'));

        expect(fn () => $service->findOrCreateUser(transactionSpidData('After'), fn () => false))
            ->toThrow(SpidAccessDeniedException::class);

        expect(SecondaryUser::sole()->name)->toBe('Before Rossi');
    });

    it('rolls back when provisioning throws', function () {
        Event::listen(SpidUserProvisioning::class, fn () => throw new RuntimeException('provisioning'));

        expect(fn () => app(SpidUserService::class)->findOrCreateUser(transactionSpidData()))
            ->toThrow(RuntimeException::class, 'provisioning');

        expect(SecondaryUser::count())->toBe(0);
    });

    it('fires SpidUserCreated after that connection commits', function () {
        $seen = [];
        Event::listen(SpidUserCreated::class, function () use (&$seen) {
            $seen[] = DB::connection('secondary')->transactionLevel();
        });

        app(SpidUserService::class)->findOrCreateUser(transactionSpidData());

        expect($seen)->toBe([0]);
    });
});

describe('inside an outer transaction', function () {
    it('fires no user event when the outer transaction rolls back', function (bool $existing, string $eventClass) {
        $service = app(SpidUserService::class);
        if ($existing) {
            $service->findOrCreateUser(transactionSpidData());
        }
        Event::fake([$eventClass]);

        try {
            DB::transaction(function () use ($service) {
                $service->findOrCreateUser(transactionSpidData('Inner'));

                throw new RuntimeException('outer');
            });
        } catch (RuntimeException) {
        }

        Event::assertNotDispatched($eventClass);
        expect(User::count())->toBe($existing ? 1 : 0);
    })->with([
        'created' => [false, SpidUserCreated::class],
        'updated' => [true, SpidUserUpdated::class],
    ]);

    it('fires the user event once the outer transaction commits', function () {
        $levels = [];
        Event::listen(SpidUserCreated::class, function () use (&$levels) {
            $levels[] = DB::transactionLevel();
        });

        DB::transaction(function () use (&$levels) {
            app(SpidUserService::class)->findOrCreateUser(transactionSpidData());
            $levels[] = 'inner returned';
        });

        expect($levels)->toBe(['inner returned', 0]);
    });
});
