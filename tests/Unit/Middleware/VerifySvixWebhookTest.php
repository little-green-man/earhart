<?php

use Illuminate\Http\Request;
use LittleGreenMan\Earhart\Middleware\VerifySvixWebhook;
use LittleGreenMan\Earhart\Tests\TestCase;

uses(TestCase::class);

describe('VerifySvixWebhook', function () {
    test('throws a clear error when the webhook secret is not configured', function () {
        config()->set('services.propelauth.svix_secret', null);

        $middleware = new VerifySvixWebhook;

        expect(fn () => $middleware->handle(Request::create('/auth/webhooks', 'POST'), fn () => response('ok')))
            ->toThrow(RuntimeException::class, 'PROPELAUTH_SVIX_SECRET');
    });
});
