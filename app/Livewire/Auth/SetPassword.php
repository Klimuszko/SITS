<?php

namespace App\Livewire\Auth;

use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Strona „Ustaw hasło" z linku zaproszenia (i ewentualnego resetu). Token
 * jednorazowy z brokera „invitations" (tabela password_reset_tokens, 7 dni).
 * Po ustawieniu hasła użytkownik może logować się e-mailem + hasłem.
 */
#[Layout('layouts.guest')]
#[Title('Ustaw hasło')]
class SetPassword extends Component
{
    public string $token = '';
    public string $email = '';
    public string $password = '';
    public string $password_confirmation = '';

    public function mount(string $token): void
    {
        $this->token = $token;
        $this->email = (string) request()->query('email', '');
    }

    public function save()
    {
        $this->validate([
            'email' => ['required', 'email'],
            'token' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $status = Password::broker('invitations')->reset(
            [
                'email' => $this->email,
                'token' => $this->token,
                'password' => $this->password,
                'password_confirmation' => $this->password_confirmation,
            ],
            function ($user, string $password) {
                // Cast 'hashed' na User::password zahashuje — bez podwójnego hashowania.
                $user->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            },
        );

        if ($status !== Password::PasswordReset) {
            // Token błędny/wygasły/jednorazowy już użyty albo zły e-mail.
            throw ValidationException::withMessages(['email' => __($status)]);
        }

        session()->flash('status', 'Hasło zostało ustawione. Możesz się teraz zalogować.');

        return $this->redirectRoute('login', navigate: false);
    }

    public function render()
    {
        return view('livewire.auth.set-password');
    }
}
