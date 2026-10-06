<?php

namespace LittleGreenMan\Earhart\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class VerifyPropelAuthPermission
{
    /**
     * Handle an incoming request.
     *
     * Verifies that the authenticated user has the required role/permission
     * within the specified organisation.
     *
     * @param  Closure(Request): (SymfonyResponse)  $next
     */
    public function handle(Request $request, Closure $next, string $requiredRole): SymfonyResponse
    {
        // Get the authenticated user
        $user = $request->attributes->get('propelauth_user');

        if (! $user) {
            return $this->unauthorized('User not authenticated');
        }

        // Get the organisation ID from request attributes (set by VerifyPropelAuthOrg)
        $orgId = $request->attributes->get('propelauth_org_id');

        if (! $orgId) {
            return $this->badRequest('Organisation context not found');
        }

        // Check if user has required role in the organisation
        if (! $this->userHasRole($user, $orgId, $requiredRole)) {
            return $this->forbidden("User does not have required role: {$requiredRole}");
        }

        return $next($request);
    }

    /**
     * Check if the user has the required role (or a role above it) in the organisation.
     *
     * Roles follow your PropelAuth role hierarchy, using the inherited roles
     * PropelAuth returns: an Owner passes a check for Admin or Member. Prefix
     * the argument with "permission:" to check a permission instead, e.g.
     * `VerifyPropelAuthPermission::class.':permission:propelauth::can_invite'`.
     */
    protected function userHasRole(mixed $user, string $orgId, string $requiredRole): bool
    {
        if (! is_object($user) || ! method_exists($user, 'org')) {
            return false;
        }

        $membership = $user->org($orgId);

        if ($membership === null) {
            return false;
        }

        if (str_starts_with($requiredRole, 'permission:')) {
            return $membership->hasPermission(substr($requiredRole, strlen('permission:')));
        }

        return $membership->isAtLeastRole($requiredRole);
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
     * Return bad request response.
     */
    protected function badRequest(string $message): SymfonyResponse
    {
        return response()->json([
            'error' => 'Bad Request',
            'message' => $message,
        ], Response::HTTP_BAD_REQUEST);
    }
}
