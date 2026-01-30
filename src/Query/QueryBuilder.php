<?php

namespace LittleGreenMan\Earhart\Query;

use LittleGreenMan\Earhart\PropelAuth\PaginatedResult;
use LittleGreenMan\Earhart\Services\OrganisationService;
use LittleGreenMan\Earhart\Services\UserService;

/**
 * Fluent query builder for PropelAuth users and organisations.
 *
 * Provides a chainable API for constructing queries with filtering, pagination, and sorting.
 *
 * Usage for users:
 *   $builder = QueryBuilder::users($userService)
 *       ->search('john')
 *       ->orderBy('CREATED_AT_DESC')
 *       ->paginate(0, 10)
 *       ->get();
 *
 * Usage for orgs:
 *   $builder = QueryBuilder::orgs($orgService)
 *       ->paginate(0, 20)
 *       ->get();
 */
class QueryBuilder
{
    private ?string $searchTerm = null;

    private ?string $orderBy = null;

    private int $pageNumber = 0;

    private int $pageSize = 10;

    /**
     * Create a new user query builder.
     *
     * @param  UserService  $userService  The user service instance
     */
    public static function users(UserService $userService): self
    {
        $builder = new self;
        $builder->userService = $userService;
        $builder->type = 'users';

        return $builder;
    }

    /**
     * Create a new organisation query builder.
     *
     * @param  OrganisationService  $orgService  The organisation service instance
     */
    public static function orgs(OrganisationService $orgService): self
    {
        $builder = new self;
        $builder->orgService = $orgService;
        $builder->type = 'orgs';

        return $builder;
    }

    private ?UserService $userService = null;

    private ?OrganisationService $orgService = null;

    private string $type = 'users';

    /**
     * Add a search term to filter results.
     *
     * For users: searches by email or username
     * For orgs: searches by display name
     *
     * @param  string  $term  The search term
     */
    public function search(string $term): self
    {
        $this->searchTerm = $term;

        return $this;
    }

    /**
     * Set the sort order.
     *
     * Valid values: CREATED_AT_ASC, CREATED_AT_DESC
     * Default: CREATED_AT_DESC
     *
     * @param  string  $order  The sort order
     */
    public function orderBy(string $order): self
    {
        $this->orderBy = $order;

        return $this;
    }

    /**
     * Set pagination parameters.
     *
     * @param  int  $pageNumber  Zero-based page number (default: 0)
     * @param  int  $pageSize  Number of results per page (default: 10)
     */
    public function paginate(int $pageNumber = 0, int $pageSize = 10): self
    {
        $this->pageNumber = max(0, $pageNumber);
        $this->pageSize = max(1, $pageSize);

        return $this;
    }

    /**
     * Set the current page (zero-based).
     *
     * @param  int  $page  The page number
     */
    public function page(int $page): self
    {
        $this->pageNumber = max(0, $page);

        return $this;
    }

    /**
     * Set the page size.
     *
     * @param  int  $size  The number of results per page
     */
    public function limit(int $size): self
    {
        $this->pageSize = max(1, $size);

        return $this;
    }

    /**
     * Execute the query and get paginated results.
     *
     * @throws \LogicException If required service not configured
     */
    public function get(): PaginatedResult
    {
        if ($this->type === 'users') {
            if (! $this->userService) {
                throw new \LogicException('UserService must be provided to query users');
            }

            return $this->userService->queryUsers(
                emailOrUsername: $this->searchTerm,
                orderBy: $this->orderBy ?? 'CREATED_AT_DESC',
                pageNumber: $this->pageNumber,
                pageSize: $this->pageSize,
            );
        }

        if (! $this->orgService) {
            throw new \LogicException('OrganisationService must be provided to query organisations');
        }

        return $this->orgService->queryOrganisations(
            orderBy: $this->orderBy ?? 'CREATED_AT_DESC',
            pageNumber: $this->pageNumber,
            pageSize: $this->pageSize,
        );
    }

    /**
     * Get only the first item from results, or null if no results.
     */
    public function first(): mixed
    {
        $result = $this->limit(1)->get();

        return ! empty($result->items) ? $result->items[0] : null;
    }

    /**
     * Check if any results match the query.
     */
    public function exists(): bool
    {
        return $this->first() !== null;
    }

    /**
     * Get the total count of items matching the query.
     */
    public function count(): int
    {
        $result = $this->page(0)->get();

        return $result->totalItems ?? 0;
    }

    /**
     * Reset all query parameters to defaults.
     */
    public function reset(): self
    {
        $this->searchTerm = null;
        $this->orderBy = null;
        $this->pageNumber = 0;
        $this->pageSize = 10;

        return $this;
    }

    /**
     * Get current query parameters as an array.
     */
    public function getParams(): array
    {
        return [
            'type' => $this->type,
            'search' => $this->searchTerm,
            'orderBy' => $this->orderBy,
            'pageNumber' => $this->pageNumber,
            'pageSize' => $this->pageSize,
        ];
    }

    /**
     * Clone this builder for independent modifications.
     */
    public function clone(): self
    {
        $clone = new self;
        $clone->type = $this->type;
        $clone->searchTerm = $this->searchTerm;
        $clone->orderBy = $this->orderBy;
        $clone->pageNumber = $this->pageNumber;
        $clone->pageSize = $this->pageSize;
        $clone->userService = $this->userService;
        $clone->orgService = $this->orgService;

        return $clone;
    }
}
