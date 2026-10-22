<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AccountLink extends Notification
{
    public function __construct(private string $mailer, #[\SensitiveParameter] private ?string $resetToken = null) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $notification = $this->resetToken === null ? new VerifyEmail : new ResetPassword($this->resetToken);

        return $notification->toMail($notifiable)->mailer($this->mailer);
    }
}
