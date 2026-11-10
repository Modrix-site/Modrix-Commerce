<?php

namespace EcoModular\CartAbandonmentRecovery\Mail;

use App\Core\Settings\StoreSettings;
use EcoModular\CartAbandonmentRecovery\Models\AbandonedCart;
use EcoModular\CartAbandonmentRecovery\Models\AbandonedCartEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CartRecoveryMailable extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly AbandonedCart $abandonedCart,
        public readonly int $step,
        public readonly string $emailSubject,
        public readonly ?string $discountCode = null,
        public readonly ?AbandonedCartEmail $emailRecord = null,
    ) {}

    public function envelope(): Envelope
    {
        $store = app(StoreSettings::class)->toArray();
        $replyTo = $store['store_email'] ? [new Address($store['store_email'], $store['store_name'])] : [];

        return new Envelope(
            subject: $this->emailSubject,
            replyTo: $replyTo,
        );
    }

    public function content(): Content
    {
        $store = app(StoreSettings::class);
        $recoveryUrl = $this->emailRecord !== null
            ? route('modrixcommerce.cart-abandonment-recovery.track.click', ['email' => $this->emailRecord->id])
            : $this->abandonedCart->recoveryUrl();

        $trackingPixelUrl = $this->emailRecord !== null
            ? route('modrixcommerce.cart-abandonment-recovery.track.open', ['email' => $this->emailRecord->id])
            : null;

        return new Content(
            view: 'cart-abandonment-recovery::mail.recovery',
            with: [
                'abandonedCart' => $this->abandonedCart,
                'customerName' => $this->abandonedCart->customer_name,
                'items' => $this->abandonedCart->items_snapshot ?? [],
                'formattedSubtotal' => $this->abandonedCart->formattedSubtotal(),
                'step' => $this->step,
                'subject' => $this->emailSubject,
                'discountCode' => $this->discountCode,
                'recoveryUrl' => $recoveryUrl,
                'unsubscribeUrl' => $this->abandonedCart->unsubscribeUrl(),
                'trackingPixelUrl' => $trackingPixelUrl,
                'storeName' => $store->name(),
            ],
        );
    }
}
