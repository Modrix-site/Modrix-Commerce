<?php

namespace Marketplace\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Marketplace\Models\Order;

/** Receipt with the license key, sent after a paid order. */
class LicenseIssued extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Order $order) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Your license for '.$this->order->package->name);
    }

    public function content(): Content
    {
        return new Content(view: 'mail.license-issued');
    }
}
