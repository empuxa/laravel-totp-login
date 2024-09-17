<?php

namespace Empuxa\TotpLogin\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * @todo test
 */
class LoginCode extends Notification
{
    public function __construct(protected readonly string $code, protected readonly string $ip) {}

    /**
     * @param  array<string>  $notifiable
     *
     * @phpcsSuppress SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function via(mixed $notifiable): array
    {
        return ['mail'];
    }

    /**
     * This method is intended to implement your own logic to determine the user's timezone.
     * Adjust this to avoid the user receiving wrong timestamps or adjust the texts to be less accurate.
     */
    public static function getUserTimeZone(mixed $notifiable): string
    {
        return $notifiable->timezone ?? $notifiable->tz ?? config('app.timezone', 'UTC');
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        $params = [
            'app'         => config('app.name'),
            'name'        => $notifiable->name,
            'code'        => $this->code,
            'ip'          => $this->ip,
            'valid_until' => $notifiable->{config('totp-login.columns.code_valid_until')}
                ?->tz(self::getUserTimeZone($notifiable)),
        ];

        return (new MailMessage)
            ->subject(__('totp-login::notification.mail.subject', $params))
            ->greeting(__('totp-login::notification.mail.greeting', $params))
            ->line(__('totp-login::notification.mail.line-1', $params))
            ->line(__('totp-login::notification.mail.line-2', $params))
            ->line(__('totp-login::notification.mail.line-3', $params))
            ->action(
                __('totp-login::notification.mail.cta', $params),
                route('totp-login.code.form'),
            )
            ->markdown('totp-login::notification', [
                'notifiable' => $notifiable,
                'code'       => str_split($this->code),
            ]);
    }
}
