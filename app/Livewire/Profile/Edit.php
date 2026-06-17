<?php

namespace App\Livewire\Profile;

use App\Enums\AuditAction;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Mój profil')]
class Edit extends Component
{
    // Pola profilu (edytowalne przez właściciela konta).
    public string $name = '';
    public string $email = '';
    public ?string $phone = null;

    // Zmiana hasła (opcjonalna). currentPassword wymagane tylko gdy podano nowe hasło.
    public ?string $currentPassword = null;
    public ?string $newPassword = null;
    public ?string $newPassword_confirmation = null;

    public function mount(): void
    {
        $user = auth()->user();

        $this->name = $user->name;
        $this->email = $user->email;
        $this->phone = $user->phone;
    }

    /** @return array<string,mixed> */
    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required', 'email', 'max:255',
                Rule::unique('users', 'email')->ignore(auth()->id()),
            ],
            'phone' => ['nullable', 'string', 'max:50'],
            // Bieżące hasło wymagane tylko przy zmianie hasła; reguła current_password
            // weryfikuje je względem hasła zalogowanego użytkownika.
            'currentPassword' => ['nullable', 'required_with:newPassword', 'current_password'],
            // Nowe hasło opcjonalne; puste = bez zmiany. confirmed → newPassword_confirmation.
            'newPassword' => ['nullable', 'string', 'min:8', 'confirmed'],
        ];
    }

    /** @return array<string,string> */
    protected function messages(): array
    {
        return [
            'email.unique' => 'Ten adres e-mail jest już zajęty.',
            'currentPassword.required_with' => 'Podaj aktualne hasło, aby je zmienić.',
            'currentPassword.current_password' => 'Aktualne hasło jest nieprawidłowe.',
        ];
    }

    public function save(): void
    {
        $data = $this->validate();

        /** @var User $user */
        $user = auth()->user();

        DB::transaction(function () use ($data, $user) {
            $old = $user->getOriginal();

            $user->name = $data['name'];
            $user->email = $data['email'];
            $user->phone = $data['phone'];

            // Hasło ustawiamy tylko gdy podano nowe (cast 'hashed' zahashuje raz — bez podwójnego hashowania).
            if (filled($data['newPassword'])) {
                $user->password = $data['newPassword'];
            }

            $user->save();

            // Audyt bez pól wrażliwych (hash hasła, remember_token nigdy nie trafiają do logu).
            $tracked = Arr::except($user->getChanges(), ['password', 'remember_token', 'updated_at']);
            if (filled($data['newPassword'])) {
                $tracked['password_changed'] = true;
            }
            AuditLogger::log(
                AuditAction::UserUpdated,
                $user,
                Arr::only($old, array_keys(Arr::except($tracked, ['password_changed']))) ?: null,
                $tracked ?: null,
            );
        });

        // Reset pól hasła po zapisie; pozostałe pola odświeżamy ze świeżego stanu.
        $this->reset(['currentPassword', 'newPassword', 'newPassword_confirmation']);
        $this->name = $user->name;
        $this->email = $user->email;
        $this->phone = $user->phone;

        session()->flash('status', 'Zapisano profil.');
    }

    public function render()
    {
        return view('livewire.profile.edit', [
            'memberships' => auth()->user()->memberships()->with('organization')->get(),
        ]);
    }
}
