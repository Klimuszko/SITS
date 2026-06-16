<?php

namespace Tests\Feature;

use App\Livewire\Auth\Login;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_credentials_log_the_user_in(): void
    {
        $user = User::factory()->create([
            'email' => 'klient@example.com',
            'password' => Hash::make('tajne-haslo'),
            'is_active' => true,
        ]);

        Livewire::test(Login::class)
            ->set('email', 'klient@example.com')
            ->set('password', 'tajne-haslo')
            ->call('login')
            ->assertHasNoErrors()
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_invalid_password_is_rejected(): void
    {
        User::factory()->create([
            'email' => 'klient@example.com',
            'password' => Hash::make('tajne-haslo'),
        ]);

        Livewire::test(Login::class)
            ->set('email', 'klient@example.com')
            ->set('password', 'zle-haslo')
            ->call('login')
            ->assertHasErrors('email');

        $this->assertGuest();
    }

    public function test_unknown_email_is_rejected(): void
    {
        Livewire::test(Login::class)
            ->set('email', 'nieznany@example.com')
            ->set('password', 'cokolwiek')
            ->call('login')
            ->assertHasErrors('email');

        $this->assertGuest();
    }

    public function test_inactive_account_cannot_log_in(): void
    {
        User::factory()->inactive()->create([
            'email' => 'wylaczony@example.com',
            'password' => Hash::make('tajne-haslo'),
        ]);

        Livewire::test(Login::class)
            ->set('email', 'wylaczony@example.com')
            ->set('password', 'tajne-haslo')
            ->call('login')
            ->assertHasErrors('email');

        $this->assertGuest();
        $this->assertFalse(Auth::check());
    }
}
