<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PMScheduledMail extends Mailable
{
    use Queueable, SerializesModels;

    public $recipientName;
    public $requestNumber;
    public $division;
    public $scheduleDate;
    public $branch;
    public $region;
    public $ticketUrl;

    /**
     * Create a new message instance.
     */
    public function __construct($recipientName, $requestNumber, $division = null, $scheduleDate = null, $ticketUrl = null, $branch = null, $region = null)
    {
        $this->recipientName = $recipientName;
        $this->requestNumber = $requestNumber;
        $this->division = $division;
        $this->scheduleDate = $scheduleDate;
        $this->ticketUrl = $ticketUrl;
        $this->branch = $branch;
        $this->region = $region;
    }

    /**
     * Build the message.
     */
    public function build()
    {
        $date = $this->scheduleDate ? \Carbon\Carbon::parse($this->scheduleDate)->format('F d, Y') : now()->format('F d, Y');
        $message = "A workstation preventive maintenance (PM) has been scheduled for your equipment in {$this->division}. Please coordinate with your ICT Unit for your schedule.";
        $status = 'Scheduled';

        return $this->subject("[NCMB CMMS] PM Scheduled - #{$this->requestNumber}")
                    ->view('emails.default')
                    ->with([
                        'title' => 'PM Scheduled',
                        'recipientName' => $this->recipientName,
                        'notificationMessage' => $message,
                        'requestNumber' => $this->requestNumber,
                        'type' => 'PM Scheduled',
                        'status' => $status,
                        'date' => $date,
                        'ticketUrl' => $this->ticketUrl,
                        'branch' => $this->branch,
                        'region' => $this->region,
                    ]);
    }
}
