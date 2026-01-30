<?php

namespace LittleGreenMan\Earhart\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use LittleGreenMan\Earhart\Exceptions\InvalidUserException;
use LittleGreenMan\Earhart\Services\UserService;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class VerifyPropelAuthUser
{
    public function __construct(
        protected UserService $userService,
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(Request): (SymfonyResponse)  $next
     */
    public function handle(Request $request, Closure $next): SymfonyResponse
    {
        // Extract the PropelAuth session token from the request
        $token = $this->extractToken($request);

        if (! $token) {
            return $this->unauthorized('Missing authentication token');
        }

        try {
            // Validate the token with PropelAuth
            $user = $this->userService->validateToken($token);

            // Check if user is disabled
            if ($user->enabled === false) {
                return $this->forbidden('User account is disabled');
            }

            // Inject user into request for downstream use
            $request->attributes->set('propelauth_user', $user);
            $request->setUserResolver(fn () => $user);

            return $next($request);
        } catch (InvalidUserException) {
            return $this->unauthorized('Invalid or expired token');
        } catch (\Exception $e) {
            return $this->unauthorized('Authentication failed: '.$e->getMessage());
        }
    }

    /**
     * Extract PropelAuth token from request.
     *
     * Checks in order:
     * 1. Authorization header (Bearer token)
     * 2. Cookie (propelauth_session)
     * 3. Query parameter (for backward compatibility)
     */
    protected function extractToken(Request $request): ?string
    {
        // Check Authorization header
        $authHeader = $request->header('Authorization');
        if ($authHeader && str_starts_with($authHeader, 'Bearer ')) {
            return substr($authHeader, 7);
        }

        // Check PropelAuth session cookie
        if ($request->hasCookie('propelauth_session')) {
            return $request->cookie('propelauth_session');
        }

        // Check query parameter (optional, for flexibility)
        if ($request->has('token')) {
            return $request->query('token');
        }

        return null;
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
}
