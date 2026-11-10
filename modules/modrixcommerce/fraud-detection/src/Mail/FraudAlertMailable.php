<?php

namespace EcoModular\FraudDetection\Mail;

use EcoModular\FraudDetection\Models\FraudScore;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class FraudAlertMailable extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly FraudScore $fraudScore,
    ) {}

    public function envelope(): Envelope
    {
        $orderNumber = $this->fraudScore->order?->number ?? '#' . $this->fraudScore->order_id;

        return new Envelope(
            subject: "⚠️ High-Risk Order Detected: {$orderNumber} (Score: {$this->fraudScore->total_score}/100)",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'fraud-detection::emails.fraud-alert',
        );
    }
}
