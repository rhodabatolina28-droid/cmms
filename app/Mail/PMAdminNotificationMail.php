<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PMAdminNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public $adminName;
    public $requestNumber;
    public $scheduleDate;
    public $notificationType;
    public $notificationMessage;
    public $branch;
    public $region;
    public $ticketUrl;

    /**
     * Create a new message instance.
     */
    public function __construct($adminName, $requestNumber, $scheduleDate, $notificationType, $notificationMessage, $ticketUrl = null, $branch = null, $region = null)
    {
        $this->adminName = $adminName;
        $this->requestNumber = $requestNumber;
        $this->scheduleDate = $scheduleDate;
        $this->notificationType = $notificationType;
        $this->notificationMessage = $notificationMessage;
        $this->ticketUrl = $ticketUrl;
        $this->branch = $branch;
        $this->region = $region;
    }

    /**
     * Build the message.
     */
    public function build()
    {
        $date = $this->scheduleDate ? \Carbon\Carbon::parse($this->scheduleDate)->format('F d, Y') : 'TBD';
        $status = 'Assigned';

        return $this->subject("[NCMB CMMS] PM Task Assigned - #{$this->requestNumber}")
                    ->view('emails.default')
                    ->with([
                        'title' => 'PM Task Assigned',
                        'recipientName' => $this->adminName,
                        'notificationMessage' => $this->notificationMessage,
                        'requestNumber' => $this->requestNumber,
                        'type' => $this->notificationType,
                        'status' => $status,
                        'date' => $date,
                        'ticketUrl' => $this->ticketUrl,
                        'branch' => $this->branch,
                        'region' => $this->region,
                    ]);
    }
}