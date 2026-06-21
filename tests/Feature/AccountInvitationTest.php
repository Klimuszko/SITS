<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Livewire\Auth\SetPassword;
use App\Livewire\Users\ManageForm;
use App\Models\User;
use App\Notifications\AccountInvitationNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Zaproszenia po e-mailu (konto bez hasła) + ustawienie hasła z linku (token
 * jednorazowy, broker „invitations"). Hasło nie jest generowane ani wysyłane.
 */
class AccountInvitationTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_user_without_password_sends_invitation(): void
    {
        Notification::fake();
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(ManageForm::class)
            ->set('name', 'Nowy Klient')
            ->set('email', 'nowy@example.com')
            ->set('role', Role::User->value)
            ->set('password', null)
            ->set('password_confirmation', null)
            ->call('save')
            ->assertHasNoErrors();

        $user = User::where('email', 'nowy@example.com')->firstOrFail();

        Notification::assertSentTo($user, AccountInvitationNotification::class);

        // Konto istnieje, ale placeholder hasła jest nieużywalny (nie znamy go).
        $this->assertFalse(Hash::check('', $user->password));
        $this->assertSame(Role::User, $user->role);
    }

    public function test_creating_user_with_password_does_not_send_invitation(): void
    {
        Notification::fake();
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(ManageForm::class)
            ->set('name', 'Z Haslem')
            ->set('email', 'haslo@example.com')
            ->set('role', Role::User->value)
            ->set('password', 'tajneHaslo1')
            ->set('password_confirmation', 'tajneHaslo1')
            ->call('save')
            ->assertHasNoErrors();

        Notification::assertNothingSent();
        $user = User::where('email', 'haslo@example.com')->firstOrFail();
        $this->assertTrue(Hash::check('tajneHaslo1', $user->password));
    }

    public function test_set_password_via_token_sets_password(): void
    {
        // Konto „zaproszone": placeholder hasła + token z brokera invitations.
        $user = User::factory()->create(['password' => Hash::make(Str::password(40))]);
        $token = Password::broker('invitations')->createToken($user);

        Livewire::test(SetPassword::class, ['token' => $token])
            ->set('email', $user->email)
            ->set('password', 'mojeNoweHaslo1')
            ->set('password_confirmation', 'mojeNoweHaslo1')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertTrue(Hash::check('mojeNoweHaslo1', $user->fresh()->password));
    }

    public function test_set_password_rejects_invalid_token(): void
    {
        $user = User::factory()->create();

        Livewire::test(SetPassword::class, ['token' => 'nieprawidlowy-token'])
            ->set('email', $user->email)
            ->set('password', 'mojeNoweHaslo1')
            ->set('password_confirmation', 'mojeNoweHaslo1')
            ->call('save')
            ->assertHasErrors('email');
    }

    public function test_set_password_token_is_single_use(): void
    {
        $user = User::factory()->create(['password' => Hash::make(Str::password(40))]);
        $token = Password::broker('invitations')->createToken($user);

        Livewire::test(SetPassword::class, ['token' => $token])
            ->set('email', $user->email)
            ->set('password', 'pierwszeHaslo1')
            ->set('password_confirmation', 'pierwszeHaslo1')
            ->call('save')
            ->assertHasNoErrors();

        // Ten sam token drugi raz — odrzucony (jednorazowy).
        Livewire::test(SetPassword::class, ['token' => $token])
            ->set('email', $user->email)
            ->set('password', 'drugieHaslo1')
            ->set('password_confirmation', 'drugieHaslo1')
            ->call('save')
            ->assertHasErrors('email');

        $this->assertTrue(Hash::check('pierwszeHaslo1', $user->fresh()->password));
    }
}
