<?php

use App\Domain\Accounts\Enums\UserRole;
use App\Models\User;
use Spatie\Permission\Models\Role;

it('tworzy role idempotentnie', function () {
    UserRole::ensureExist();
    UserRole::ensureExist();

    expect(Role::count())->toBe(4);
});

it('wymaga 2FA dla admina i fachowca, ale nie dla właściciela', function () {
    expect(User::factory()->admin()->create()->requiresTwoFactor())->toBeTrue()
        ->and(User::factory()->specialist()->create()->requiresTwoFactor())->toBeTrue()
        ->and(User::factory()->owner()->create()->requiresTwoFactor())->toBeFalse();
});

it('wybiera rolę główną wg priorytetu', function () {
    $user = User::factory()->owner()->specialist()->create();

    expect($user->primaryRole())->toBe(UserRole::Specialist);
});

it('nie pozwala ustawić pól bezpieczeństwa przez masowe przypisanie', function () {
    $user = new User;
    $user->fill(['banned_at' => now(), 'two_factor_secret' => 'x', 'email_verified_at' => now()]);

    expect($user->banned_at)->toBeNull()
        ->and($user->two_factor_secret)->toBeNull()
        ->and($user->email_verified_at)->toBeNull();
});
