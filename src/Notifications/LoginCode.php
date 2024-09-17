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

    public function toMail(mixed $notifiable): MailMessage
    {
        $params = [
            'app'         => config('app.name'),
            'name'        => $notifiable->name,
            'valid_until' => $notifiable->{config('totp-login.columns.code_valid_until')},
            'code'        => $this->code,
            'ip'          => $this->ip,
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
