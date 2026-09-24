<?php

namespace App\Mail;

use App\Models\Request as ServiceRequest;
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
        // D9.42 P3(vi): the notification row and any queued payload still carry
        // the FULL stored number (ICT-NCR-RCMB-2026-09-24-0001), but the email
        // subject and body must print the short form like every other surface.
        // Normalising here (build time, not the constructor) also covers
        // mailables that were queued BEFORE this change, because build() runs
        // at send time.
        $message = ServiceRequest::shortenNumbersInText($this->notificationMessage);
        $requestNumber = $this->requestNumber === null
            ? null
            : ServiceRequest::shortNumber($this->requestNumber);

        $this->notificationMessage = $message;
        $this->requestNumber = $requestNumber;

        return $this->subject("[NCMB CMMS] {$this->notificationType} - #{$requestNumber}")
                    ->view('emails.default')
                    ->with([
                        'title' => 'NCMB CMMS Notification',
                        'recipientName' => $this->recipientName,
                        'notificationMessage' => $message,
                        'requestNumber' => $requestNumber,
                        'type' => $this->notificationType,
                        'status' => null,
                        'date' => now()->format('F d, Y'),
                        'ticketUrl' => $this->ticketUrl,
                        'branch' => $this->branch,
                        'region' => $this->region,
                    ]);
    }
}
