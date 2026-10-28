<?php

declare(strict_types=1);

use Thbappy7706\SteadFastCourier\Enums\DeliveryStatus;

it('has the expected backing values', function (): void {
    expect(array_map(
        static fn (DeliveryStatus $status): string => $status->value,
        DeliveryStatus::cases(),
    ))->toBe([
        'pending',
        'delivered_approval_pending',
        'partial_delivered_approval_pending',
        'cancelled_approval_pending',
        'unknown_approval_pending',
        'delivered',
        'partial_delivered',
        'cancelled',
        'hold',
        'in_review',
        'unknown',
    ]);
});

it('provides a human readable label', function (DeliveryStatus $status, string $label): void {
    expect($status->label())->toBe($label);
})->with([
    'pending' => [DeliveryStatus::Pending, 'Pending'],
    'delivered approval pending' => [DeliveryStatus::DeliveredApprovalPending, 'Delivered (approval pending)'],
    'partial delivered approval pending' => [DeliveryStatus::PartialDeliveredApprovalPending, 'Partially delivered (approval pending)'],
    'cancelled approval pending' => [DeliveryStatus::CancelledApprovalPending, 'Cancelled (approval pending)'],
    'unknown approval pending' => [DeliveryStatus::UnknownApprovalPending, 'Unknown (approval pending)'],
    'delivered' => [DeliveryStatus::Delivered, 'Delivered'],
    'partial delivered' => [DeliveryStatus::PartialDelivered, 'Partially delivered'],
    'cancelled' => [DeliveryStatus::Cancelled, 'Cancelled'],
    'hold' => [DeliveryStatus::Hold, 'On hold'],
    'in review' => [DeliveryStatus::InReview, 'In review'],
    'unknown' => [DeliveryStatus::Unknown, 'Unknown'],
]);

it('resolves known values and falls back to unknown', function (string $value, DeliveryStatus $expected): void {
    expect(DeliveryStatus::tryFromOrUnknown($value))->toBe($expected);
})->with([
    'known' => ['delivered', DeliveryStatus::Delivered],
    'known pending' => ['pending', DeliveryStatus::Pending],
    'unknown value' => ['lost', DeliveryStatus::Unknown],
    'empty' => ['', DeliveryStatus::Unknown],
    'different case' => ['Delivered', DeliveryStatus::Unknown],
]);

it('identifies final statuses', function (DeliveryStatus $status, bool $expected): void {
    expect($status->isFinal())->toBe($expected);
})->with([
    [DeliveryStatus::Delivered, true],
    [DeliveryStatus::PartialDelivered, true],
    [DeliveryStatus::Cancelled, true],
    [DeliveryStatus::Pending, false],
    [DeliveryStatus::InReview, false],
    [DeliveryStatus::Hold, false],
    [DeliveryStatus::DeliveredApprovalPending, false],
    [DeliveryStatus::PartialDeliveredApprovalPending, false],
    [DeliveryStatus::CancelledApprovalPending, false],
    [DeliveryStatus::UnknownApprovalPending, false],
    [DeliveryStatus::Unknown, false],
]);

it('identifies statuses that are pending approval', function (DeliveryStatus $status, bool $expected): void {
    expect($status->isPendingApproval())->toBe($expected);
})->with([
    [DeliveryStatus::DeliveredApprovalPending, true],
    [DeliveryStatus::PartialDeliveredApprovalPending, true],
    [DeliveryStatus::CancelledApprovalPending, true],
    [DeliveryStatus::UnknownApprovalPending, true],
    [DeliveryStatus::Delivered, false],
    [DeliveryStatus::PartialDelivered, false],
    [DeliveryStatus::Cancelled, false],
    [DeliveryStatus::Pending, false],
    [DeliveryStatus::InReview, false],
    [DeliveryStatus::Hold, false],
    [DeliveryStatus::Unknown, false],
]);

it('identifies successful statuses', function (DeliveryStatus $status, bool $expected): void {
    expect($status->isSuccessful())->toBe($expected);
})->with([
    [DeliveryStatus::Delivered, true],
    [DeliveryStatus::PartialDelivered, true],
    [DeliveryStatus::Cancelled, false],
    [DeliveryStatus::Pending, false],
    [DeliveryStatus::InReview, false],
    [DeliveryStatus::Hold, false],
    [DeliveryStatus::DeliveredApprovalPending, false],
    [DeliveryStatus::PartialDeliveredApprovalPending, false],
    [DeliveryStatus::CancelledApprovalPending, false],
    [DeliveryStatus::UnknownApprovalPending, false],
    [DeliveryStatus::Unknown, false],
]);
