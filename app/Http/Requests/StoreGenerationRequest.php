<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\ImageQuality;
use App\Enums\ImageSize;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreGenerationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        if (is_array($this->input('reference_images'))) {
            $filtered = array_values(array_filter(
                array_map(static fn (mixed $v): string => trim((string) $v), $this->input('reference_images')),
                static fn (string $v): bool => $v !== '',
            ));

            $this->merge(['reference_images' => $filtered]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'prompt' => ['required', 'string', 'min:1', 'max:4000'],
            'size' => ['required', Rule::enum(ImageSize::class)],
            'quality' => ['required', Rule::enum(ImageQuality::class)],
            'reference_images' => ['nullable', 'array', 'max:4'],
            'reference_images.*' => ['required', 'string', 'url:http,https', 'max:500'],
        ];
    }
}
