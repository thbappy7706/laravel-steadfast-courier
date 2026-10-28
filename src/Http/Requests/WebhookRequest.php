<?php

declare(strict_types=1);

namespace Thbappy7706\SteadFastCourier\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Thbappy7706\SteadFastCourier\Data\WebhookPayloadData;
use Thbappy7706\SteadFastCourier\Exceptions\InvalidWebhookException;

final class WebhookRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'consignment_id' => ['required', 'regex:/^\d+$/'],
            'invoice' => ['required', 'string'],
            'status' => ['required', 'string'],
            'cod_amount' => ['required', 'numeric'],
            'updated_at' => ['required', 'date'],
            'notification_type' => ['nullable', 'string'],
        ];
    }

    /**
     * @throws InvalidWebhookException
     */
    public function toData(): WebhookPayloadData
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return WebhookPayloadData::fromArray($validated);
    }
}
