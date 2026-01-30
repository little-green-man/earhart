<?php

namespace LittleGreenMan\Earhart\Tests\Unit\Webhooks;

use LittleGreenMan\Earhart\Webhooks\WebhookConfig;

describe('WebhookConfig', function () {
    describe('default', function () {
        it('creates config with default settings', function () {
            $config = WebhookConfig::default();

            expect($config)->toBeInstanceOf(WebhookConfig::class);
            expect($config->shouldVerifySignatures())->toBeTrue();
            expect($config->shouldInvalidateUserListCache())->toBeTrue();
            expect($config->shouldInvalidateOrgListCache())->toBeTrue();
            expect($config->getTimestampTolerance())->toBe(300);
        });

        it('has default user cache invalidation events', function () {
            $config = WebhookConfig::default();
            $events = $config->getUserCacheInvalidationEvents();

            expect($events)->toContain('user.created');
            expect($events)->toContain('user.updated');
            expect($events)->toContain('user.deleted');
            expect($events)->toContain('user.added_to_org');
            expect($events)->toContain('user.removed_from_org');
        });

        it('has default org cache invalidation events', function () {
            $config = WebhookConfig::default();
            $events = $config->getOrgCacheInvalidationEvents();

            expect($events)->toContain('org.created');
            expect($events)->toContain('org.updated');
            expect($events)->toContain('org.deleted');
            expect($events)->toContain('user.added_to_org');
            expect($events)->toContain('user.removed_from_org');
        });
    });

    describe('fromArray', function () {
        it('creates config from array', function () {
            $config = WebhookConfig::fromArray([
                'verify_signatures' => false,
                'signing_secret' => 'whsec_test123',
                'timestamp_tolerance_seconds' => 600,
            ]);

            expect($config->shouldVerifySignatures())->toBeFalse();
            expect($config->getSigningSecret())->toBe('whsec_test123');
            expect($config->getTimestampTolerance())->toBe(600);
        });

        it('handles partial array config', function () {
            $config = WebhookConfig::fromArray([
                'verify_signatures' => false,
            ]);

            expect($config->shouldVerifySignatures())->toBeFalse();
            expect($config->getTimestampTolerance())->toBe(300); // default
        });

        it('handles empty array', function () {
            $config = WebhookConfig::fromArray([]);

            expect($config)->toBeInstanceOf(WebhookConfig::class);
        });

        it('applies custom cache invalidation events from array', function () {
            $config = WebhookConfig::fromArray([
                'user_cache_invalidation_events' => ['user.created', 'user.deleted'],
                'org_cache_invalidation_events' => ['org.updated'],
            ]);

            expect($config->getUserCacheInvalidationEvents())->toBe(['user.created', 'user.deleted']);
            expect($config->getOrgCacheInvalidationEvents())->toBe(['org.updated']);
        });

        it('applies list cache invalidation flags from array', function () {
            $config = WebhookConfig::fromArray([
                'invalidate_user_list_cache' => false,
                'invalidate_org_list_cache' => false,
            ]);

            expect($config->shouldInvalidateUserListCache())->toBeFalse();
            expect($config->shouldInvalidateOrgListCache())->toBeFalse();
        });

        it('applies cache key format from array', function () {
            $config = WebhookConfig::fromArray([
                'user_cache_key_format' => 'app:user:{id}',
                'org_cache_key_format' => 'app:org:{id}',
            ]);

            expect($config->getUserCacheKeyFormat())->toBe('app:user:{id}');
            expect($config->getOrgCacheKeyFormat())->toBe('app:org:{id}');
        });
    });

    describe('user cache invalidation events', function () {
        it('can set custom user cache invalidation events', function () {
            $config = WebhookConfig::default()->withUserCacheInvalidationEvents(['user.created', 'user.updated']);

            expect($config->getUserCacheInvalidationEvents())->toBe(['user.created', 'user.updated']);
        });

        it('can add user cache invalidation events', function () {
            $config = WebhookConfig::default();
            $originalCount = count($config->getUserCacheInvalidationEvents());

            $config->addUserCacheInvalidationEvent('custom.event');

            expect(count($config->getUserCacheInvalidationEvents()))->toBe($originalCount + 1);
            expect($config->getUserCacheInvalidationEvents())->toContain('custom.event');
        });

        it('does not duplicate events when adding', function () {
            $config = WebhookConfig::default();

            $config->addUserCacheInvalidationEvent('user.created');

            $events = $config->getUserCacheInvalidationEvents();
            $count = array_count_values($events)['user.created'] ?? 0;
            expect($count)->toBe(1);
        });

        it('can remove user cache invalidation events', function () {
            $config = WebhookConfig::default();

            $config->removeUserCacheInvalidationEvent('user.updated');

            expect($config->getUserCacheInvalidationEvents())->not->toContain('user.updated');
        });

        it('checks if event should invalidate user cache', function () {
            $config = WebhookConfig::default();

            expect($config->shouldInvalidateUserCache('user.created'))->toBeTrue();
            expect($config->shouldInvalidateUserCache('org.created'))->toBeFalse();
        });
    });

    describe('org cache invalidation events', function () {
        it('can set custom org cache invalidation events', function () {
            $config = WebhookConfig::default()->withOrgCacheInvalidationEvents(['org.created', 'org.updated']);

            expect($config->getOrgCacheInvalidationEvents())->toBe(['org.created', 'org.updated']);
        });

        it('can add org cache invalidation events', function () {
            $config = WebhookConfig::default();
            $originalCount = count($config->getOrgCacheInvalidationEvents());

            $config->addOrgCacheInvalidationEvent('custom.org.event');

            expect(count($config->getOrgCacheInvalidationEvents()))->toBe($originalCount + 1);
            expect($config->getOrgCacheInvalidationEvents())->toContain('custom.org.event');
        });

        it('can remove org cache invalidation events', function () {
            $config = WebhookConfig::default();

            $config->removeOrgCacheInvalidationEvent('org.updated');

            expect($config->getOrgCacheInvalidationEvents())->not->toContain('org.updated');
        });

        it('checks if event should invalidate org cache', function () {
            $config = WebhookConfig::default();

            expect($config->shouldInvalidateOrgCache('org.created'))->toBeTrue();
            expect($config->shouldInvalidateOrgCache('user.created'))->toBeFalse();
        });
    });

    describe('list cache invalidation', function () {
        it('can disable user list cache invalidation', function () {
            $config = WebhookConfig::default()->setInvalidateUserListCache(false);

            expect($config->shouldInvalidateUserListCache())->toBeFalse();
        });

        it('can disable org list cache invalidation', function () {
            $config = WebhookConfig::default()->setInvalidateOrgListCache(false);

            expect($config->shouldInvalidateOrgListCache())->toBeFalse();
        });

        it('defaults to invalidating list caches', function () {
            $config = WebhookConfig::default();

            expect($config->shouldInvalidateUserListCache())->toBeTrue();
            expect($config->shouldInvalidateOrgListCache())->toBeTrue();
        });
    });

    describe('cache key formats', function () {
        it('can set custom user cache key format', function () {
            $config = WebhookConfig::default()->withUserCacheKeyFormat('app:user:{id}:v2');

            expect($config->getUserCacheKeyFormat())->toBe('app:user:{id}:v2');
        });

        it('can set custom org cache key format', function () {
            $config = WebhookConfig::default()->withOrgCacheKeyFormat('app:org:{id}:v2');

            expect($config->getOrgCacheKeyFormat())->toBe('app:org:{id}:v2');
        });

        it('returns null for default cache key formats', function () {
            $config = WebhookConfig::default();

            expect($config->getUserCacheKeyFormat())->toBeNull();
            expect($config->getOrgCacheKeyFormat())->toBeNull();
        });
    });

    describe('signature verification', function () {
        it('enables signature verification by default', function () {
            $config = WebhookConfig::default();

            expect($config->shouldVerifySignatures())->toBeTrue();
        });

        it('can disable signature verification', function () {
            $config = WebhookConfig::default()->setVerifySignatures(false);

            expect($config->shouldVerifySignatures())->toBeFalse();
        });

        it('can set signing secret', function () {
            $config = WebhookConfig::default()->withSigningSecret('whsec_test123');

            expect($config->getSigningSecret())->toBe('whsec_test123');
        });

        it('returns null for signing secret by default', function () {
            $config = WebhookConfig::default();

            expect($config->getSigningSecret())->toBeNull();
        });
    });

    describe('timestamp tolerance', function () {
        it('defaults to 300 seconds', function () {
            $config = WebhookConfig::default();

            expect($config->getTimestampTolerance())->toBe(300);
        });

        it('can set custom timestamp tolerance', function () {
            $config = WebhookConfig::default()->setTimestampTolerance(600);

            expect($config->getTimestampTolerance())->toBe(600);
        });

        it('accepts zero tolerance', function () {
            $config = WebhookConfig::default()->setTimestampTolerance(0);

            expect($config->getTimestampTolerance())->toBe(0);
        });

        it('accepts negative tolerance (for testing)', function () {
            $config = WebhookConfig::default()->setTimestampTolerance(-100);

            expect($config->getTimestampTolerance())->toBe(-100);
        });
    });

    describe('fluent interface', function () {
        it('allows method chaining', function () {
            $config = WebhookConfig::default()
                ->setVerifySignatures(false)
                ->withSigningSecret('whsec_test')
                ->setTimestampTolerance(600)
                ->setInvalidateUserListCache(false)
                ->setInvalidateOrgListCache(false);

            expect($config->shouldVerifySignatures())->toBeFalse();
            expect($config->getSigningSecret())->toBe('whsec_test');
            expect($config->getTimestampTolerance())->toBe(600);
            expect($config->shouldInvalidateUserListCache())->toBeFalse();
            expect($config->shouldInvalidateOrgListCache())->toBeFalse();
        });

        it('returns self from all setters', function () {
            $config = WebhookConfig::default();

            expect($config->setVerifySignatures(false))->toBe($config);
            expect($config->withSigningSecret('test'))->toBe($config);
            expect($config->setTimestampTolerance(100))->toBe($config);
            expect($config->withUserCacheKeyFormat('test'))->toBe($config);
            expect($config->withOrgCacheKeyFormat('test'))->toBe($config);
        });
    });

    describe('toArray', function () {
        it('converts config to array', function () {
            $config = WebhookConfig::default()->withSigningSecret('whsec_test123')->setVerifySignatures(false);

            $array = $config->toArray();

            expect($array)->toBeArray();
            expect($array['verify_signatures'])->toBeFalse();
            expect($array['signing_secret'])->toBe('***'); // masked
            expect($array['timestamp_tolerance_seconds'])->toBe(300);
        });

        it('masks signing secret in array output', function () {
            $config = WebhookConfig::default()->withSigningSecret('whsec_longsecret123456');

            $array = $config->toArray();

            expect($array['signing_secret'])->toBe('***');
            expect($array['signing_secret'])->not->toContain('whsec');
        });

        it('returns null for signing secret when not set', function () {
            $config = WebhookConfig::default();

            $array = $config->toArray();

            expect($array['signing_secret'])->toBeNull();
        });

        it('includes cache invalidation events in array', function () {
            $config = WebhookConfig::default()->withUserCacheInvalidationEvents(['user.created']);

            $array = $config->toArray();

            expect($array['user_cache_invalidation_events'])->toBe(['user.created']);
        });

        it('includes cache key formats in array', function () {
            $config = WebhookConfig::default()
                ->withUserCacheKeyFormat('app:user:{id}')
                ->withOrgCacheKeyFormat('app:org:{id}');

            $array = $config->toArray();

            expect($array['user_cache_key_format'])->toBe('app:user:{id}');
            expect($array['org_cache_key_format'])->toBe('app:org:{id}');
        });
    });

    describe('common usage patterns', function () {
        it('supports development mode with signature verification disabled', function () {
            $config = WebhookConfig::default()->setVerifySignatures(false);

            expect($config->shouldVerifySignatures())->toBeFalse();
        });

        it('supports custom cache key scheme', function () {
            $config = WebhookConfig::default()
                ->withUserCacheKeyFormat('app:user:{user_id}')
                ->withOrgCacheKeyFormat('app:org:{org_id}');

            expect($config->getUserCacheKeyFormat())->toContain('user_id');
            expect($config->getOrgCacheKeyFormat())->toContain('org_id');
        });

        it('supports disabling specific event cache invalidation', function () {
            $config = WebhookConfig::default()
                ->removeUserCacheInvalidationEvent('user.updated')
                ->removeOrgCacheInvalidationEvent('org.updated');

            expect($config->shouldInvalidateUserCache('user.updated'))->toBeFalse();
            expect($config->shouldInvalidateOrgCache('org.updated'))->toBeFalse();
        });

        it('supports strict timestamp checking', function () {
            $config = WebhookConfig::default()->setTimestampTolerance(60); // 1 minute

            expect($config->getTimestampTolerance())->toBe(60);
        });
    });
});
