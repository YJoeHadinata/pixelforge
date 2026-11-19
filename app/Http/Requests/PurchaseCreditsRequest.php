<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class PurchaseCreditsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'amount_usd' => ['required', 'string', 'regex:/^\d+(?:\.\d{1,2})?$/', 'numeric', 'min:0.50', 'max:500'],
        ];
    }
}
