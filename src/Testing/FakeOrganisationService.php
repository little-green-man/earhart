<?php

namespace LittleGreenMan\Earhart\Testing;

use Illuminate\Support\Str;
use LittleGreenMan\Earhart\Exceptions\InvalidOrgException;
use LittleGreenMan\Earhart\Exceptions\PropelAuthException;
use LittleGreenMan\Earhart\PropelAuth\OrganisationData;
use LittleGreenMan\Earhart\PropelAuth\PaginatedResult;
use LittleGreenMan\Earhart\Services\CacheService;
use LittleGreenMan\Earhart\Services\OrganisationService;

/**
 * In-memory OrganisationService used by Earhart::fake().
 */
class FakeOrganisationService extends OrganisationService
{
    use RecordsCalls;

    public function __construct(FakeState $state)
    {
        parent::__construct('fake-api-key', 'https://auth.example.test', new CacheService(false));
        $this->state = $state;
    }

    /**
     * Store an organisation without recording a call. Returns its attributes.
     *
     * @param  array<string, mixed>  $attributes  API fields in camelCase; the display name is `name`
     * @return array<string, mixed>
     */
    public function store(array $attributes = []): array
    {
        $orgId = $attributes['orgId'] ?? (string) Str::uuid();
        $this->state->members[$orgId] ??= [];

        return $this->state->orgs[$orgId] = array_merge([
            'orgId' => $orgId,
            'name' => "Organisation {$orgId}",
            'urlSafeOrgSlug' => $orgId,
            'canSetupSaml' => false,
            'isSamlConfigured' => false,
            'isSamlInTestMode' => false,
            'customRoleMappingName' => 'default',
            'createdAt' => now()->getTimestamp(),
            'metadata' => [],
            'isolated' => false,
        ], $attributes, ['orgId' => $orgId]);
    }

    /**
     * Add a user to an organisation without recording a call.
     */
    public function storeMember(string $orgId, string $userId, string $role = 'Member'): void
    {
        $this->state->members[$orgId][$userId] = $role;
    }

    public function getOrganisation(string $orgId, bool $fresh = false): OrganisationData
    {
        return $this->fake(__FUNCTION__, get_defined_vars(), function () use ($orgId) {
            $this->requireOrg($orgId);

            return OrganisationData::fromArray($this->state->orgs[$orgId]);
        });
    }

    public function queryOrganisations(
        ?string $orderBy = null,
        int $pageNumber = 0,
        int $pageSize = 100,
    ): PaginatedResult {
        return $this->fake(__FUNCTION__, get_defined_vars(), function () use ($orderBy, $pageNumber, $pageSize) {
            $items = array_values(array_map(fn (array $org) => OrganisationData::fromArray($org), $this->state->orgs));

            return PaginatedResult::from(
                $this->state->page($items, $pageNumber, $pageSize),
                fn (int $nextPage) => $this->queryOrganisations($orderBy, $nextPage, $pageSize),
            );
        });
    }

    public function getOrganisationUsers(string $orgId, int $pageSize = 100, int $pageNumber = 0): PaginatedResult
    {
        return $this->fake(__FUNCTION__, get_defined_vars(), function () use ($orgId, $pageSize, $pageNumber) {
            $this->requireOrg($orgId);

            $items = array_map(
                fn (string $userId) => $this->state->userData($userId),
                array_keys($this->state->members[$orgId]),
            );

            return PaginatedResult::from(
                $this->state->page($items, $pageNumber, $pageSize),
                fn (int $nextPage) => $this->getOrganisationUsers($orgId, $pageSize, $nextPage),
            );
        });
    }

    public function createOrganisation(string $name, ?string $slug = null, ?array $metadata = null): string
    {
        return $this->fake(__FUNCTION__, get_defined_vars(), fn () => $this->store(array_filter([
            'name' => $name,
            'urlSafeOrgSlug' => $slug,
            'metadata' => $metadata,
        ], fn ($v) => $v !== null))['orgId']);
    }

    public function updateOrganisation(string $orgId, ?string $name = null, ?array $metadata = null): bool
    {
        return $this->fake(__FUNCTION__, get_defined_vars(), fn () => $this->set($orgId, array_filter([
            'name' => $name,
            'metadata' => $metadata,
        ], fn ($v) => $v !== null)));
    }

    public function deleteOrganisation(string $orgId): bool
    {
        return $this->fake(__FUNCTION__, get_defined_vars(), function () use ($orgId) {
            $this->requireOrg($orgId);

            unset($this->state->orgs[$orgId], $this->state->members[$orgId]);
            $this->state->invites = array_values(array_filter($this->state->invites, fn (array $invite) => $invite['orgId'] !== $orgId));

            return true;
        });
    }

    public function addUserToOrganisation(string $orgId, string $userId, ?string $role = null): bool
    {
        return $this->fake(__FUNCTION__, get_defined_vars(), function () use ($orgId, $userId, $role) {
            if (! isset($this->state->orgs[$orgId], $this->state->users[$userId])) {
                throw $this->notFound(__FUNCTION__);
            }

            $this->storeMember($orgId, $userId, $role ?? 'Member');

            return true;
        });
    }

    public function inviteUserToOrganisation(string $orgId, string $email, ?string $role = null): bool
    {
        return $this->fake(__FUNCTION__, get_defined_vars(), function () use ($orgId, $email, $role) {
            $this->requireOrg($orgId);

            $this->state->invites[] = ['orgId' => $orgId, 'inviteeEmail' => $email, 'role' => $role];

            return true;
        });
    }

    public function removeUserFromOrganisation(string $orgId, string $userId): bool
    {
        return $this->fake(__FUNCTION__, get_defined_vars(), function () use ($orgId, $userId) {
            $this->requireMember($orgId, $userId, __FUNCTION__);

            unset($this->state->members[$orgId][$userId]);

            return true;
        });
    }

    public function changeUserRole(string $orgId, string $userId, string $role): bool
    {
        return $this->fake(__FUNCTION__, get_defined_vars(), function () use ($orgId, $userId, $role) {
            $this->requireMember($orgId, $userId, __FUNCTION__);

            $this->storeMember($orgId, $userId, $role);

            return true;
        });
    }

    public function getRoleMappings(): array
    {
        return $this->fake(__FUNCTION__, [], fn () => []);
    }

    public function subscribeOrgToRoleMapping(string $orgId, string $mappingId): bool
    {
        return $this->fake(__FUNCTION__, get_defined_vars(), fn () => $this->set($orgId, ['customRoleMappingName' => $mappingId]));
    }

    public function getPendingInvites(?string $orgId = null): PaginatedResult
    {
        return $this->fake(__FUNCTION__, get_defined_vars(), function () use ($orgId) {
            $items = array_values(array_filter($this->state->invites, fn (array $invite) => $orgId === null || $invite['orgId'] === $orgId));

            return PaginatedResult::from(
                ['items' => $items, 'totalUsers' => count($items), 'pageSize' => max(count($items), 1)],
                fn (int $nextPage) => $this->getPendingInvites($orgId),
            );
        });
    }

    public function revokePendingInvite(string $orgId, string $inviteeEmail): bool
    {
        return $this->fake(__FUNCTION__, get_defined_vars(), function () use ($orgId, $inviteeEmail) {
            $remaining = array_values(array_filter(
                $this->state->invites,
                fn (array $invite) => ! ($invite['orgId'] === $orgId && strcasecmp($invite['inviteeEmail'], $inviteeEmail) === 0),
            ));

            if (count($remaining) === count($this->state->invites)) {
                throw $this->notFound(__FUNCTION__);
            }

            $this->state->invites = $remaining;

            return true;
        });
    }

    public function allowOrgToSetupSAML(string $orgId): bool
    {
        return $this->fake(__FUNCTION__, get_defined_vars(), fn () => $this->set($orgId, ['canSetupSaml' => true]));
    }

    public function disallowOrgToSetupSAML(string $orgId): bool
    {
        return $this->fake(__FUNCTION__, get_defined_vars(), fn () => $this->set($orgId, ['canSetupSaml' => false]));
    }

    public function createSAMLConnectionLink(string $orgId): string
    {
        return $this->fake(__FUNCTION__, get_defined_vars(), function () use ($orgId) {
            $this->requireOrg($orgId);

            return "https://auth.example.test/saml/{$orgId}";
        });
    }

    public function fetchSAMLMetadata(string $orgId): string
    {
        return $this->fake(__FUNCTION__, get_defined_vars(), function () use ($orgId) {
            $this->requireOrg($orgId);

            return "<EntityDescriptor entityID=\"https://auth.example.test/saml/{$orgId}\"/>";
        });
    }

    public function setSAMLIdPMetadata(string $orgId, string $metadataXml): bool
    {
        return $this->fake(__FUNCTION__, get_defined_vars(), fn () => $this->set($orgId, [
            'isSamlConfigured' => true,
            'isSamlInTestMode' => true,
        ]));
    }

    public function enableSAMLConnection(string $orgId): bool
    {
        return $this->fake(__FUNCTION__, get_defined_vars(), fn () => $this->set($orgId, ['isSamlInTestMode' => false]));
    }

    public function deleteSAMLConnection(string $orgId): bool
    {
        return $this->fake(__FUNCTION__, get_defined_vars(), fn () => $this->set($orgId, [
            'isSamlConfigured' => false,
            'isSamlInTestMode' => false,
        ]));
    }

    public function migrateOrgToIsolated(string $orgId): bool
    {
        return $this->fake(__FUNCTION__, get_defined_vars(), fn () => $this->set($orgId, ['isolated' => true]));
    }

    protected function requireOrg(string $orgId): void
    {
        if (! isset($this->state->orgs[$orgId])) {
            throw InvalidOrgException::notFound($orgId);
        }
    }

    protected function requireMember(string $orgId, string $userId, string $method): void
    {
        if (! isset($this->state->members[$orgId][$userId])) {
            throw $this->notFound($method);
        }
    }

    protected function notFound(string $method): PropelAuthException
    {
        return PropelAuthException::forStatus(404, "PropelAuth API error: 404 on {$method} (fake)");
    }

    /**
     * @param  array<string, mixed>  $changes
     */
    protected function set(string $orgId, array $changes): bool
    {
        $this->requireOrg($orgId);
        $this->state->orgs[$orgId] = array_merge($this->state->orgs[$orgId], $changes);

        return true;
    }
}
