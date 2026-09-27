<?php

namespace LittleGreenMan\Earhart\Middleware;

use Closure;
use Illuminate\Http\Request;
use Svix\Exception\WebhookVerificationException;
use Svix\Webhook;
use Symfony\Component\HttpFoundation\Response;

class VerifySvixWebhook
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     *
     * @throws WebhookVerificationException
     */
    public function handle(Request $request, Closure $next): Response
    {
        $payload = $request->getContent();
        $headers = [
            'svix-id' => $request->headers->get('svix-id'),
            'svix-timestamp' => $request->headers->get('svix-timestamp'),
            'svix-signature' => $request->headers->get('svix-signature'),
        ];
        $secret = config('services.propelauth.svix_secret');
        if (! $secret) {
            throw new \RuntimeException(
                'PropelAuth Webhook Secret is not configured. '
                .'Please set the PROPELAUTH_SVIX_SECRET environment variable and configure services.propelauth.svix_secret in config/services.php',
            );
        }

        $wh = new Webhook($secret);
        $wh->verify($payload, $headers); // returns json

        return $next($request);
    }
}
