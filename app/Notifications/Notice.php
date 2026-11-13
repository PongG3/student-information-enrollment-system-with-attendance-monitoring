<?php

namespace App\Notifications;

use App\Support\SchoolBranding;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** One plain email: a subject, a few lines, and an optional button back into the portal. */
class Notice extends Notification
{
    /**
     * @param  string[]  $lines
     */
    public function __construct(
        public string $subject,
        public array $lines,
        public ?string $actionText = null,
        public ?string $actionUrl = null,
        public ?string $greeting = null,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $school = SchoolBranding::hasRealName() ? SchoolBranding::name() : 'SIES Campus Portal';
        $mail = (new MailMessage())
            ->subject($this->subject . ' · ' . $school)
            ->greeting($this->greeting ?? 'Hello,');

        foreach ($this->lines as $line) {
            $mail->line($line);
        }
        if ($this->actionText && $this->actionUrl) {
            $mail->action($this->actionText, $this->actionUrl);
        }

        return $mail->salutation('— ' . $school . ' (sent automatically; please do not reply)');
    }
}
