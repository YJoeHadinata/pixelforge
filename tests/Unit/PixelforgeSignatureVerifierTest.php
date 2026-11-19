<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Pixelforge\Exceptions\PixelforgeSignatureException;
use App\Services\Pixelforge\PixelforgeSignatureVerifier;
use PHPUnit\Framework\TestCase;

final class PixelforgeSignatureVerifierTest extends TestCase
{
    public function test_accepts_valid_hmac_signature_within_tolerance(): void
    {
        $verifier = new PixelforgeSignatureVerifier;
        $secret = 'whsec_test_secret';
        $payload = '{"id":"evt_123","type":"ping"}';
        $timestamp = 1_762_265_009;
        $sig = hash_hmac('sha256', "{$timestamp}.{$payload}", $secret);

        $verifier->verify(
            rawPayload: $payload,
            signatureHeader: "t={$timestamp},v1={$sig}",
            secret: $secret,
            toleranceSeconds: 300,
            nowTimestamp: $timestamp + 60,
        );

        $this->addToAssertionCount(1);
    }

    public function test_rejects_tampered_payload(): void
    {
        $verifier = new PixelforgeSignatureVerifier;
        $secret = 'whsec_test_secret';
        $timestamp = 1_762_265_009;
        $sig = hash_hmac('sha256', "{$timestamp}.{\"id\":\"evt_123\"}", $secret);

        $this->expectException(PixelforgeSignatureException::class);

        $verifier->verify(
            rawPayload: '{"id":"evt_999"}',
            signatureHeader: "t={$timestamp},v1={$sig}",
            secret: $secret,
            toleranceSeconds: 300,
            nowTimestamp: $timestamp,
        );
    }

    public function test_rejects_stale_timestamp(): void
    {
        $verifier = new PixelforgeSignatureVerifier;
        $secret = 'whsec_test_secret';
        $payload = '{"id":"evt_123"}';
        $timestamp = 1_762_265_009;
        $sig = hash_hmac('sha256', "{$timestamp}.{$payload}", $secret);

        $this->expectException(PixelforgeSignatureException::class);

        $verifier->verify(
            rawPayload: $payload,
            signatureHeader: "t={$timestamp},v1={$sig}",
            secret: $secret,
            toleranceSeconds: 300,
            nowTimestamp: $timestamp + 301,
        );
    }

    public function test_rejects_missing_signature_header(): void
    {
        $verifier = new PixelforgeSignatureVerifier;

        $this->expectException(PixelforgeSignatureException::class);

        $verifier->verify(
            rawPayload: '{}',
            signatureHeader: null,
            secret: 'whsec_test_secret',
            toleranceSeconds: 300,
            nowTimestamp: 1_762_265_009,
        );
    }
}
