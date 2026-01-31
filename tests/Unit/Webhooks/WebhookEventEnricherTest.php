<?php

namespace LittleGreenMan\Earhart\Tests\Unit\Webhooks;

use LittleGreenMan\Earhart\Events\PropelAuth\UserCreated;
use LittleGreenMan\Earhart\Exceptions\InvalidOrgException;
use LittleGreenMan\Earhart\Exceptions\InvalidUserException;
use LittleGreenMan\Earhart\PropelAuth\OrganisationData;
use LittleGreenMan\Earhart\PropelAuth\UserData;
use LittleGreenMan\Earhart\Services\OrganisationService;
use LittleGreenMan\Earhart\Services\UserService;
use LittleGreenMan\Earhart\Tests\TestCase;
use LittleGreenMan\Earhart\Webhooks\WebhookEventEnricher;
use Mockery;

class WebhookEventEnricherTest extends TestCase
{
    private WebhookEventEnricher $enricher;

    /** @var UserService&\Mockery\MockInterface */
    private UserService $userService;

    /** @var OrganisationService&\Mockery\MockInterface */
    private OrganisationService $orgService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->userService = Mockery::mock(UserService::class);
        $this->orgService = Mockery::mock(OrganisationService::class);
        $this->enricher = new WebhookEventEnricher($this->userService, $this->orgService);
    }

    /**
     * Test enriching an event with user data
     */
    public function test_enrich_user_data_fetches_and_caches()
    {
        $userMock = Mockery::mock(UserData::class);
        $userMock->userId = 'user_123';
        $userMock->email = 'john@example.com';

        $this->userService
            ->shouldReceive('getUser')
            ->with('user_123', true)
            ->once()
            ->andReturn($userMock);

        $result = $this->enricher->getUserData('user_123');

        $this->assertInstanceOf(UserData::class, $result);
        $this->assertEquals('user_123', $result->userId);
    }

    /**
     * Test enriching an event with org data
     */
    public function test_enrich_org_data_fetches_and_caches()
    {
        $orgMock = Mockery::mock(OrganisationData::class);
        $orgMock->orgId = 'org_456';

        $this->orgService
            ->shouldReceive('getOrganisation')
            ->with('org_456', true)
            ->once()
            ->andReturn($orgMock);

        $result = $this->enricher->getOrgData('org_456');

        $this->assertInstanceOf(OrganisationData::class, $result);
        $this->assertEquals('org_456', $result->orgId);
    }

    /**
     * Test caching prevents duplicate API calls for same user
     */
    public function test_user_data_caching_prevents_duplicate_calls()
    {
        $userMock = Mockery::mock(UserData::class);
        $userMock->userId = 'user_123';

        $this->userService
            ->shouldReceive('getUser')
            ->with('user_123', true)
            ->once()
            ->andReturn($userMock);

        $result1 = $this->enricher->getUserData('user_123');
        $result2 = $this->enricher->getUserData('user_123');
        $result3 = $this->enricher->getUserData('user_123');

        $this->assertEquals('user_123', $result1->userId);
        $this->assertEquals('user_123', $result2->userId);
        $this->assertEquals('user_123', $result3->userId);
        $this->assertSame($result1, $result2);
        $this->assertSame($result2, $result3);
    }

    /**
     * Test caching prevents duplicate API calls for same org
     */
    public function test_org_data_caching_prevents_duplicate_calls()
    {
        $orgMock = Mockery::mock(OrganisationData::class);
        $orgMock->orgId = 'org_456';

        $this->orgService
            ->shouldReceive('getOrganisation')
            ->with('org_456', true)
            ->once()
            ->andReturn($orgMock);

        $result1 = $this->enricher->getOrgData('org_456');
        $result2 = $this->enricher->getOrgData('org_456');

        $this->assertEquals('org_456', $result1->orgId);
        $this->assertSame($result1, $result2);
    }

    /**
     * Test enriching event without user_id property returns null
     */
    public function test_enrich_user_data_returns_null_when_property_missing()
    {
        $event = new \stdClass;
        $event->org_id = 'org_456';

        $result = $this->enricher->enrichUserData($event);

        $this->assertNull($result);
        $this->userService->shouldNotHaveReceived('getUser');
    }

    /**
     * Test enriching event without org_id property returns null
     */
    public function test_enrich_org_data_returns_null_when_property_missing()
    {
        $event = new \stdClass;
        $event->user_id = 'user_123';

        $result = $this->enricher->enrichOrgData($event);

        $this->assertNull($result);
        $this->orgService->shouldNotHaveReceived('getOrganisation');
    }

    /**
     * Test enriching with custom property names
     */
    public function test_enrich_user_data_with_custom_property_name()
    {
        $event = new \stdClass;
        $event->created_by = 'user_789';

        $userMock = Mockery::mock(UserData::class);
        $userMock->userId = 'user_789';

        $this->userService
            ->shouldReceive('getUser')
            ->with('user_789', true)
            ->once()
            ->andReturn($userMock);

        $result = $this->enricher->enrichUserData($event, 'created_by');

        $this->assertInstanceOf(UserData::class, $result);
        $this->assertEquals('user_789', $result->userId);
    }

    /**
     * Test enriching with custom org property name
     */
    public function test_enrich_org_data_with_custom_property_name()
    {
        $event = new \stdClass;
        $event->parent_org = 'org_789';

        $orgMock = Mockery::mock(OrganisationData::class);
        $orgMock->orgId = 'org_789';

        $this->orgService
            ->shouldReceive('getOrganisation')
            ->with('org_789', true)
            ->once()
            ->andReturn($orgMock);

        $result = $this->enricher->enrichOrgData($event, 'parent_org');

        $this->assertInstanceOf(OrganisationData::class, $result);
        $this->assertEquals('org_789', $result->orgId);
    }

    /**
     * Test clear cache clears all cached data
     */
    public function test_clear_cache_removes_all_cached_data()
    {
        $userMock = Mockery::mock(UserData::class);
        $userMock->userId = 'user_123';

        $this->userService
            ->shouldReceive('getUser')
            ->with('user_123', true)
            ->twice()
            ->andReturn($userMock);

        $this->enricher->getUserData('user_123');
        $this->assertEquals(1, $this->enricher->getCachedUserCount());

        $this->enricher->clearCache();
        $this->assertEquals(0, $this->enricher->getCachedUserCount());

        $this->enricher->getUserData('user_123');
        $this->assertEquals(1, $this->enricher->getCachedUserCount());
    }

    /**
     * Test clear specific user cache
     */
    public function test_clear_user_cache_removes_specific_user()
    {
        $user1Mock = Mockery::mock(UserData::class);
        $user1Mock->userId = 'user_1';

        $user2Mock = Mockery::mock(UserData::class);
        $user2Mock->userId = 'user_2';

        $this->userService->shouldReceive('getUser')->andReturn($user1Mock, $user2Mock);

        $this->enricher->getUserData('user_1');
        $this->enricher->getUserData('user_2');
        $this->assertEquals(2, $this->enricher->getCachedUserCount());

        $this->enricher->clearUserCache('user_1');
        $this->assertEquals(1, $this->enricher->getCachedUserCount());
    }

    /**
     * Test clear specific org cache
     */
    public function test_clear_org_cache_removes_specific_org()
    {
        $org1Mock = Mockery::mock(OrganisationData::class);
        $org1Mock->orgId = 'org_1';

        $org2Mock = Mockery::mock(OrganisationData::class);
        $org2Mock->orgId = 'org_2';

        $this->orgService->shouldReceive('getOrganisation')->andReturn($org1Mock, $org2Mock);

        $this->enricher->getOrgData('org_1');
        $this->enricher->getOrgData('org_2');
        $this->assertEquals(2, $this->enricher->getCachedOrgCount());

        $this->enricher->clearOrgCache('org_1');
        $this->assertEquals(1, $this->enricher->getCachedOrgCount());
    }

    /**
     * Test getting cached user count
     */
    public function test_get_cached_user_count()
    {
        $userMock = Mockery::mock(UserData::class);
        $userMock->userId = 'user_123';

        $this->userService->shouldReceive('getUser')->andReturn($userMock);

        $this->assertEquals(0, $this->enricher->getCachedUserCount());

        $this->enricher->getUserData('user_123');
        $this->assertEquals(1, $this->enricher->getCachedUserCount());

        $this->enricher->getUserData('user_123');
        $this->assertEquals(1, $this->enricher->getCachedUserCount());
    }

    /**
     * Test getting cached org count
     */
    public function test_get_cached_org_count()
    {
        $orgMock = Mockery::mock(OrganisationData::class);
        $orgMock->orgId = 'org_456';

        $this->orgService->shouldReceive('getOrganisation')->andReturn($orgMock);

        $this->assertEquals(0, $this->enricher->getCachedOrgCount());

        $this->enricher->getOrgData('org_456');
        $this->assertEquals(1, $this->enricher->getCachedOrgCount());
    }

    /**
     * Test enriching event with null user_id value
     */
    public function test_enrich_user_data_returns_null_for_null_value()
    {
        $event = new \stdClass;
        $event->user_id = null;

        $result = $this->enricher->enrichUserData($event);

        $this->assertNull($result);
        $this->userService->shouldNotHaveReceived('getUser');
    }

    /**
     * Test enriching event with null org_id value
     */
    public function test_enrich_org_data_returns_null_for_null_value()
    {
        $event = new \stdClass;
        $event->org_id = null;

        $result = $this->enricher->enrichOrgData($event);

        $this->assertNull($result);
        $this->orgService->shouldNotHaveReceived('getOrganisation');
    }

    /**
     * Test user data fetching throws exception is propagated
     */
    public function test_enrich_user_data_throws_when_user_not_found()
    {
        $this->userService
            ->shouldReceive('getUser')
            ->with('invalid_user', true)
            ->once()
            ->andThrow(InvalidUserException::notFound('invalid_user'));

        $this->expectException(InvalidUserException::class);

        $this->enricher->getUserData('invalid_user');
    }

    /**
     * Test org data fetching throws exception is propagated
     */
    public function test_enrich_org_data_throws_when_org_not_found()
    {
        $this->orgService
            ->shouldReceive('getOrganisation')
            ->with('invalid_org', true)
            ->once()
            ->andThrow(InvalidOrgException::notFound('invalid_org'));

        $this->expectException(InvalidOrgException::class);

        $this->enricher->getOrgData('invalid_org');
    }

    /**
     * Test multiple users and orgs can be cached simultaneously
     */
    public function test_multiple_users_and_orgs_cached_simultaneously()
    {
        $user1Mock = Mockery::mock(UserData::class);
        $user1Mock->userId = 'user_1';

        $user2Mock = Mockery::mock(UserData::class);
        $user2Mock->userId = 'user_2';

        $org1Mock = Mockery::mock(OrganisationData::class);
        $org1Mock->orgId = 'org_1';

        $org2Mock = Mockery::mock(OrganisationData::class);
        $org2Mock->orgId = 'org_2';

        $this->userService->shouldReceive('getUser')->andReturn($user1Mock, $user2Mock);
        $this->orgService->shouldReceive('getOrganisation')->andReturn($org1Mock, $org2Mock);

        $this->enricher->getUserData('user_1');
        $this->enricher->getUserData('user_2');
        $this->enricher->getOrgData('org_1');
        $this->enricher->getOrgData('org_2');

        $this->assertEquals(2, $this->enricher->getCachedUserCount());
        $this->assertEquals(2, $this->enricher->getCachedOrgCount());
    }

    /**
     * Test enriching real event object
     */
    public function test_enrich_real_event_object()
    {
        $event = new UserCreated([
            'event_type' => 'user.created',
            'user_id' => 'user_123',
            'email' => 'john@example.com',
            'email_confirmed' => true,
            'first_name' => 'John',
            'last_name' => 'Doe',
            'username' => 'johndoe',
            'picture_url' => null,
        ]);

        $userMock = Mockery::mock(UserData::class);
        $userMock->userId = 'user_123';

        $this->userService
            ->shouldReceive('getUser')
            ->with('user_123', true)
            ->once()
            ->andReturn($userMock);

        $result = $this->enricher->enrichUserData($event);

        $this->assertInstanceOf(UserData::class, $result);
        $this->assertEquals('user_123', $result->userId);
    }
}
