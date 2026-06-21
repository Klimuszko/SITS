<?php

namespace App\Notifications;

use App\Models\Setting;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * E-mail powitalny po utworzeniu konta przez admina: informacja, że konto
 * zostało zarejestrowane + link do USTAWIENIA hasła (token jednorazowy, broker
 * „invitations"). ŻADNEGO hasła w treści — użytkownik ustawia je sam.
 *
 * Kto loguje się przez Microsoft/Google — może zignorować link i użyć SSO.
 */
class AccountInvitationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $token) {}

    /** @return array<int,string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $appName = Setting::get('app_name', config('app.name', 'Smart Solutions'));

        $url = route('password.set', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ]);

        return (new MailMessage)
            ->subject('Twoje konto w '.$appName.' zostało utworzone')
            ->greeting('Witaj '.$notifiable->name.'!')
            ->line('Twoje konto w portalu '.$appName.' zostało zarejestrowane.')
            ->action('Ustaw hasło', $url)
            ->line('Jeśli logujesz się przez Microsoft lub Google, możesz użyć tego konta — ustawianie hasła nie jest wtedy potrzebne.')
            ->line('Link do ustawienia hasła wygasa po 7 dniach.');
    }
}
