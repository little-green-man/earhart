<?php

namespace LittleGreenMan\Earhart\PropelAuth;

use Illuminate\Support\Collection;

class PaginatedResult
{
    /**
     * @param  array<mixed>  $items
     * @param  \Closure(int): self  $fetchNextPage
     */
    public function __construct(
        public array $items,
        public int $totalItems,
        public int $currentPage,
        public int $pageSize,
        public bool $hasMoreResults,
        protected \Closure $fetchNextPage,
    ) {}

    /**
     * Create a PaginatedResult from API response data.
     *
     * @param  array<string, mixed>  $data
     * @param  \Closure(int): self  $fetchNext
     */
    public static function from(array $data, \Closure $fetchNext): self
    {
        $items = $data['items'] ?? $data['users'] ?? $data['orgs'] ?? [];

        return new self(
            items: $items,
            totalItems: $data['totalUsers'] ?? $data['totalOrgs'] ?? count($items),
            currentPage: $data['currentPage'] ?? 0,
            pageSize: $data['pageSize'] ?? 10,
            hasMoreResults: $data['hasMoreResults'] ?? false,
            fetchNextPage: $fetchNext,
        );
    }

    /**
     * Check if there are more pages to fetch.
     */
    public function hasNextPage(): bool
    {
        return $this->hasMoreResults;
    }

    /**
     * Fetch the next page of results.
     */
    public function nextPage(): ?self
    {
        if (! $this->hasNextPage()) {
            return null;
        }

        return ($this->fetchNextPage)($this->currentPage + 1);
    }

    /**
     * Fetch all remaining pages and return as collection.
     *
     * @return Collection<int, mixed>
     */
    public function allPages(): Collection
    {
        $allItems = collect($this->items);
        $current = $this;

        while ($current->hasNextPage()) {
            $current = $current->nextPage();
            if ($current) {
                $allItems = $allItems->merge($current->items);
            }
        }

        return $allItems;
    }

    /**
     * Get items as a collection.
     *
     * @return Collection<int, mixed>
     */
    public function collection(): Collection
    {
        return collect($this->items);
    }

    /**
     * Convert to array.
     *
     * @return array<mixed>
     */
    public function toArray(): array
    {
        return $this->items;
    }

    /**
     * Get count of current page items.
     */
    public function count(): int
    {
        return count($this->items);
    }

    /**
     * Get the last page number.
     */
    public function lastPage(): int
    {
        return (int) ceil($this->totalItems / $this->pageSize);
    }

    /**
     * Check if this is the first page.
     */
    public function isFirstPage(): bool
    {
        return $this->currentPage === 0;
    }

    /**
     * Check if this is the last page.
     */
    public function isLastPage(): bool
    {
        return ! $this->hasMoreResults;
    }
}
