<?php

namespace App\Services\IssueStudio;

use App\Services\AI\AnthropicClient;

/**
 * Issue Studio's Anthropic Messages API client.
 *
 * Now a thin subclass of the shared App\Services\AI\AnthropicClient (extracted
 * in T3 W0). Behavior is identical — this only pins the "IssueStudio" log
 * context so warnings stay attributable. All logic lives in the parent.
 */
class AnthropicGateway extends AnthropicClient
{
    protected string $logContext = 'IssueStudio';
}
