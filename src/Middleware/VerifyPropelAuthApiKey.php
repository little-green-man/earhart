<?php

namespace LittleGreenMan\Earhart\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use LittleGreenMan\Earhart\Exceptions\ApiKeyRateLimitException;
use LittleGreenMan\Earhart\Exceptions\InvalidApiKeyException;
use LittleGreenMan\Earhart\Services\ApiKeyService;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Authenticate a request by an end-user API key in the Authorization header.
 *
 * Use as VerifyPropelAuthApiKey::class for any key, or with ':personal' or
 * ':org' to require that kind. Sets the request attributes `propelauth_api_key`
 * (ApiKeyValidation), `propelauth_user` (when the key has a user) and
 * `propelauth_org_id` (when it has an organisation), so VerifyPropelAuthPermission
 * can follow it.
 */
class VerifyPropelAuthApiKey
{
    public function __construct(
        protected ApiKeyService $apiKeyService,
    ) {}

    /**
     * @param  Closure(Request): (SymfonyResponse)  $next
     * @param  ?string  $kind  "personal", "org", or null for either
     */
    public function handle(Request $request, Closure $next, ?string $kind = null): SymfonyResponse
    {
        $token = $request->bearerToken();

        if (! $token) {
            return $this->error('Unauthorized', 'Missing API key', Response::HTTP_UNAUTHORIZED);
        }

        try {
            $validation = match ($kind) {
                'personal' => $this->apiKeyService->validatePersonalApiKey($token),
                'org' => $this->apiKeyService->validateOrgApiKey($token),
                default => $this->apiKeyService->validateApiKey($token),
            };
        } catch (InvalidApiKeyException) {
            return $this->error('Unauthorized', 'Invalid API key', Response::HTTP_UNAUTHORIZED);
        } catch (ApiKeyRateLimitException $e) {
            return $this->error('Too Many Requests', $e->userFacingError ?? 'API key rate limit exceeded', Response::HTTP_TOO_MANY_REQUESTS)
                ->header('Retry-After', (string) $e->waitSeconds);
        }

        $request->attributes->set('propelauth_api_key', $validation);

        if ($validation->user !== null) {
            $request->attributes->set('propelauth_user', $validation->user);
            $request->setUserResolver(fn () => $validation->user);
        }

        if ($validation->org !== null) {
            $request->attributes->set('propelauth_org_id', $validation->org->orgId);
        }

        return $next($request);
    }

    protected function error(string $error, string $message, int $status): JsonResponse
    {
        return response()->json(['error' => $error, 'message' => $message], $status);
    }
}
