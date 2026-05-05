<?php

namespace App\Http\Controllers\Magazine;

use App\Http\Controllers\Controller;
use App\Http\Requests\Magazine\LockStepRequest;
use App\Http\Requests\Magazine\SendMessageRequest;
use App\Http\Requests\Magazine\StoreSessionRequest;
use App\Http\Requests\Magazine\UnlockStepRequest;
use App\Http\Resources\V1\WizardSessionResource;
use App\Models\Magazine\WizardSession;
use App\Services\Magazine\AnthropicClient;
use App\Services\Magazine\ArtifactExtractor;
use App\Services\Magazine\WizardPromptBuilder;
use App\Services\Magazine\WizardProvisioner;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;

class WizardController extends Controller
{
    public function __construct(
        private AnthropicClient $anthropic,
        private WizardPromptBuilder $promptBuilder,
        private WizardProvisioner $provisioner,
    ) {}

    public function index(): JsonResponse
    {
        $this->authorize('viewAny', WizardSession::class);

        $sessions = WizardSession::where('user_id', auth()->id())
            ->where('status', '!=', 'abandoned')
            ->orderByDesc('updated_at')
            ->paginate(20);

        return WizardSessionResource::collection($sessions)->response();
    }

    public function store(StoreSessionRequest $request): JsonResponse
    {
        $session = WizardSession::create([
            'tenant_id' => $request->user()->tenant_id,
            'user_id' => $request->user()->id,
            'title' => $request->validated('title'),
            'current_step' => 1,
            'status' => 'active',
        ]);

        return (new WizardSessionResource($session))
            ->response()
            ->setStatusCode(201);
    }

    public function show(WizardSession $session): JsonResponse
    {
        $this->authorize('view', $session);

        $session->load(['messages' => fn ($q) => $q->orderBy('created_at')->limit(50)]);

        return (new WizardSessionResource($session))->response();
    }

    public function destroy(WizardSession $session): JsonResponse
    {
        $this->authorize('delete', $session);

        $session->update(['status' => 'abandoned']);

        return response()->json(['message' => 'Session abandoned.']);
    }

    /**
     * Send a message and stream the AI response via SSE.
     */
    public function sendMessage(SendMessageRequest $request, WizardSession $session): StreamedResponse
    {
        $this->authorize('update', $session);

        if (!$session->isActive()) {
            return $this->sseError('Session is not active.', 409);
        }

        if (!$this->anthropic->isAvailable()) {
            return $this->sseError('No Anthropic API key configured. Go to Settings → AI.', 503);
        }

        $step = $request->validated('step');
        $content = $request->validated('content');
        $tenantId = $request->user()->tenant_id;
        $startTime = microtime(true);

        Log::info('Wizard sendMessage', ['session_id' => $session->id, 'step' => $step]);

        // Persist user message immediately
        $userMsg = $session->messages()->create([
            'tenant_id' => $tenantId,
            'step' => $step,
            'role' => 'user',
            'content' => $content,
        ]);

        // Build system prompt
        $stepColumn = $this->stepColumn($step);
        $currentArtifact = $stepColumn ? $session->{$stepColumn} : null;
        $systemPrompt = $this->promptBuilder->build($session, $step, $currentArtifact);

        // Get conversation context (last 10 messages for this step)
        $conversationMessages = $this->promptBuilder->getStepMessages($session, $step);

        return new StreamedResponse(function () use ($session, $step, $tenantId, $systemPrompt, $conversationMessages, $startTime) {
            // Disable output buffering for SSE (keep at least 1 level for test compat)
            while (ob_get_level() > 1) @ob_end_flush();

            $this->anthropic->streamMessage(
                $systemPrompt,
                $conversationMessages,
                // onDelta
                function (string $chunk) {
                    echo "event: delta\n";
                    echo 'data: ' . json_encode(['text' => $chunk]) . "\n\n";
                    @ob_flush();
                    @flush();
                },
                // onComplete
                function (array $result) use ($session, $step, $tenantId, $startTime) {
                    $fullText = $result['full_text'];
                    $artifact = ArtifactExtractor::extract($fullText);

                    $assistantMsg = $session->messages()->create([
                        'tenant_id' => $tenantId,
                        'step' => $step,
                        'role' => 'assistant',
                        'content' => $fullText,
                        'artifact_update' => $artifact,
                        'tokens_in' => $result['tokens_in'],
                        'tokens_out' => $result['tokens_out'],
                    ]);

                    Log::info('Wizard AI response', [
                        'session_id' => $session->id, 'step' => $step,
                        'tokens_in' => $result['tokens_in'], 'tokens_out' => $result['tokens_out'],
                        'duration_ms' => round((microtime(true) - $startTime) * 1000),
                        'has_artifact' => $artifact !== null,
                    ]);

                    echo "event: complete\n";
                    echo 'data: ' . json_encode([
                        'message_id' => $assistantMsg->id,
                        'artifact' => $artifact,
                        'tokens_in' => $result['tokens_in'],
                        'tokens_out' => $result['tokens_out'],
                    ]) . "\n\n";
                    @ob_flush();
                    @flush();
                },
                // onError
                function (string $errorMessage) {
                    echo "event: error\n";
                    echo 'data: ' . json_encode(['message' => $errorMessage]) . "\n\n";
                    @ob_flush();
                    @flush();
                },
            );
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    /**
     * Lock the current step's artifact and advance.
     */
    public function lockStep(LockStepRequest $request, WizardSession $session): JsonResponse
    {
        $this->authorize('update', $session);

        $step = $request->validated('step');
        $artifact = $request->validated('locked_artifact');

        if ($step !== $session->current_step) {
            return response()->json([
                'message' => "Cannot lock step {$step}; session is on step {$session->current_step}.",
            ], 409);
        }

        $column = $this->stepColumn($step);
        if (!$column) {
            return response()->json(['message' => 'Invalid step.'], 422);
        }

        // Steps 4, 5, 6 are arrays — append rather than replace
        if (in_array($step, [4, 5, 6])) {
            $existing = $session->{$column} ?? [];
            $existing[] = $artifact;
            $session->{$column} = $existing;
        } else {
            $session->{$column} = $artifact;
        }

        // Advance unless already at step 7
        $fromStep = $session->current_step;
        if ($session->current_step < 7) {
            $session->current_step = $session->current_step + 1;
        }

        $session->save();

        Log::info('Wizard lockStep', [
            'session_id' => $session->id,
            'from_step' => $fromStep,
            'to_step' => $session->current_step,
        ]);

        return (new WizardSessionResource($session->fresh()))->response();
    }

    /**
     * Unlock and roll back to a prior step.
     */
    public function unlockStep(UnlockStepRequest $request, WizardSession $session): JsonResponse
    {
        $this->authorize('update', $session);

        $toStep = $request->validated('to_step');

        if ($toStep >= $session->current_step) {
            return response()->json([
                'message' => "Cannot unlock to step {$toStep}; session is on step {$session->current_step}.",
            ], 409);
        }

        // Clear all step columns >= toStep
        $stepColumns = [
            1 => 'step1_brief',
            2 => 'step2_structure',
            3 => 'step3_article_selection',
            4 => 'step4_analyses',
            5 => 'step5_directions',
            6 => 'step6_thumbnails',
        ];

        foreach ($stepColumns as $s => $col) {
            if ($s >= $toStep) {
                $session->{$col} = in_array($s, [4, 5, 6]) ? [] : null;
            }
        }

        $fromStep = $session->current_step;
        $session->current_step = $toStep;
        $session->save();

        Log::info('Wizard unlockStep', [
            'session_id' => $session->id,
            'from_step' => $fromStep,
            'to_step' => $toStep,
        ]);

        // Messages are preserved — do not delete
        return (new WizardSessionResource($session->fresh()))->response();
    }

    /**
     * Provision the wizard session into a magazine issue.
     */
    public function provision(WizardSession $session): JsonResponse
    {
        $this->authorize('update', $session);

        try {
            $issue = $this->provisioner->provision($session);

            return response()->json([
                'issue_id' => $issue->id,
                'redirect_url' => "/sites/{$issue->site_id}/pages/{$issue->linked_page_id}",
            ]);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            Log::error('Wizard provision controller error', ['session' => $session->id, 'error' => $e->getMessage()]);
            return response()->json(['message' => 'Provisioning failed. Please try again.'], 500);
        }
    }

    // ─── Helpers ───

    private function stepColumn(int $step): ?string
    {
        return match ($step) {
            1 => 'step1_brief',
            2 => 'step2_structure',
            3 => 'step3_article_selection',
            4 => 'step4_analyses',
            5 => 'step5_directions',
            6 => 'step6_thumbnails',
            default => null,
        };
    }

    private function sseError(string $message, int $status): StreamedResponse
    {
        return new StreamedResponse(function () use ($message) {
            echo "event: error\n";
            echo 'data: ' . json_encode(['message' => $message]) . "\n\n";
            flush();
        }, $status, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
        ]);
    }
}
