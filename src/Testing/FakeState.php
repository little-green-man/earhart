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

    /** @var list<array{orgId: string, inviteeEmail: string, role: ?string}> */
    public array $invites = [];

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

    public function userData(string $userId): UserData
    {
        $orgs = [];

        foreach ($this->members as $orgId => $members) {
            if (isset($members[$userId])) {
                $orgs[$orgId] = [
                    'orgId' => $orgId,
                    'orgName' => $this->orgs[$orgId]['name'],
                    'userRole' => $members[$userId],
                ];
            }
        }

        return UserData::fromArray($this->users[$userId] + ['orgs' => $orgs]);
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
