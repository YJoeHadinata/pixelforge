<?php

declare(strict_types=1);

namespace App\Data;

final readonly class TokenRatesData
{
    public function __construct(
        public string $inputTextTokens,
        public string $inputImageTokens,
        public string $outputTokens,
        public string $reasoningTokens,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            inputTextTokens: (string) ($data['input_text_tokens'] ?? '0.00'),
            inputImageTokens: (string) ($data['input_image_tokens'] ?? '0.00'),
            outputTokens: (string) ($data['output_tokens'] ?? '0.00'),
            reasoningTokens: (string) ($data['reasoning_tokens'] ?? '0.00'),
        );
    }

    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return [
            'input_text_tokens' => $this->inputTextTokens,
            'input_image_tokens' => $this->inputImageTokens,
            'output_tokens' => $this->outputTokens,
            'reasoning_tokens' => $this->reasoningTokens,
        ];
    }
}
