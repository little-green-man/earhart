<?php

namespace LittleGreenMan\Earhart\Testing;

use LittleGreenMan\Earhart\Exceptions\PropelAuthException;
use LittleGreenMan\Earhart\PropelAuth\UserData;

/**
 * In-memory PropelAuth data and call log shared by the fake services.
 *
 * @internal
 */
class FakeState
{
    /** @var array<string, array<string, mixed>> User attributes keyed by user ID */
    public array $users = [];

    /** @var array<string, array<string, mixed>> Organisation attributes keyed by org ID */
    public array $orgs = [];

    /** @var array<string, array<string, string>> Roles keyed by org ID, then user ID */
    public array $members = [];

    /** @var list<array<string, mixed>> Pending invites, in PropelAuth's (camelCase) shape */
    public array $invites = [];

    /**
     * Roles from highest to lowest. A role inherits every role below it,
     * matching PropelAuth's default Owner > Admin > Member.
     *
     * @var list<string>
     */
    public array $roleHierarchy = ['Owner', 'Admin', 'Member'];

    /** @var array<string, list<string>> Permissions keyed by role */
    public array $rolePermissions = [];

    /** @var array<string, string> User IDs keyed by access token */
    public array $tokens = [];

    /** @var list<array{service: class-string, method: string, args: array<string, mixed>, failed: bool}> */
    public array $calls = [];

    /** @var array<string, list<int|PropelAuthException>> Scripted failures keyed by service class or method name */
    public array $failures = [];

    /**
     * Take the next scripted failure for a call, preferring one scripted for the method.
     */
    public function nextFailure(string $service, string $method): ?PropelAuthException
    {
        foreach ([$method, $service] as $key) {
            if (! empty($this->failures[$key])) {
                $failure = array_shift($this->failures[$key]);

                return $failure instanceof PropelAuthException
                    ? $failure
                    : PropelAuthException::forStatus($failure, "PropelAuth API error: {$failure} on {$method} (fake)");
            }
        }

        return null;
    }

    public function userData(string $userId, bool $includeOrgs = true, ?string $roleInOrg = null): UserData
    {
        return UserData::fromArray($this->users[$userId] + [
            'orgIdToOrgInfo' => $includeOrgs ? $this->orgInfo($userId) : [],
            'roleInOrg' => $roleInOrg,
        ]);
    }

    /**
     * The user's memberships in PropelAuth's `org_id_to_org_info` shape.
     *
     * @return array<string, array<string, mixed>>
     */
    public function orgInfo(string $userId): array
    {
        $orgs = [];

        foreach ($this->members as $orgId => $members) {
            if (! isset($members[$userId])) {
                continue;
            }

            $role = $members[$userId];
            $position = array_search($role, $this->roleHierarchy, true);
            $inherited = $position === false ? [$role] : array_slice($this->roleHierarchy, $position);

            $orgs[$orgId] = [
                'org_id' => $orgId,
                'org_name' => $this->orgs[$orgId]['name'],
                'url_safe_org_name' => $this->orgs[$orgId]['urlSafeOrgSlug'],
                'org_metadata' => $this->orgs[$orgId]['metadata'],
                'user_role' => $role,
                'inherited_user_roles_plus_current_role' => $inherited,
                'user_permissions' => array_values(array_unique(array_merge(
                    ...array_map(fn (string $r) => $this->rolePermissions[$r] ?? [], $inherited),
                ))),
                'additional_roles' => [],
            ];
        }

        return $orgs;
    }

    /**
     * Slice a list into the shape PaginatedResult::from() expects.
     *
     * @param  list<mixed>  $items
     * @return array<string, mixed>
     */
    public function page(array $items, int $pageNumber, int $pageSize): array
    {
        return [
            'items' => array_slice($items, $pageNumber * $pageSize, $pageSize),
            'totalUsers' => count($items),
            'currentPage' => $pageNumber,
            'pageSize' => $pageSize,
            'hasMoreResults' => ($pageNumber + 1) * $pageSize < count($items),
        ];
    }
}
