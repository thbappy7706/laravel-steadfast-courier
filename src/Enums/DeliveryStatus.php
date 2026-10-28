<?php

declare(strict_types=1);

namespace Thbappy7706\SteadFastCourier\Enums;

enum DeliveryStatus: string
{
    case Pending = 'pending';
    case DeliveredApprovalPending = 'delivered_approval_pending';
    case PartialDeliveredApprovalPending = 'partial_delivered_approval_pending';
    case CancelledApprovalPending = 'cancelled_approval_pending';
    case UnknownApprovalPending = 'unknown_approval_pending';
    case Delivered = 'delivered';
    case PartialDelivered = 'partial_delivered';
    case Cancelled = 'cancelled';
    case Hold = 'hold';
    case InReview = 'in_review';
    case Unknown = 'unknown';

    public static function tryFromOrUnknown(string $value): self
    {
        return self::tryFrom($value) ?? self::Unknown;
    }

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::DeliveredApprovalPending => 'Delivered (approval pending)',
            self::PartialDeliveredApprovalPending => 'Partially delivered (approval pending)',
            self::CancelledApprovalPending => 'Cancelled (approval pending)',
            self::UnknownApprovalPending => 'Unknown (approval pending)',
            self::Delivered => 'Delivered',
            self::PartialDelivered => 'Partially delivered',
            self::Cancelled => 'Cancelled',
            self::Hold => 'On hold',
            self::InReview => 'In review',
            self::Unknown => 'Unknown',
        };
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::Delivered, self::PartialDelivered, self::Cancelled], true);
    }

    public function isPendingApproval(): bool
    {
        return in_array($this, [
            self::DeliveredApprovalPending,
            self::PartialDeliveredApprovalPending,
            self::CancelledApprovalPending,
            self::UnknownApprovalPending,
        ], true);
    }

    public function isSuccessful(): bool
    {
        return in_array($this, [self::Delivered, self::PartialDelivered], true);
    }
}
