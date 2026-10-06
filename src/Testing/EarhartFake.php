<?php

namespace LittleGreenMan\Earhart\Testing;

use LittleGreenMan\Earhart\Earhart;
use LittleGreenMan\Earhart\Exceptions\PropelAuthException;
use LittleGreenMan\Earhart\Facades\PropelAuth;
use LittleGreenMan\Earhart\PropelAuth\OrganisationData;
use LittleGreenMan\Earhart\PropelAuth\UserData;
use LittleGreenMan\Earhart\Services\OrganisationService;
use LittleGreenMan\Earhart\Services\UserService;
use PHPUnit\Framework\Assert as PHPUnit;

/**
 * In-memory stand-in for Earhart, installed by Earhart::fake().
 *
 * Users and organisations live in memory, every API call is recorded, and
 * nothing is sent to PropelAuth. Calls on missing users or organisations
 * throw the same exceptions as the real services.
 */
class EarhartFake extends Earhart
{
    protected FakeState $state;

    public function __construct()
    {
        parent::__construct(
            clientId: 'fake-client-id',
            clientSecret: 'fake-client-secret',
            callbackUrl: 'http://localhost/auth/callback',
            authUrl: 'https://auth.example.test',
            svixSecret: 'fake-svix-secret',
            apiKey: 'fake-api-key',
        );

        $this->state = new FakeState;
        $this->userService = new FakeUserService($this->state);
        $this->organisationService = new FakeOrganisationService($this->state);
    }

    // ============================================================
    // Seeding
    // ============================================================

    /**
     * Add a user. Attributes are UserData fields in camelCase; any left out get defaults.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function addUser(array $attributes = []): UserData
    {
        $user = $this->fakeUsers()->store($attributes);

        return $this->state->userData($user['userId']);
    }

    /**
     * Add an organisation. Attributes are API fields in camelCase (the display name is `name`).
     *
     * @param  array<string, mixed>  $attributes
     * @param  array<string, string>  $members  Roles keyed by user ID
     */
    public function addOrganisation(array $attributes = [], array $members = []): OrganisationData
    {
        $org = $this->fakeOrganisations()->store($attributes);

        foreach ($members as $userId => $role) {
            $this->fakeOrganisations()->storeMember($org['orgId'], $userId, $role);
        }

        return OrganisationData::fromArray($org);
    }

    /**
     * Add a user to an organisation.
     */
    public function addMember(string $orgId, string $userId, string $role = 'Member'): static
    {
        $this->fakeOrganisations()->storeMember($orgId, $userId, $role);

        return $this;
    }

    /**
     * Set the role hierarchy, highest first. Defaults to PropelAuth's Owner > Admin > Member.
     *
     * @param  list<string>  $roles
     */
    public function withRoleHierarchy(array $roles): static
    {
        $this->state->roleHierarchy = $roles;

        return $this;
    }

    /**
     * Set the permissions each role grants. A role also gets the permissions of roles below it.
     *
     * @param  array<string, list<string>>  $permissions  Keyed by role
     */
    public function withRolePermissions(array $permissions): static
    {
        $this->state->rolePermissions = $permissions;

        return $this;
    }

    /**
     * Issue an access token that validateToken(), verifyAccessToken() and VerifyPropelAuthUser accept.
     */
    public function issueToken(string $userId, ?string $token = null): string
    {
        return $this->fakeUsers()->issueToken($userId, $token);
    }

    // ============================================================
    // Failures
    // ============================================================

    /**
     * Make the next call(s) fail.
     *
     * @param  string  $target  A service class (UserService::class, OrganisationService::class) or a method name
     * @param  int|PropelAuthException  $failure  An HTTP status, mapped to the matching exception, or the exception to throw
     */
    public function failNext(string $target, int|PropelAuthException $failure = 500, int $times = 1): static
    {
        for ($i = 0; $i < $times; $i++) {
            $this->state->failures[$target][] = $failure;
        }

        return $this;
    }

    // ============================================================
    // Inspection
    // ============================================================

    /**
     * Every recorded call, including failed ones.
     *
     * @return list<array{service: class-string, method: string, args: array<string, mixed>, failed: bool}>
     */
    public function calls(): array
    {
        return $this->state->calls;
    }

    /**
     * Successful calls to a method, optionally filtered by a callback given the named arguments.
     *
     * @param  (\Closure(array<string, mixed>): bool)|null  $callback
     * @return list<array<string, mixed>> The named arguments of each matching call
     */
    public function recorded(string $method, ?\Closure $callback = null): array
    {
        $matches = [];

        foreach ($this->state->calls as $call) {
            if ($call['method'] === $method && ! $call['failed'] && ($callback === null || $callback($call['args']))) {
                $matches[] = $call['args'];
            }
        }

        return $matches;
    }

    // ============================================================
    // Assertions
    // ============================================================

    /**
     * @param  (\Closure(array<string, mixed>): bool)|null  $callback
     */
    public function assertCalled(string $method, ?\Closure $callback = null): static
    {
        PHPUnit::assertNotEmpty($this->recorded($method, $callback), "Expected [{$method}] to be called successfully, but it was not.");

        return $this;
    }

    public function assertCalledTimes(string $method, int $times): static
    {
        $count = count($this->recorded($method));

        PHPUnit::assertSame($times, $count, "Expected [{$method}] to be called {$times} times, but it was called {$count} times.");

        return $this;
    }

    /**
     * @param  (\Closure(array<string, mixed>): bool)|null  $callback
     */
    public function assertNotCalled(string $method, ?\Closure $callback = null): static
    {
        PHPUnit::assertEmpty($this->recorded($method, $callback), "Unexpected successful call to [{$method}].");

        return $this;
    }

    public function assertNothingCalled(): static
    {
        $methods = implode(', ', array_column($this->state->calls, 'method'));

        PHPUnit::assertEmpty($this->state->calls, "Expected no PropelAuth calls, but got: {$methods}.");

        return $this;
    }

    public function assertUserCreated(?string $email = null): static
    {
        PHPUnit::assertTrue(
            $this->recorded('createUser', fn ($args) => $email === null || strcasecmp($args['email'], $email) === 0) !== []
                || $this->recorded('migrateUserFromExternal', fn ($args) => $email === null || strcasecmp($args['email'], $email) === 0) !== [],
            $email === null ? 'Expected a user to be created.' : "Expected user [{$email}] to be created.",
        );

        return $this;
    }

    public function assertUserUpdated(string $userId): static
    {
        return $this->assertCalled('updateUser', fn ($args) => $args['userId'] === $userId);
    }

    public function assertUserDisabled(string $userId): static
    {
        return $this->assertCalled('disableUser', fn ($args) => $args['userId'] === $userId);
    }

    public function assertUserEnabled(string $userId): static
    {
        return $this->assertCalled('enableUser', fn ($args) => $args['userId'] === $userId);
    }

    public function assertUserDeleted(string $userId): static
    {
        return $this->assertCalled('deleteUser', fn ($args) => $args['userId'] === $userId);
    }

    public function assertUserLoggedOut(string $userId): static
    {
        return $this->assertCalled('logoutAllSessions', fn ($args) => $args['userId'] === $userId);
    }

    public function assertOrganisationCreated(?string $name = null): static
    {
        return $this->assertCalled('createOrganisation', fn ($args) => $name === null || $args['name'] === $name);
    }

    public function assertOrganisationUpdated(string $orgId): static
    {
        return $this->assertCalled('updateOrganisation', fn ($args) => $args['orgId'] === $orgId);
    }

    public function assertOrganisationDeleted(string $orgId): static
    {
        return $this->assertCalled('deleteOrganisation', fn ($args) => $args['orgId'] === $orgId);
    }

    public function assertUserAddedToOrganisation(string $orgId, string $userId, ?string $role = null): static
    {
        return $this->assertCalled('addUserToOrganisation', fn ($args) => $args['orgId'] === $orgId
            && $args['userId'] === $userId
            && ($role === null || $args['role'] === $role));
    }

    public function assertUserRemovedFromOrganisation(string $orgId, string $userId): static
    {
        return $this->assertCalled('removeUserFromOrganisation', fn ($args) => $args['orgId'] === $orgId && $args['userId'] === $userId);
    }

    public function assertUserInvitedToOrganisation(string $orgId, string $email): static
    {
        return $this->assertCalled('inviteUserToOrganisation', fn ($args) => $args['orgId'] === $orgId && strcasecmp($args['email'], $email) === 0);
    }

    protected function fakeUsers(): FakeUserService
    {
        /** @var FakeUserService */
        return $this->userService;
    }

    protected function fakeOrganisations(): FakeOrganisationService
    {
        /** @var FakeOrganisationService */
        return $this->organisationService;
    }

    /**
     * Install the fake in the container.
     *
     * @internal Use Earhart::fake() or PropelAuth::fake()
     */
    public function swap(): static
    {
        app()->instance('earhart', $this);
        app()->instance(UserService::class, $this->userService);
        app()->instance(OrganisationService::class, $this->organisationService);

        PropelAuth::clearResolvedInstance('earhart');

        return $this;
    }
}
