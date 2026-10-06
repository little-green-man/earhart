<?php

namespace LittleGreenMan\Earhart\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use LittleGreenMan\Earhart\Exceptions\InvalidOrgException;
use LittleGreenMan\Earhart\Services\OrganisationService;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class VerifyPropelAuthOrg
{
    public function __construct(
        protected OrganisationService $organisationService,
    ) {}

    /**
     * Handle an incoming request.
     *
     * Verifies that the authenticated user belongs to the specified organisation.
     * The organisation ID should be passed as a route parameter (e.g., 'org_id' or 'orgId').
     *
     * @param  Closure(Request): (SymfonyResponse)  $next
     */
    public function handle(Request $request, Closure $next, ?string $orgParameter = 'org_id'): SymfonyResponse
    {
        // Get the authenticated user
        $user = $request->attributes->get('propelauth_user');

        if (! $user) {
            return $this->unauthorized('User not authenticated');
        }

        // Extract organisation ID from route parameters or request attributes
        $orgId = $request->route($orgParameter) ?? $request->attributes->get($orgParameter);

        if (! $orgId) {
            return $this->badRequest("Missing organisation parameter: {$orgParameter}");
        }

        try {
            // Verify user belongs to this organisation
            if (! $this->userBelongsToOrg($user, $orgId)) {
                return $this->forbidden("User does not belong to organisation {$orgId}");
            }

            // Store org ID in request for downstream use
            $request->attributes->set('propelauth_org_id', $orgId);

            return $next($request);
        } catch (InvalidOrgException) {
            return $this->notFound("Organisation not found: {$orgId}");
        } catch (\Exception $e) {
            return $this->serverError('Failed to verify organisation membership: '.$e->getMessage());
        }
    }

    /**
     * Check if user belongs to the specified organisation.
     *
     * Works with UserData (fetched with memberships) and AccessToken.
     */
    protected function userBelongsToOrg(mixed $user, string $orgId): bool
    {
        return is_object($user) && method_exists($user, 'isMemberOf') && $user->isMemberOf($orgId);
    }

    /**
     * Return unauthorized response.
     */
    protected function unauthorized(string $message): SymfonyResponse
    {
        return response()->json([
            'error' => 'Unauthorized',
            'message' => $message,
        ], Response::HTTP_UNAUTHORIZED);
    }

    /**
     * Return forbidden response.
     */
    protected function forbidden(string $message): SymfonyResponse
    {
        return response()->json([
            'error' => 'Forbidden',
            'message' => $message,
        ], Response::HTTP_FORBIDDEN);
    }

    /**
     * Return not found response.
     */
    protected function notFound(string $message): SymfonyResponse
    {
        return response()->json([
            'error' => 'Not Found',
            'message' => $message,
        ], Response::HTTP_NOT_FOUND);
    }

    /**
     * Return bad request response.
     */
    protected function badRequest(string $message): SymfonyResponse
    {
        return response()->json([
            'error' => 'Bad Request',
            'message' => $message,
        ], Response::HTTP_BAD_REQUEST);
    }

    /**
     * Return server error response.
     */
    protected function serverError(string $message): SymfonyResponse
    {
        return response()->json([
            'error' => 'Internal Server Error',
            'message' => $message,
        ], Response::HTTP_INTERNAL_SERVER_ERROR);
    }
}
