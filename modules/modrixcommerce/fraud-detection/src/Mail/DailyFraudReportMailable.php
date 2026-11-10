<?php

namespace EcoModular\FraudDetection\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DailyFraudReportMailable extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array<string, mixed>  $stats
     */
    public function __construct(
        public readonly array $stats,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '📊 Daily Fraud Detection Summary — ' . now()->toFormattedDateString(),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'fraud-detection::emails.daily-report',
        );
    }
}
