<?php

declare(strict_types=1);

namespace App\Services\Pixelforge;

use App\Services\Pixelforge\Exceptions\PixelforgeSignatureException;

final class PixelforgeSignatureVerifier
{
    public function verify(
        string $rawPayload,
        ?string $signatureHeader,
        ?string $secret = null,
        ?int $toleranceSeconds = null,
        ?int $nowTimestamp = null,
    ): void {
        $signingSecret = $secret ?? (string) config('services.pixelforge.webhook_secret', '');
        if ($signingSecret === '') {
            throw new PixelforgeSignatureException('Pixelforge webhook secret is not configured.');
        }

        if ($signatureHeader === null || trim($signatureHeader) === '') {
            throw new PixelforgeSignatureException('Missing Pixelforge-Signature header.');
        }

        $parsed = $this->parseHeader($signatureHeader);
        $timestampRaw = $parsed['t'] ?? null;
        $providedSig = $parsed['v1'] ?? null;

        if ($timestampRaw === null || ! ctype_digit($timestampRaw) || $providedSig === null || $providedSig === '') {
            throw new PixelforgeSignatureException('Malformed Pixelforge-Signature header.');
        }

        $timestamp = (int) $timestampRaw;
        $current = $nowTimestamp ?? time();
        $tolerance = $toleranceSeconds ?? (int) config('services.pixelforge.webhook_tolerance_seconds', 300);

        if (abs($current - $timestamp) > $tolerance) {
            throw new PixelforgeSignatureException('Pixelforge webhook timestamp is outside the allowed tolerance window.');
        }

        $expectedSig = hash_hmac('sha256', "{$timestamp}.{$rawPayload}", $signingSecret);

        if (! hash_equals($expectedSig, $providedSig)) {
            throw new PixelforgeSignatureException('Invalid Pixelforge webhook signature.');
        }
    }

    /**
     * @return array<string, string>
     */
    private function parseHeader(string $header): array
    {
        $parts = [];
        foreach (explode(',', $header) as $segment) {
            [$key, $value] = array_pad(explode('=', trim($segment), 2), 2, '');
            if ($key !== '') {
                $parts[$key] = $value;
            }
        }

        return $parts;
    }
}
