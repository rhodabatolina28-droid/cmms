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
        // D9.42 / Phase E2: subject prints the SHORT number like every surface.
        $requestNumber = \App\Models\Request::shortNumber($this->requestNumber);
        // Phase E2: the REAL schedule date — null means "unknown", so the Date
        // row is omitted (the old now() fallback printed TODAY as the PM date).
        $date = $this->scheduleDate ? \Carbon\Carbon::parse($this->scheduleDate)->format('F d, Y') : null;
        $divisionLabel = $this->division ? " in {$this->division}" : '';
        $when = $date ? " on {$date}" : '';
        $message = "PM for your workstation{$divisionLabel} is scheduled{$when}. Coordinate with your ICT Unit for your time slot.";
        $status = 'Scheduled';

        return $this->subject("[NCMB] PM Scheduled · #{$requestNumber}")
                    ->view('emails.default')
                    ->with([
                        'title' => 'PM Scheduled',
                        'recipientName' => $this->recipientName,
                        'notificationMessage' => $message,
                        'requestNumber' => $requestNumber,
                        'type' => 'PM Scheduled',
                        'status' => $status,
                        'date' => $date,
                        'ticketUrl' => $this->ticketUrl,
                        'branch' => $this->branch,
                        'region' => $this->region,
                    ]);
    }
}
