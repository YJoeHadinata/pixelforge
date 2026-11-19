<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Generations\PollInFlightGenerationsAction;
use App\Actions\Generations\SubmitGenerationAction;
use App\Enums\GenerationStatus;
use App\Enums\ImageQuality;
use App\Enums\ImageSize;
use App\Http\Requests\StoreGenerationRequest;
use App\Models\Generation;
use App\Models\User;
use App\Queries\UserGenerationHistoryQuery;
use App\Services\Pixelforge\Exceptions\InsufficientCreditsException;
use App\Services\Pixelforge\Exceptions\PixelforgeApiException;
use App\Services\Pixelforge\PixelforgePricingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class GenerationController extends Controller
{
    public function index(
        Request $request,
        PixelforgePricingService $pricingService,
        UserGenerationHistoryQuery $historyQuery,
    ): Response {
        /** @var User $user */
        $user = $request->user();

        return Inertia::render('Generations/Index', [
            'pricing' => $pricingService->current()->toArray(),
            'markupPercent' => (int) config('services.pixelforge.markup_percent', 25),
            'history' => $historyQuery->historyForUser($user),
            'sizes' => array_map(
                static fn (ImageSize $s): array => ['value' => $s->value, 'label' => $s->label()],
                ImageSize::cases(),
            ),
            'qualities' => array_map(
                static fn (ImageQuality $q): array => ['value' => $q->value, 'label' => $q->label()],
                ImageQuality::cases(),
            ),
        ]);
    }

    public function store(StoreGenerationRequest $request, SubmitGenerationAction $submit): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        try {
            $generation = $submit->execute(
                user: $user,
                prompt: (string) $request->validated('prompt'),
                size: ImageSize::from((string) $request->validated('size')),
                quality: ImageQuality::from((string) $request->validated('quality')),
                referenceImages: $request->validated('reference_images') ?? [],
            );

            $message = $generation->status === GenerationStatus::QueuedLocally
                ? 'Pixelforge concurrency limit reached (max 3 active). Your generation is queued locally and will submit automatically.'
                : 'Generation submitted to Pixelforge.';

            return back()->with('success', $message);
        } catch (InsufficientCreditsException $e) {
            return back()->withErrors([
                'credits' => $e->getMessage(),
            ])->withInput();
        } catch (PixelforgeApiException $e) {
            if ($e->validationErrors !== []) {
                return back()->withErrors($e->validationErrors)->withInput();
            }

            $field = $e->param !== null && in_array($e->param, ['prompt', 'size', 'quality', 'reference_images'], true)
                ? $e->param
                : 'prompt';

            return back()->withErrors([
                $field => $e->errorMessage,
            ])->withInput();
        }
    }

    public function status(
        Request $request,
        PollInFlightGenerationsAction $poll,
        UserGenerationHistoryQuery $historyQuery,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();

        if ($request->boolean('sync', true)) {
            $poll->syncForUser($user);
        }

        return response()->json(
            $historyQuery->statusSnapshotForUser($user->fresh() ?? $user),
        );
    }

    public function sync(
        Request $request,
        Generation $generation,
        PollInFlightGenerationsAction $poll,
    ): RedirectResponse {
        /** @var User $user */
        $user = $request->user();

        if ((int) $generation->user_id !== (int) $user->id) {
            abort(403);
        }

        try {
            $poll->syncOne($generation);

            return back()->with('success', 'Generation status synced from Pixelforge.');
        } catch (PixelforgeApiException $e) {
            return back()->with('error', $e->errorMessage);
        }
    }
}
