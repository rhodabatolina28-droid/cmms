<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SystemNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public $recipientName;
    public $notificationType;
    public $notificationMessage;
    public $requestNumber;
    public $branch;
    public $region;
    public $ticketUrl;

    /**
     * Create a new message instance.
     */
    public function __construct($recipientName, $notificationType, $notificationMessage, $requestNumber, $ticketUrl = null, $branch = null, $region = null)
    {
        $this->recipientName = $recipientName;
        $this->notificationType = $notificationType;
        $this->notificationMessage = $notificationMessage;
        $this->requestNumber = $requestNumber;
        $this->ticketUrl = $ticketUrl;
        $this->branch = $branch;
        $this->region = $region;
    }

    /**
     * Build the message.
     */
    public function build()
    {
        return $this->subject("[NCMB CMMS] {$this->notificationType} - #{$this->requestNumber}")
                    ->view('emails.default')
                    ->with([
                        'title' => 'NCMB CMMS Notification',
                        'recipientName' => $this->recipientName,
                        'notificationMessage' => $this->notificationMessage,
                        'requestNumber' => $this->requestNumber,
                        'type' => $this->notificationType,
                        'status' => null,
                        'date' => now()->format('F d, Y'),
                        'ticketUrl' => $this->ticketUrl,
                        'branch' => $this->branch,
                        'region' => $this->region,
                    ]);
    }
}
