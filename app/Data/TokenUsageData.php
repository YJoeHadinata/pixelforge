<?php

declare(strict_types=1);

namespace App\Data;

final readonly class TokenUsageData
{
    public function __construct(
        public int $inputTextTokens,
        public int $inputImageTokens,
        public int $outputTokens,
        public int $reasoningTokens,
        public int $totalTokens,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $inputText = (int) ($data['input_text_tokens'] ?? 0);
        $inputImage = (int) ($data['input_image_tokens'] ?? 0);
        $output = (int) ($data['output_tokens'] ?? 0);
        $reasoning = (int) ($data['reasoning_tokens'] ?? 0);

        return new self(
            inputTextTokens: $inputText,
            inputImageTokens: $inputImage,
            outputTokens: $output,
            reasoningTokens: $reasoning,
            totalTokens: (int) ($data['total_tokens'] ?? ($inputText + $inputImage + $output + $reasoning)),
        );
    }

    /**
     * @return array<string, int>
     */
    public function toArray(): array
    {
        return [
            'input_text_tokens' => $this->inputTextTokens,
            'input_image_tokens' => $this->inputImageTokens,
            'output_tokens' => $this->outputTokens,
            'reasoning_tokens' => $this->reasoningTokens,
            'total_tokens' => $this->totalTokens,
        ];
    }
}
