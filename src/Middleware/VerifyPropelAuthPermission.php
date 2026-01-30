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
     * @param  \Closure(Request): (SymfonyResponse)  $next
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
     * Check if user has the specified role in the organisation.
     *
     * Supports role hierarchy:
     * - owner: all permissions
     * - admin: most permissions except org deletion
     * - member: basic permissions
     * - custom roles: exact match required
     */
    protected function userHasRole(mixed $user, string $orgId, string $requiredRole): bool
    {
        // Check if user has orgs property
        if (! isset($user->orgs) || ! is_array($user->orgs)) {
            return false;
        }

        // Find the user's role in this organisation
        $userRole = null;
        foreach ($user->orgs as $org) {
            $currentOrgId = $this->extractOrgId($org);
            if ($currentOrgId === $orgId) {
                $userRole = $this->extractUserRole($org);
                break;
            }
        }

        if (! $userRole) {
            return false;
        }

        // Check role hierarchy
        return $this->roleHasPermission($userRole, $requiredRole);
    }

    /**
     * Extract organisation ID from org object/array.
     */
    protected function extractOrgId(mixed $org): ?string
    {
        if (is_array($org)) {
            return $org['id'] ?? $org['orgId'] ?? null;
        }
        if (is_object($org)) {
            return $org->id ?? $org->orgId ?? null;
        }

        return null;
    }

    /**
     * Extract user's role from org object/array.
     */
    protected function extractUserRole(mixed $org): ?string
    {
        if (is_array($org)) {
            return $org['user_role'] ?? $org['userRole'] ?? $org['role'] ?? null;
        }
        if (is_object($org)) {
            return $org->user_role ?? $org->userRole ?? $org->role ?? null;
        }

        return null;
    }

    /**
     * Check if a role has permission to fulfill a required role.
     *
     * Role hierarchy (higher roles include lower roles):
     * - owner (highest)
     * - admin
     * - member (lowest)
     */
    protected function roleHasPermission(string $userRole, string $requiredRole): bool
    {
        $roleHierarchy = [
            'owner' => 3,
            'admin' => 2,
            'member' => 1,
        ];

        // Get hierarchy values
        $userRoleLevel = $roleHierarchy[strtolower($userRole)] ?? 0;
        $requiredRoleLevel = $roleHierarchy[strtolower($requiredRole)] ?? 0;

        // If it's a custom role or unknown, require exact match
        if ($userRoleLevel === 0 || $requiredRoleLevel === 0) {
            return strtolower($userRole) === strtolower($requiredRole);
        }

        // For standard roles, check hierarchy
        return $userRoleLevel >= $requiredRoleLevel;
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
