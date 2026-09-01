<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\PurchaseRequest;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Purchase Request workflow notifications (in-app + email via the existing
 * Notification boot() pipeline). Uses the same recipient-safety rules:
 *  - super_admins receive in-app only (no email flood);
 *  - alias emails are skipped in production;
 *  - local dev writes a readable log preview.
 */
class PurchaseRequestNotificationService
{
    /** Supply users in the given region/branch (same rule as low-stock alerts). */
    public static function supplyUsers(?string $region, ?string $branch): Collection
    {
        return User::query()
            ->where('is_active', true)
            ->where(function ($q) {
                $q->where('role', 'supply_officer')
                    ->orWhere(fn ($q2) => $q2->where('role', 'admin')->where('can_supply', true));
            })
            ->when($region, fn ($q) => $q->where('region', $region))
            ->when($branch, fn ($q) => $q->where('branch', $branch))
            ->get();
    }

    /** PR submitted → notify supply users (skip the creator). */
    public static function notifySubmitted(PurchaseRequest $pr): void
    {
        $creator = $pr->creator;
        $region = $creator?->region;
        $branch = $creator?->branch;

        $message = "{$pr->pr_number} submitted — " . count($pr->items ?? []) . ' item(s), ' .
            ($pr->total_amount !== null ? 'total ₱' . number_format((float) $pr->total_amount, 2) : 'amount TBD') .
            '. Awaiting your review.';

        foreach (self::supplyUsers($region, $branch) as $recipient) {
            if ($creator && $recipient->id === $creator->id) {
                continue; // do not notify the creator of their own submission
            }
            Notification::send($recipient->id, null, 'PR Submitted', $message);
        }
    }

    /** PR finalized → notify requester + creator (dedupe, skip self). */
    public static function notifyFinalized(PurchaseRequest $pr): void
    {
        $message = "{$pr->pr_number} finalized — ready to print and submit to Procurement.";

        $targets = collect([$pr->requested_by, $pr->created_by])
            ->filter()
            ->unique()
            ->values();

        foreach ($targets as $userId) {
            Notification::send($userId, null, 'PR Finalized', $message);
        }
    }

    /** PR delivered → notify requester + creator. */
    public static function notifyDelivered(PurchaseRequest $pr): void
    {
        $message = "{$pr->pr_number} delivered — parts received and recorded.";

        $targets = collect([$pr->requested_by, $pr->created_by])
            ->filter()
            ->unique()
            ->values();

        foreach ($targets as $userId) {
            Notification::send($userId, null, 'PR Delivered', $message);
        }
    }
}