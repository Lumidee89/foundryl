<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WorkspaceInvitation extends Notification
{
    public function __construct(public string $company, public string $acceptUrl) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->subject('Join '.$this->company.' on Foundryl')->line('You have been invited to join '.$this->company.'.')->action('Review invitation', $this->acceptUrl)->line('This invitation expires in 7 days. Foundryl is free to use.');
    }
}
