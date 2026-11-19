<?php

declare(strict_types=1);

namespace App\Http\Controllers\Webhooks;

use App\Actions\Generations\ProcessPixelforgeWebhookAction;
use App\Http\Controllers\Controller;
use App\Services\Pixelforge\Exceptions\PixelforgeSignatureException;
use App\Services\Pixelforge\PixelforgeSignatureVerifier;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class PixelforgeWebhookController extends Controller
{
    public function __invoke(
        Request $request,
        PixelforgeSignatureVerifier $verifier,
        ProcessPixelforgeWebhookAction $processWebhook,
    ): Response {
        $rawPayload = $request->getContent();

        try {
            $verifier->verify(
                rawPayload: $rawPayload,
                signatureHeader: $request->header('Pixelforge-Signature'),
            );
        } catch (PixelforgeSignatureException $e) {
            abort(400, $e->getMessage());
        }

        $decoded = json_decode($rawPayload, true);
        if (! is_array($decoded)) {
            abort(400, 'Invalid JSON webhook payload.');
        }

        $attempt = (int) $request->header('Pixelforge-Delivery-Attempt', '1');
        $processWebhook->execute($decoded, $attempt);

        return response()->noContent();
    }
}
