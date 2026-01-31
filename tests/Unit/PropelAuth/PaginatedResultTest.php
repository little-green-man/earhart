<?php

namespace LittleGreenMan\Earhart\Tests\Unit\PropelAuth;

use LittleGreenMan\Earhart\PropelAuth\PaginatedResult;

describe('PaginatedResult', function () {
    test('can be instantiated with all required data', function () {
        $items = ['item1', 'item2'];
        $callback = fn ($page) => null;

        $result = new PaginatedResult(
            items: $items,
            totalItems: 100,
            currentPage: 0,
            pageSize: 10,
            hasMoreResults: true,
            fetchNextPage: $callback,
        );

        expect($result->items)
            ->toBe($items)
            ->and($result->totalItems)
            ->toBe(100)
            ->and($result->currentPage)
            ->toBe(0)
            ->and($result->pageSize)
            ->toBe(10)
            ->and($result->hasMoreResults)
            ->toBeTrue();
    });

    test('creates from API response with users key', function () {
        $callback = fn ($page) => null;
        $data = [
            'users' => ['user1', 'user2'],
            'totalUsers' => 50,
            'currentPage' => 0,
            'pageSize' => 10,
            'hasMoreResults' => true,
        ];

        $result = PaginatedResult::from($data, $callback);

        expect($result->items)
            ->toBe(['user1', 'user2'])
            ->and($result->totalItems)
            ->toBe(50)
            ->and($result->currentPage)
            ->toBe(0);
    });

    test('creates from API response with orgs key', function () {
        $callback = fn ($page) => null;
        $data = [
            'orgs' => ['org1', 'org2'],
            'totalOrgs' => 25,
            'currentPage' => 1,
            'pageSize' => 20,
            'hasMoreResults' => false,
        ];

        $result = PaginatedResult::from($data, $callback);

        expect($result->items)
            ->toBe(['org1', 'org2'])
            ->and($result->totalItems)
            ->toBe(25)
            ->and($result->currentPage)
            ->toBe(1);
    });

    test('creates from API response with items key', function () {
        $callback = fn ($page) => null;
        $data = [
            'items' => ['item1', 'item2', 'item3'],
            'currentPage' => 2,
            'pageSize' => 15,
            'hasMoreResults' => true,
        ];

        $result = PaginatedResult::from($data, $callback);

        expect($result->items)->toBe(['item1', 'item2', 'item3']);
    });

    test('hasNextPage returns true when hasMoreResults is true', function () {
        $callback = fn ($page) => null;
        $result = new PaginatedResult(
            items: [],
            totalItems: 100,
            currentPage: 0,
            pageSize: 10,
            hasMoreResults: true,
            fetchNextPage: $callback,
        );

        expect($result->hasNextPage())->toBeTrue();
    });

    test('hasNextPage returns false when hasMoreResults is false', function () {
        $callback = fn ($page) => null;
        $result = new PaginatedResult(
            items: [],
            totalItems: 25,
            currentPage: 2,
            pageSize: 10,
            hasMoreResults: false,
            fetchNextPage: $callback,
        );

        expect($result->hasNextPage())->toBeFalse();
    });

    test('nextPage returns null when no more results', function () {
        $callback = fn ($page) => null;
        $result = new PaginatedResult(
            items: [],
            totalItems: 25,
            currentPage: 2,
            pageSize: 10,
            hasMoreResults: false,
            fetchNextPage: $callback,
        );

        expect($result->nextPage())->toBeNull();
    });

    test('nextPage returns next page when more results available', function () {
        $nextResult = new PaginatedResult(
            items: ['next1', 'next2'],
            totalItems: 100,
            currentPage: 1,
            pageSize: 10,
            hasMoreResults: true,
            fetchNextPage: fn () => null,
        );

        $callback = function ($page) use ($nextResult) {
            return $nextResult;
        };

        $result = new PaginatedResult(
            items: ['item1', 'item2'],
            totalItems: 100,
            currentPage: 0,
            pageSize: 10,
            hasMoreResults: true,
            fetchNextPage: $callback,
        );

        expect($result->nextPage())->toBe($nextResult);
    });

    test('collection returns items as collection', function () {
        $callback = fn ($page) => null;
        $items = ['item1', 'item2', 'item3'];
        $result = new PaginatedResult(
            items: $items,
            totalItems: 100,
            currentPage: 0,
            pageSize: 10,
            hasMoreResults: true,
            fetchNextPage: $callback,
        );

        $collection = $result->collection();

        expect($collection)
            ->toBeInstanceOf(\Illuminate\Support\Collection::class)
            ->and($collection->toArray())
            ->toBe($items);
    });

    test('toArray returns items array', function () {
        $callback = fn ($page) => null;
        $items = ['item1', 'item2'];
        $result = new PaginatedResult(
            items: $items,
            totalItems: 100,
            currentPage: 0,
            pageSize: 10,
            hasMoreResults: true,
            fetchNextPage: $callback,
        );

        expect($result->toArray())->toBe($items);
    });

    test('count returns number of items in current page', function () {
        $callback = fn ($page) => null;
        $result = new PaginatedResult(
            items: ['item1', 'item2', 'item3'],
            totalItems: 100,
            currentPage: 0,
            pageSize: 10,
            hasMoreResults: true,
            fetchNextPage: $callback,
        );

        expect($result->count())->toBe(3);
    });

    test('lastPage calculates last page number', function () {
        $callback = fn ($page) => null;
        $result = new PaginatedResult(
            items: [],
            totalItems: 105,
            currentPage: 0,
            pageSize: 10,
            hasMoreResults: true,
            fetchNextPage: $callback,
        );

        expect($result->lastPage())->toBe(10);
    });

    test('isFirstPage returns true for page 0', function () {
        $callback = fn ($page) => null;
        $result = new PaginatedResult(
            items: [],
            totalItems: 100,
            currentPage: 0,
            pageSize: 10,
            hasMoreResults: true,
            fetchNextPage: $callback,
        );

        expect($result->isFirstPage())->toBeTrue();
    });

    test('isFirstPage returns false for other pages', function () {
        $callback = fn ($page) => null;
        $result = new PaginatedResult(
            items: [],
            totalItems: 100,
            currentPage: 5,
            pageSize: 10,
            hasMoreResults: true,
            fetchNextPage: $callback,
        );

        expect($result->isFirstPage())->toBeFalse();
    });

    test('isLastPage returns true when no more results', function () {
        $callback = fn ($page) => null;
        $result = new PaginatedResult(
            items: [],
            totalItems: 25,
            currentPage: 2,
            pageSize: 10,
            hasMoreResults: false,
            fetchNextPage: $callback,
        );

        expect($result->isLastPage())->toBeTrue();
    });

    test('isLastPage returns false when more results available', function () {
        $callback = fn ($page) => null;
        $result = new PaginatedResult(
            items: [],
            totalItems: 100,
            currentPage: 2,
            pageSize: 10,
            hasMoreResults: true,
            fetchNextPage: $callback,
        );

        expect($result->isLastPage())->toBeFalse();
    });
});
