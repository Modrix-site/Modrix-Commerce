<?php

namespace App\Domains\Marketplace;

use App\Core\Audit\AuditLogger;
use App\Domains\Marketplace\Models\MarketplaceProduct;
use App\Domains\Marketplace\Models\Publisher;
use App\Domains\Marketplace\Models\PublisherMember;
use App\Domains\Marketplace\Models\SellerApplication;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Seller onboarding and administration (marketplace server). Every seller
 * is verified by the platform owner before it can publish; its packages
 * must use its vendor slug, so it can never ship under another vendor's name.
 */
final class SellerAccounts
{
    /** Vendor prefixes no seller may claim. */
    public const RESERVED_SLUGS = ['ecomodular', 'eleydo', 'laravel', 'illuminate', 'app', 'core', 'admin', 'official', 'marketplace', 'vendor'];

    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array{publisher_name: string, vendor_slug: string, legal_name: string, country: string, website?: string|null, payout_email: string, support_email: string, description: string}  $data
     *
     * @throws ValidationException
     */
    public function apply(User $user, array $data): SellerApplication
    {
        if (PublisherMember::query()->where('user_id', $user->id)->exists()) {
            throw ValidationException::withMessages(['publisher_name' => 'This account already belongs to a seller.']);
        }

        if (SellerApplication::query()->where('user_id', $user->id)->where('status', SellerApplication::PENDING)->exists()) {
            throw ValidationException::withMessages(['publisher_name' => 'Your application is already waiting for review.']);
        }

        $this->ensureSlugAvailable($data['vendor_slug']);

        $application = new SellerApplication;
        $application->forceFill([...$data, 'user_id' => $user->id, 'status' => SellerApplication::PENDING])->save();
        $this->audit->record('marketplace.seller_applied', null, ['application' => $application->id, 'vendor' => $application->vendor_slug], $user);

        return $application;
    }

    /**
     * @throws ValidationException
     */
    public function approve(SellerApplication $application, string $commissionPercent, ?string $notes, User $actor): Publisher
    {
        if ($application->status !== SellerApplication::PENDING) {
            throw ValidationException::withMessages(['application' => 'This application was already reviewed.']);
        }

        return DB::transaction(function () use ($application, $commissionPercent, $notes, $actor) {
            $this->ensureSlugAvailable($application->vendor_slug);

            if (PublisherMember::query()->where('user_id', $application->user_id)->exists()) {
                throw ValidationException::withMessages(['application' => 'The applicant already belongs to a seller.']);
            }

            $publisher = new Publisher;
            $publisher->forceFill([
                'name' => $application->publisher_name,
                'slug' => $application->vendor_slug,
                'is_platform' => false,
                'status' => Publisher::ACTIVE,
                'commission_percent' => $commissionPercent,
                'legal_name' => $application->legal_name,
                'country' => $application->country,
                'website' => $application->website,
                'payout_email' => $application->payout_email,
                'support_email' => $application->support_email,
                'verified_at' => now(),
            ])->save();

            $member = new PublisherMember;
            $member->forceFill(['publisher_id' => $publisher->id, 'user_id' => $application->user_id, 'role' => 'owner'])->save();

            $application->forceFill([
                'status' => SellerApplication::APPROVED,
                'review_notes' => $notes,
                'reviewed_by' => $actor->id,
                'reviewed_at' => now(),
                'publisher_id' => $publisher->id,
            ])->save();

            $this->audit->record('marketplace.seller_approved', null, ['publisher' => $publisher->slug, 'commission_percent' => $commissionPercent], $actor);

            return $publisher;
        });
    }

    public function reject(SellerApplication $application, string $notes, User $actor): void
    {
        if ($application->status !== SellerApplication::PENDING) {
            throw ValidationException::withMessages(['application' => 'This application was already reviewed.']);
        }

        $application->forceFill(['status' => SellerApplication::REJECTED, 'review_notes' => $notes, 'reviewed_by' => $actor->id, 'reviewed_at' => now()])->save();
        $this->audit->record('marketplace.seller_rejected', null, ['application' => $application->id], $actor);
    }

    /**
     * Suspending hides the seller's products and blocks new sales and
     * uploads; existing licenses keep working and the balance stays owed.
     */
    public function setStatus(Publisher $publisher, string $status, string $reason, User $actor): void
    {
        if ($publisher->is_platform) {
            throw ValidationException::withMessages(['status' => 'The platform publisher cannot be suspended.']);
        }

        $publisher->forceFill(['status' => $status])->save();
        $this->audit->record('marketplace.seller_status', null, ['publisher' => $publisher->slug, 'status' => $status, 'reason' => $reason], $actor);
    }

    public function setCommission(Publisher $publisher, string $percent, User $actor): void
    {
        $old = $publisher->commission_percent;
        $publisher->forceFill(['commission_percent' => $percent])->save();
        $this->audit->record('marketplace.seller_commission', null, ['publisher' => $publisher->slug, 'from' => $old, 'to' => $percent], $actor);
    }

    /**
     * @throws ValidationException
     */
    private function ensureSlugAvailable(string $slug): void
    {
        $taken = in_array($slug, self::RESERVED_SLUGS, true)
            || Publisher::query()->where('slug', $slug)->exists()
            || MarketplaceProduct::withTrashed()->where('extension_name', 'like', $slug.'/%')->exists();

        if ($taken) {
            throw ValidationException::withMessages(['vendor_slug' => 'This vendor name is reserved or already used.']);
        }
    }
}
