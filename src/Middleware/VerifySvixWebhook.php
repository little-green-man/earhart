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
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
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
        $wh = new Webhook(config('services.propelauth.svix_secret'));
        $wh->verify($payload, $headers); // returns json

        return $next($request);
    }
}
