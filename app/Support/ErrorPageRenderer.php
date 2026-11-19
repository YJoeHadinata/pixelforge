<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

final class ErrorPageRenderer
{
    /** @var array<int, int> */
    private const HANDLED_STATUSES = [401, 403, 404, 419, 429, 500, 503];

    public static function respond(Response $response, Throwable $exception, Request $request): Response
    {
        $status = $response->getStatusCode();

        if (! in_array($status, self::HANDLED_STATUSES, true)) {
            return $response;
        }

        if ($request->is('api/*') || $request->is('webhooks/*')) {
            return $response;
        }

        if ($request->expectsJson() && ! $request->header('X-Inertia')) {
            return $response;
        }

        if ($status === 500 && config('app.debug')) {
            return $response;
        }

        try {
            $isAuthenticated = $request->user() !== null;
            $copy = self::copyFor($status, $exception);

            $primaryCta = $isAuthenticated && $status !== 401
                ? ['label' => 'Back to Studio', 'href' => route('generations.index')]
                : ['label' => 'Go to Sign In', 'href' => route('login')];

            $secondaryCta = match (true) {
                $isAuthenticated && $status !== 401 => ['label' => 'View Credits', 'href' => route('credits.index')],
                ! $isAuthenticated && in_array($status, [401, 403, 404], true) => ['label' => 'Create Account', 'href' => route('register')],
                default => null,
            };

            return Inertia::render('Errors/ErrorPage', [
                'status' => $status,
                'title' => $copy['title'],
                'description' => $copy['description'],
                'primaryCta' => $primaryCta,
                'secondaryCta' => $secondaryCta,
                'canRetry' => in_array($status, [419, 429, 500, 503], true),
            ])->toResponse($request)->setStatusCode($status);
        } catch (Throwable) {
            return $response;
        }
    }

    /**
     * @return array{title: string, description: string}
     */
    private static function copyFor(int $status, Throwable $exception): array
    {
        $customMessage = $exception instanceof HttpExceptionInterface
            ? trim($exception->getMessage())
            : '';

        return match ($status) {
            401 => [
                'title' => 'Authentication Required',
                'description' => 'Please sign in to access this page.',
            ],
            403 => [
                'title' => 'Access Denied',
                'description' => $customMessage !== ''
                    ? $customMessage
                    : 'You do not have permission to view this page.',
            ],
            404 => [
                'title' => 'Page Not Found',
                'description' => 'The page you are looking for does not exist or may have been moved.',
            ],
            419 => [
                'title' => 'Session Expired',
                'description' => 'Your security token expired due to inactivity. Try again or return to the app.',
            ],
            429 => [
                'title' => 'Too Many Requests',
                'description' => 'You have hit the rate limit for this action. Please wait a moment before trying again.',
            ],
            503 => [
                'title' => 'Service Unavailable',
                'description' => 'Pixelforge Studio is temporarily unavailable for maintenance. Please check back shortly.',
            ],
            default => [
                'title' => 'Something Went Wrong',
                'description' => 'An unexpected error occurred on our side. Please try again or return to the studio.',
            ],
        };
    }
}
