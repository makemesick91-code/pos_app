<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Sprint 24 — validates a subscription dunning notice transition (prepare /
 * mark-sent-manually / complete / cancel / skip). Marking sent-manually records
 * an external manual action only — no real message is sent.
 */
class TransitionSubscriptionDunningNoticeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
