<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Livewire\Profile\Edit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class ProfileEditTest extends TestCase
{
    use RefreshDatabase;

    /** Aktor = zwykły użytkownik (klient), którego znamy hasło ('password' z fabryki). */
    private function actAsUser(array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $this->actingAs($user);

        return $user;
    }

    public function test_user_updates_name_email_and_phone(): void
    {
        $user = $this->actAsUser(['email' => 'stary@example.com', 'phone' => null]);

        Livewire::test(Edit::class)
            ->set('name', 'Nowe Imię')
            ->set('email', 'nowy@example.com')
            ->set('phone', '+48 600 100 200')
            ->call('save')
            ->assertHasNoErrors();

        $user->refresh();
        $this->assertSame('Nowe Imię', $user->name);
        $this->assertSame('nowy@example.com', $user->email);
        $this->assertSame('+48 600 100 200', $user->phone);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'user.updated',
            'subject_type' => $user->getMorphClass(),
            'subject_id' => $user->id,
        ]);
    }

    public function test_email_uniqueness_blocks_other_user_but_allows_keeping_own(): void
    {
        $this->actAsUser(['email' => 'self@example.com']);
        User::factory()->create(['email' => 'zajety@example.com']);

        // Cudzy e-mail → błąd.
        Livewire::test(Edit::class)
            ->set('email', 'zajety@example.com')
            ->call('save')
            ->assertHasErrors(['email']);

        // Własny (niezmieniony) e-mail → bez błędu uniqueness.
        Livewire::test(Edit::class)
            ->set('name', 'Zmieniona Nazwa')
            ->set('email', 'self@example.com')
            ->call('save')
            ->assertHasNoErrors();
    }

    public function test_wrong_current_password_is_rejected_and_password_unchanged(): void
    {
        $user = $this->actAsUser();
        $originalHash = $user->password;

        Livewire::test(Edit::class)
            ->set('currentPassword', 'zleHaslo')
            ->set('newPassword', 'noweHaslo1')
            ->set('newPassword_confirmation', 'noweHaslo1')
            ->call('save')
            ->assertHasErrors(['currentPassword']);

        $user->refresh();
        $this->assertSame($originalHash, $user->password);
        $this->assertTrue(Hash::check('password', $user->password));
    }

    public function test_correct_current_password_changes_password(): void
    {
        $user = $this->actAsUser();

        Livewire::test(Edit::class)
            ->set('currentPassword', 'password')
            ->set('newPassword', 'noweHaslo1')
            ->set('newPassword_confirmation', 'noweHaslo1')
            ->call('save')
            ->assertHasNoErrors();

        $user->refresh();
        $this->assertTrue(Hash::check('noweHaslo1', $user->password));
        $this->assertFalse(Hash::check('password', $user->password));
    }

    public function test_blank_password_fields_leave_password_unchanged(): void
    {
        $user = $this->actAsUser();
        $originalHash = $user->password;

        Livewire::test(Edit::class)
            ->set('name', 'Inna Nazwa')
            ->set('currentPassword', null)
            ->set('newPassword', null)
            ->set('newPassword_confirmation', null)
            ->call('save')
            ->assertHasNoErrors();

        $user->refresh();
        $this->assertSame('Inna Nazwa', $user->name);
        $this->assertSame($originalHash, $user->password);
        $this->assertTrue(Hash::check('password', $user->password));
    }

    public function test_component_cannot_change_role_or_active_flag(): void
    {
        $user = $this->actAsUser(['role' => Role::User, 'is_active' => true]);

        // Komponent nie deklaruje tych właściwości — próba ustawienia ich nie istniejących
        // pól nie może zmienić konta. Sprawdzamy stan po zwykłym zapisie.
        Livewire::test(Edit::class)
            ->set('name', 'Bez Eskalacji')
            ->call('save')
            ->assertHasNoErrors();

        $user->refresh();
        $this->assertSame(Role::User, $user->role);
        $this->assertTrue($user->is_active);
        $this->assertSame('Bez Eskalacji', $user->name);
    }
}
