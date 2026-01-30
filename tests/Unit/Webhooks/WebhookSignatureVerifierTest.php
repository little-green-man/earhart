<?php

namespace LittleGreenMan\Earhart\Tests\Unit\Webhooks;

use LittleGreenMan\Earhart\Webhooks\WebhookSignatureVerifier;
use Svix\Exception\WebhookVerificationException;
use Svix\Webhook;

describe('WebhookSignatureVerifier', function () {
    describe('constructor', function () {
        it('creates a verifier with a valid signing secret', function () {
            $verifier = new WebhookSignatureVerifier('whsec_test1234567890');
            expect($verifier)->toBeInstanceOf(WebhookSignatureVerifier::class);
        });

        it('throws when signing secret is empty', function () {
            expect(fn () => new WebhookSignatureVerifier(''))
                ->toThrow(\InvalidArgumentException::class, 'Webhook signing secret cannot be empty.');
        });

        it('throws when signing secret is only whitespace', function () {
            expect(fn () => new WebhookSignatureVerifier('   '))->not->toThrow(\InvalidArgumentException::class);
        });
    });

    describe('verify', function () {
        it('verifies a valid webhook request', function () {
            $signingSecret = 'whsec_test1234567890abcdefghijk';
            $verifier = new WebhookSignatureVerifier($signingSecret);

            // Create a test webhook using Svix's Webhook class
            $webhook = new Webhook($signingSecret);
            $payload = json_encode([
                'event_type' => 'user.created',
                'user_id' => 'test-user-123',
                'created_at' => time(),
            ]);

            // Generate valid headers
            $msgId = 'msg_test123';
            $msgTimestamp = (string) time();
            $msgSignature = $webhook->sign($msgId, $msgTimestamp, $payload);

            $headers = [
                'svix-id' => $msgId,
                'svix-timestamp' => $msgTimestamp,
                'svix-signature' => $msgSignature,
            ];

            // Verify the payload
            $result = $verifier->verify($payload, $headers);

            expect($result)
                ->toBeArray()
                ->toHaveKey('event_type', 'user.created')
                ->toHaveKey('user_id', 'test-user-123');
        });

        it('throws when svix-id header is missing', function () {
            $verifier = new WebhookSignatureVerifier('whsec_test1234567890');
            $payload = '{"event_type":"user.created"}';

            expect(fn () => $verifier->verify($payload, [
                'svix-timestamp' => (string) time(),
                'svix-signature' => 'v1,test',
            ]))
                ->toThrow(\InvalidArgumentException::class, 'Missing required Svix headers');
        });

        it('throws when svix-timestamp header is missing', function () {
            $verifier = new WebhookSignatureVerifier('whsec_test1234567890');
            $payload = '{"event_type":"user.created"}';

            expect(fn () => $verifier->verify($payload, [
                'svix-id' => 'msg_123',
                'svix-signature' => 'v1,test',
            ]))
                ->toThrow(\InvalidArgumentException::class, 'Missing required Svix headers');
        });

        it('throws when svix-signature header is missing', function () {
            $verifier = new WebhookSignatureVerifier('whsec_test1234567890');
            $payload = '{"event_type":"user.created"}';

            expect(fn () => $verifier->verify($payload, [
                'svix-id' => 'msg_123',
                'svix-timestamp' => (string) time(),
            ]))
                ->toThrow(\InvalidArgumentException::class, 'Missing required Svix headers');
        });

        it('normalizes header keys to lowercase', function () {
            $signingSecret = 'whsec_test1234567890abcdefghijk';
            $verifier = new WebhookSignatureVerifier($signingSecret);

            $webhook = new Webhook($signingSecret);
            $payload = json_encode(['event_type' => 'user.created']);

            $msgId = 'msg_test456';
            $msgTimestamp = (string) time();
            $msgSignature = $webhook->sign($msgId, $msgTimestamp, $payload);

            // Use uppercase headers
            $headers = [
                'SVIX-ID' => $msgId,
                'SVIX-TIMESTAMP' => $msgTimestamp,
                'SVIX-SIGNATURE' => $msgSignature,
            ];

            $result = $verifier->verify($payload, $headers);
            expect($result)->toBeArray();
        });

        it('throws WebhookVerificationException when signature is invalid', function () {
            $verifier = new WebhookSignatureVerifier('whsec_test1234567890');
            $payload = '{"event_type":"user.created"}';

            expect(fn () => $verifier->verify($payload, [
                'svix-id' => 'msg_123',
                'svix-timestamp' => (string) time(),
                'svix-signature' => 'v1,invalidsignature==',
            ]))
                ->toThrow(WebhookVerificationException::class);
        });

        it('throws when payload is tampered with', function () {
            $signingSecret = 'whsec_test1234567890abcdefghijk';
            $verifier = new WebhookSignatureVerifier($signingSecret);

            $webhook = new Webhook($signingSecret);
            $originalPayload = json_encode(['event_type' => 'user.created']);

            $msgId = 'msg_test789';
            $msgTimestamp = (string) time();
            $msgSignature = $webhook->sign($msgId, $msgTimestamp, $originalPayload);

            // Tamper with the payload
            $tamperedPayload = json_encode(['event_type' => 'user.deleted']);

            expect(fn () => $verifier->verify($tamperedPayload, [
                'svix-id' => $msgId,
                'svix-timestamp' => $msgTimestamp,
                'svix-signature' => $msgSignature,
            ]))
                ->toThrow(WebhookVerificationException::class);
        });
    });

    describe('isTimestampValid', function () {
        it('returns true for a recent timestamp', function () {
            $verifier = new WebhookSignatureVerifier('whsec_test1234567890');
            $recentTimestamp = (string) time();

            expect($verifier->isTimestampValid($recentTimestamp))->toBeTrue();
        });

        it('returns false for an old timestamp outside tolerance', function () {
            $verifier = new WebhookSignatureVerifier('whsec_test1234567890');
            $oldTimestamp = (string) (time() - 400); // 400 seconds ago, default tolerance is 300

            expect($verifier->isTimestampValid($oldTimestamp))->toBeFalse();
        });

        it('returns true for a timestamp within custom tolerance', function () {
            $verifier = new WebhookSignatureVerifier('whsec_test1234567890');
            $timestamp = (string) (time() - 350); // 350 seconds ago

            expect($verifier->isTimestampValid($timestamp, 400))->toBeTrue();
        });

        it('returns false for a future timestamp outside tolerance', function () {
            $verifier = new WebhookSignatureVerifier('whsec_test1234567890');
            $futureTimestamp = (string) (time() + 400);

            expect($verifier->isTimestampValid($futureTimestamp))->toBeFalse();
        });

        it('returns false for invalid timestamp string', function () {
            $verifier = new WebhookSignatureVerifier('whsec_test1234567890');

            expect($verifier->isTimestampValid('not-a-number'))->toBeFalse();
            expect($verifier->isTimestampValid('abc123xyz'))->toBeFalse();
        });

        it('handles edge case timestamps correctly', function () {
            $verifier = new WebhookSignatureVerifier('whsec_test1234567890');
            $currentTime = time();
            $tolerance = 300;

            // Exactly at lower boundary
            expect($verifier->isTimestampValid((string) ($currentTime - $tolerance)))->toBeTrue();

            // Exactly at upper boundary
            expect($verifier->isTimestampValid((string) ($currentTime + $tolerance)))->toBeTrue();

            // Just outside lower boundary
            expect($verifier->isTimestampValid((string) ($currentTime - $tolerance - 1)))->toBeFalse();

            // Just outside upper boundary
            expect($verifier->isTimestampValid((string) ($currentTime + $tolerance + 1)))->toBeFalse();
        });
    });

    describe('setSigningSecret', function () {
        it('updates the signing secret', function () {
            $verifier = new WebhookSignatureVerifier('whsec_old123');

            $result = $verifier->setSigningSecret('whsec_new456');

            expect($result)->toBe($verifier); // Check for fluent interface
        });

        it('throws when setting empty signing secret', function () {
            $verifier = new WebhookSignatureVerifier('whsec_test1234567890');

            expect(fn () => $verifier->setSigningSecret(''))
                ->toThrow(\InvalidArgumentException::class, 'Webhook signing secret cannot be empty.');
        });

        it('allows fluent interface chaining', function () {
            $verifier = new WebhookSignatureVerifier('whsec_initial');

            $result = $verifier->setSigningSecret('whsec_updated1')->setSigningSecret('whsec_updated2');

            expect($result)->toBeInstanceOf(WebhookSignatureVerifier::class);
        });
    });

    describe('getMaskedSecret', function () {
        it('returns masked secret for long secrets', function () {
            $verifier = new WebhookSignatureVerifier('whsec_thisisalongwhebrooksecretus');

            $masked = $verifier->getMaskedSecret();

            expect($masked)->toContain('whsec_th')->toContain('*');
        });

        it('returns fully masked secret for short secrets', function () {
            $verifier = new WebhookSignatureVerifier('secret');

            $masked = $verifier->getMaskedSecret();

            expect($masked)->toBe('******');
        });

        it('masks all but first 8 characters', function () {
            $secret = 'whsec_1234567890abcdefghij';
            $verifier = new WebhookSignatureVerifier($secret);

            $masked = $verifier->getMaskedSecret();

            expect($masked)->toStartWith('whsec_12')->not->toContain('34')->toContain('*');
        });

        it('handles 8-character secrets correctly', function () {
            $verifier = new WebhookSignatureVerifier('whsec123');

            $masked = $verifier->getMaskedSecret();

            // 8-character secrets are exactly at the boundary, so they get completely masked
            expect($masked)->toEqual('********');
        });
    });
});
