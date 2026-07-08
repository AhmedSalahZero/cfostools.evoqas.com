<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword as ResetPasswordBase;
use Illuminate\Notifications\Messages\MailMessage;

class ResetPasswordNotification extends ResetPasswordBase
{
    /**
     * Build the mail representation using a custom Blade template.
     * Reset URL uses query params: /reset-password?token=...&email=...
     */
    public function toMail($notifiable): MailMessage
    {
        $resetUrl = url(route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ], false));

        return (new MailMessage)
            ->subject(__('Reset your password'))
            ->view('emails.auth.reset-password', [
                'user'     => $notifiable,
                'resetUrl' => $resetUrl,
                'appName'  => config('app.name'),
            ]);
    }
}
