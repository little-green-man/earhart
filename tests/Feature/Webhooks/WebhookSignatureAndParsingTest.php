<?php

namespace LittleGreenMan\Earhart\Tests\Feature\Webhooks;

use LittleGreenMan\Earhart\Events\PropelAuth\OrgCreated;
use LittleGreenMan\Earhart\Events\PropelAuth\UserCreated;
use LittleGreenMan\Earhart\Events\PropelAuth\UserUpdated;
use LittleGreenMan\Earhart\Tests\TestCase;
use LittleGreenMan\Earhart\Webhooks\WebhookConfig;
use LittleGreenMan\Earhart\Webhooks\WebhookEventParser;
use LittleGreenMan\Earhart\Webhooks\WebhookSignatureVerifier;
use Svix\Webhook;

/**
 * Integration tests for webhook signature verification and parsing
 */
class WebhookSignatureAndParsingTest extends TestCase
{
    private string $signingSecret = 'whsec_test1234567890abcdefghijk';

    protected function setUp(): void
    {
        parent::setUp();
    }

    public function test_webhook_signature_verification_end_to_end(): void
    {
        $verifier = new WebhookSignatureVerifier($this->signingSecret);
        $webhook = new Webhook($this->signingSecret);

        $payload = json_encode([
            'event_type' => 'user.created',
            'user_id' => 'user_e2e_001',
        ]);

        $msgId = 'msg_e2e_001';
        $msgTimestamp = (string) time();
        $msgSignature = $webhook->sign($msgId, $msgTimestamp, $payload);

        $headers = [
            'svix-id' => $msgId,
            'svix-timestamp' => $msgTimestamp,
            'svix-signature' => $msgSignature,
        ];

        $verifiedPayload = $verifier->verify($payload, $headers);

        $this->assertIsArray($verifiedPayload);
        $this->assertEquals('user.created', $verifiedPayload['event_type']);
        $this->assertEquals('user_e2e_001', $verifiedPayload['user_id']);
    }

    public function test_signature_verification_detects_payload_tampering(): void
    {
        $verifier = new WebhookSignatureVerifier($this->signingSecret);
        $webhook = new Webhook($this->signingSecret);

        $originalPayload = json_encode([
            'event_type' => 'user.created',
            'user_id' => 'user_123',
        ]);

        $msgId = 'msg_tamper_001';
        $msgTimestamp = (string) time();
        $msgSignature = $webhook->sign($msgId, $msgTimestamp, $originalPayload);

        $tamperedPayload = json_encode([
            'event_type' => 'user.deleted',
            'user_id' => 'user_456',
        ]);

        $headers = [
            'svix-id' => $msgId,
            'svix-timestamp' => $msgTimestamp,
            'svix-signature' => $msgSignature,
        ];

        $this->expectException(\Svix\Exception\WebhookVerificationException::class);
        $verifier->verify($tamperedPayload, $headers);
    }

    public function test_signature_verification_rejects_wrong_secret(): void
    {
        $verifier = new WebhookSignatureVerifier('whsec_wrong_secret_1234567890');
        $webhook = new Webhook($this->signingSecret); // Different secret

        $payload = json_encode([
            'event_type' => 'user.created',
            'user_id' => 'user_123',
        ]);

        $msgId = 'msg_wrong_secret';
        $msgTimestamp = (string) time();
        $msgSignature = $webhook->sign($msgId, $msgTimestamp, $payload);

        $headers = [
            'svix-id' => $msgId,
            'svix-timestamp' => $msgTimestamp,
            'svix-signature' => $msgSignature,
        ];

        $this->expectException(\Svix\Exception\WebhookVerificationException::class);
        $verifier->verify($payload, $headers);
    }

    public function test_signature_verification_enforces_all_required_headers(): void
    {
        $verifier = new WebhookSignatureVerifier($this->signingSecret);
        $payload = '{"event_type":"user.created"}';

        // Missing svix-id
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing required Svix headers');
        $verifier->verify($payload, [
            'svix-timestamp' => (string) time(),
            'svix-signature' => 'v1,sig',
        ]);
    }

    public function test_signature_verification_handles_case_insensitive_headers(): void
    {
        $verifier = new WebhookSignatureVerifier($this->signingSecret);
        $webhook = new Webhook($this->signingSecret);

        $payload = json_encode(['event_type' => 'user.created']);
        $msgId = 'msg_case_test';
        $msgTimestamp = (string) time();
        $msgSignature = $webhook->sign($msgId, $msgTimestamp, $payload);

        // Uppercase headers
        $uppercaseHeaders = [
            'SVIX-ID' => $msgId,
            'SVIX-TIMESTAMP' => $msgTimestamp,
            'SVIX-SIGNATURE' => $msgSignature,
        ];

        $verifiedPayload = $verifier->verify($payload, $uppercaseHeaders);

        $this->assertIsArray($verifiedPayload);
        $this->assertEquals('user.created', $verifiedPayload['event_type']);
    }

    public function test_timestamp_validation_prevents_replay_attacks(): void
    {
        $verifier = new WebhookSignatureVerifier($this->signingSecret);
        $tolerance = 300; // 5 minutes

        // Recent timestamp - should be valid
        $this->assertTrue($verifier->isTimestampValid((string) time(), $tolerance));

        // 100 seconds ago - should be valid
        $this->assertTrue($verifier->isTimestampValid((string) (time() - 100), $tolerance));

        // 400 seconds ago - should be invalid
        $this->assertFalse($verifier->isTimestampValid((string) (time() - 400), $tolerance));

        // Future timestamp - should be invalid
        $this->assertFalse($verifier->isTimestampValid((string) (time() + 400), $tolerance));
    }

    public function test_timestamp_validation_with_custom_tolerance(): void
    {
        $verifier = new WebhookSignatureVerifier($this->signingSecret);

        // 2 minutes old with 3-minute tolerance
        $timestamp = (string) (time() - 120);
        $this->assertTrue($verifier->isTimestampValid($timestamp, 180));

        // 5 minutes old with 3-minute tolerance
        $timestamp = (string) (time() - 300);
        $this->assertFalse($verifier->isTimestampValid($timestamp, 180));
    }

    public function test_webhook_parser_supports_all_event_types(): void
    {
        $parser = new WebhookEventParser;
        $supportedEvents = $parser->getSupportedEventTypes();

        // Verify we have all the expected event types
        $expectedEvents = [
            'user.created',
            'user.updated',
            'user.deleted',
            'user.disabled',
            'user.enabled',
            'user.locked',
            'user.added_to_org',
            'user.removed_from_org',
            'user.role_changed_within_org',
            'org.created',
            'org.updated',
            'org.deleted',
        ];

        $this->assertCount(count($expectedEvents), $supportedEvents);
        foreach ($expectedEvents as $event) {
            $this->assertContains($event, $supportedEvents);
        }
    }

    public function test_webhook_parser_routes_to_correct_event_classes(): void
    {
        $parser = new WebhookEventParser;

        $userCreated = $parser->parse([
            'event_type' => 'user.created',
            'user_id' => 'user_001',
            'email' => 'user1@example.com',
            'email_confirmed' => true,
        ]);

        $userUpdated = $parser->parse([
            'event_type' => 'user.updated',
            'user_id' => 'user_002',
            'email' => 'user2@example.com',
            'email_confirmed' => true,
        ]);

        $orgCreated = $parser->parse([
            'event_type' => 'org.created',
            'org_id' => 'org_001',
            'name' => 'Test Org',
        ]);

        $this->assertInstanceOf(UserCreated::class, $userCreated);
        $this->assertInstanceOf(UserUpdated::class, $userUpdated);
        $this->assertInstanceOf(OrgCreated::class, $orgCreated);
    }

    public function test_webhook_parser_returns_null_for_unsupported_events(): void
    {
        $parser = new WebhookEventParser;

        $event = $parser->parse([
            'event_type' => 'custom.unsupported.event',
        ]);

        $this->assertNull($event);
    }

    public function test_webhook_parser_throws_for_missing_event_type(): void
    {
        $parser = new WebhookEventParser;

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('event_type');
        $parser->parse([]);
    }

    public function test_webhook_config_default_settings(): void
    {
        $config = WebhookConfig::default();

        $this->assertTrue($config->shouldVerifySignatures());
        $this->assertTrue($config->shouldInvalidateUserListCache());
        $this->assertTrue($config->shouldInvalidateOrgListCache());
        $this->assertEquals(300, $config->getTimestampTolerance());
    }

    public function test_webhook_config_can_be_customized(): void
    {
        $config = WebhookConfig::default()
            ->setVerifySignatures(false)
            ->withSigningSecret('whsec_custom_secret')
            ->setTimestampTolerance(600)
            ->setInvalidateUserListCache(false);

        $this->assertFalse($config->shouldVerifySignatures());
        $this->assertEquals('whsec_custom_secret', $config->getSigningSecret());
        $this->assertEquals(600, $config->getTimestampTolerance());
        $this->assertFalse($config->shouldInvalidateUserListCache());
    }

    public function test_webhook_config_can_be_created_from_array(): void
    {
        $array = [
            'verify_signatures' => false,
            'signing_secret' => 'whsec_from_array',
            'timestamp_tolerance_seconds' => 900,
            'invalidate_user_list_cache' => false,
        ];

        $config = WebhookConfig::fromArray($array);

        $this->assertFalse($config->shouldVerifySignatures());
        $this->assertEquals('whsec_from_array', $config->getSigningSecret());
        $this->assertEquals(900, $config->getTimestampTolerance());
        $this->assertFalse($config->shouldInvalidateUserListCache());
    }

    public function test_signing_secret_masking_protects_sensitive_data(): void
    {
        $verifier = new WebhookSignatureVerifier('whsec_verysensitivekey123456789');
        $masked = $verifier->getMaskedSecret();

        // Should show first 8 characters
        $this->assertStringStartsWith('whsec_ve', $masked);

        // Should contain asterisks
        $this->assertStringContainsString('*', $masked);

        // Should not contain the sensitive part
        $this->assertStringNotContainsString('sensitivekey123456789', $masked);
        $this->assertStringNotContainsString('123456789', $masked);
    }

    public function test_multiple_webhooks_can_be_verified_sequentially(): void
    {
        $verifier = new WebhookSignatureVerifier($this->signingSecret);
        $parser = new WebhookEventParser;
        $webhook = new Webhook($this->signingSecret);

        $eventTypes = ['user.created', 'user.updated', 'org.created'];

        foreach ($eventTypes as $eventType) {
            $payload = json_encode([
                'event_type' => $eventType,
                'user_id' => 'user_123',
                'org_id' => 'org_456',
                'email' => 'test@example.com',
                'email_confirmed' => true,
                'name' => 'Test Org',
            ]);

            $msgId = 'msg_'.md5(microtime());
            $msgTimestamp = (string) time();
            $msgSignature = $webhook->sign($msgId, $msgTimestamp, $payload);

            $headers = [
                'svix-id' => $msgId,
                'svix-timestamp' => $msgTimestamp,
                'svix-signature' => $msgSignature,
            ];

            $verifiedPayload = $verifier->verify($payload, $headers);
            $event = $parser->parse($verifiedPayload);

            $this->assertNotNull($event);
            $this->assertTrue($parser->isSupported($eventType));
        }
    }
}
