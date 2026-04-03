<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\StoreApiKeyRequest;
use App\Http\Requests\Settings\UpdateSelectedAgentRequest;
use App\Services\Settings\AgentSettingsService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AgentSettingsController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly AgentSettingsService $agentSettingsService,
    ) {}

    public function show(Request $request): JsonResponse
    {
        return $this->success($this->agentSettingsService->getSettings($request->user()));
    }

    public function updateAgent(UpdateSelectedAgentRequest $request): JsonResponse
    {
        $user = $this->agentSettingsService->updateSelectedAgent(
            $request->user(),
            $request->string('selected_agent')->toString(),
        );

        return $this->success([
            'selected_agent' => $user->selected_agent,
        ]);
    }

    public function storeApiKey(StoreApiKeyRequest $request, string $provider): JsonResponse
    {
        if (! in_array($provider, config('chat.api_key_providers', []), true)) {
            return $this->error('The selected provider is invalid.', 422);
        }

        return $this->success(
            $this->agentSettingsService->storeApiKey(
                $request->user(),
                $provider,
                $request->string('api_key')->toString(),
            ),
        );
    }

    public function deleteApiKey(Request $request, string $provider): JsonResponse
    {
        if (! in_array($provider, config('chat.api_key_providers', []), true)) {
            return $this->error('The selected provider is invalid.', 422);
        }

        $this->agentSettingsService->deleteApiKey($request->user(), $provider);

        return $this->success([
            'provider' => $provider,
            'is_set' => false,
        ]);
    }
}
