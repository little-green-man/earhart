<?php

namespace LittleGreenMan\Earhart\Tests\Unit\Query;

use LittleGreenMan\Earhart\PropelAuth\PaginatedResult;
use LittleGreenMan\Earhart\Query\QueryBuilder;
use LittleGreenMan\Earhart\Services\OrganisationService;
use LittleGreenMan\Earhart\Services\UserService;
use LittleGreenMan\Earhart\Tests\TestCase;
use Mockery;

class QueryBuilderTest extends TestCase
{
    /** @var UserService&\Mockery\MockInterface */
    private UserService $userService;

    /** @var OrganisationService&\Mockery\MockInterface */
    private OrganisationService $orgService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->userService = Mockery::mock(UserService::class);
        $this->orgService = Mockery::mock(OrganisationService::class);
    }

    /**
     * Test creating a user query builder
     */
    public function test_create_user_query_builder()
    {
        $builder = QueryBuilder::users($this->userService);

        $this->assertInstanceOf(QueryBuilder::class, $builder);
        $params = $builder->getParams();
        $this->assertEquals('users', $params['type']);
        $this->assertNull($params['search']);
        $this->assertEquals(0, $params['pageNumber']);
        $this->assertEquals(10, $params['pageSize']);
    }

    /**
     * Test creating an organisation query builder
     */
    public function test_create_org_query_builder()
    {
        $builder = QueryBuilder::orgs($this->orgService);

        $this->assertInstanceOf(QueryBuilder::class, $builder);
        $params = $builder->getParams();
        $this->assertEquals('orgs', $params['type']);
        $this->assertEquals(0, $params['pageNumber']);
        $this->assertEquals(10, $params['pageSize']);
    }

    /**
     * Test adding a search term
     */
    public function test_search_term()
    {
        $builder = QueryBuilder::users($this->userService)->search('john@example.com');

        $params = $builder->getParams();
        $this->assertEquals('john@example.com', $params['search']);
    }

    /**
     * Test setting order by
     */
    public function test_order_by()
    {
        $builder = QueryBuilder::users($this->userService)->orderBy('CREATED_AT_ASC');

        $params = $builder->getParams();
        $this->assertEquals('CREATED_AT_ASC', $params['orderBy']);
    }

    /**
     * Test setting pagination
     */
    public function test_paginate()
    {
        $builder = QueryBuilder::users($this->userService)->paginate(2, 20);

        $params = $builder->getParams();
        $this->assertEquals(2, $params['pageNumber']);
        $this->assertEquals(20, $params['pageSize']);
    }

    /**
     * Test setting page number
     */
    public function test_page()
    {
        $builder = QueryBuilder::users($this->userService)->page(5);

        $params = $builder->getParams();
        $this->assertEquals(5, $params['pageNumber']);
    }

    /**
     * Test setting page size
     */
    public function test_limit()
    {
        $builder = QueryBuilder::users($this->userService)->limit(50);

        $params = $builder->getParams();
        $this->assertEquals(50, $params['pageSize']);
    }

    /**
     * Test chaining multiple query parameters
     */
    public function test_chaining_parameters()
    {
        $builder = QueryBuilder::users($this->userService)
            ->search('admin')
            ->orderBy('CREATED_AT_DESC')
            ->page(1)
            ->limit(25);

        $params = $builder->getParams();
        $this->assertEquals('admin', $params['search']);
        $this->assertEquals('CREATED_AT_DESC', $params['orderBy']);
        $this->assertEquals(1, $params['pageNumber']);
        $this->assertEquals(25, $params['pageSize']);
    }

    /**
     * Test executing a user query
     */
    public function test_execute_user_query()
    {
        $paginatedResult = PaginatedResult::from(
            [
                'users' => [['user_id' => 'u1', 'email' => 'user@example.com']],
                'totalUsers' => 1,
                'currentPage' => 0,
                'pageSize' => 10,
                'hasMoreResults' => false,
            ],
            fn () => null,
        );

        $this->userService
            ->shouldReceive('queryUsers')
            ->with('john', 'CREATED_AT_DESC', 0, 10)
            ->once()
            ->andReturn($paginatedResult);

        $builder = QueryBuilder::users($this->userService)->search('john');

        $result = $builder->get();

        $this->assertInstanceOf(PaginatedResult::class, $result);
        $this->assertEquals(1, $result->totalItems);
    }

    /**
     * Test executing an organisation query
     */
    public function test_execute_org_query()
    {
        $paginatedResult = PaginatedResult::from(
            [
                'orgs' => [['org_id' => 'o1', 'displayName' => 'Acme']],
                'totalOrgs' => 1,
                'currentPage' => 0,
                'pageSize' => 10,
                'hasMoreResults' => false,
            ],
            fn () => null,
        );

        $this->orgService
            ->shouldReceive('queryOrganisations')
            ->with('CREATED_AT_DESC', 0, 10)
            ->once()
            ->andReturn($paginatedResult);

        $builder = QueryBuilder::orgs($this->orgService);
        $result = $builder->get();

        $this->assertInstanceOf(PaginatedResult::class, $result);
        $this->assertEquals(1, $result->totalItems);
    }

    /**
     * Test get first result
     */
    public function test_get_first_result()
    {
        $item = ['user_id' => 'u1', 'email' => 'first@example.com'];
        $paginatedResult = PaginatedResult::from(
            ['users' => [$item], 'totalUsers' => 100, 'currentPage' => 0, 'pageSize' => 1, 'hasMoreResults' => true],
            fn () => null,
        );

        $this->userService
            ->shouldReceive('queryUsers')
            ->with(null, 'CREATED_AT_DESC', 0, 1)
            ->once()
            ->andReturn($paginatedResult);

        $builder = QueryBuilder::users($this->userService);
        $first = $builder->first();

        $this->assertEquals('u1', $first['user_id']);
    }

    /**
     * Test get first result returns null when no results
     */
    public function test_get_first_result_returns_null_when_no_results()
    {
        $paginatedResult = PaginatedResult::from(
            ['users' => [], 'totalUsers' => 0, 'currentPage' => 0, 'pageSize' => 1, 'hasMoreResults' => false],
            fn () => null,
        );

        $this->userService
            ->shouldReceive('queryUsers')
            ->with(null, 'CREATED_AT_DESC', 0, 1)
            ->once()
            ->andReturn($paginatedResult);

        $builder = QueryBuilder::users($this->userService);
        $first = $builder->first();

        $this->assertNull($first);
    }

    /**
     * Test checking if results exist
     */
    public function test_exists()
    {
        $paginatedResult = PaginatedResult::from(
            [
                'users' => [['user_id' => 'u1', 'email' => 'last@example.com']],
                'totalUsers' => 1,
                'currentPage' => 0,
                'pageSize' => 1,
                'hasMoreResults' => false,
            ],
            fn () => null,
        );

        $this->userService
            ->shouldReceive('queryUsers')
            ->with('admin', 'CREATED_AT_DESC', 0, 1)
            ->once()
            ->andReturn($paginatedResult);

        $builder = QueryBuilder::users($this->userService)->search('admin');

        $this->assertTrue($builder->exists());
    }

    /**
     * Test exists returns false when no results
     */
    public function test_exists_returns_false_when_no_results()
    {
        $paginatedResult = PaginatedResult::from(
            ['users' => [], 'totalUsers' => 0, 'currentPage' => 0, 'pageSize' => 1, 'hasMoreResults' => false],
            fn () => null,
        );

        $this->userService
            ->shouldReceive('queryUsers')
            ->with('nonexistent', 'CREATED_AT_DESC', 0, 1)
            ->once()
            ->andReturn($paginatedResult);

        $builder = QueryBuilder::users($this->userService)->search('nonexistent');

        $this->assertFalse($builder->exists());
    }

    /**
     * Test get count
     */
    public function test_get_count()
    {
        $paginatedResult = PaginatedResult::from(
            ['users' => [], 'totalUsers' => 42, 'currentPage' => 0, 'pageSize' => 10, 'hasMoreResults' => false],
            fn () => null,
        );

        $this->userService
            ->shouldReceive('queryUsers')
            ->with(null, 'CREATED_AT_DESC', 0, 10)
            ->once()
            ->andReturn($paginatedResult);

        $builder = QueryBuilder::users($this->userService);
        $count = $builder->count();

        $this->assertEquals(42, $count);
    }

    /**
     * Test reset clears parameters
     */
    public function test_reset()
    {
        $builder = QueryBuilder::users($this->userService)
            ->search('john')
            ->orderBy('CREATED_AT_ASC')
            ->page(5)
            ->limit(50)
            ->reset();

        $params = $builder->getParams();
        $this->assertNull($params['search']);
        $this->assertNull($params['orderBy']);
        $this->assertEquals(0, $params['pageNumber']);
        $this->assertEquals(10, $params['pageSize']);
    }

    /**
     * Test cloning builder
     */
    public function test_clone()
    {
        $builder = QueryBuilder::users($this->userService)
            ->search('john')
            ->page(2)
            ->limit(25);

        $clone = $builder->clone();

        // Modify original
        $builder->search('jane')->page(5);

        // Clone should remain unchanged
        $cloneParams = $clone->getParams();
        $this->assertEquals('john', $cloneParams['search']);
        $this->assertEquals(2, $cloneParams['pageNumber']);

        // Original should be changed
        $originalParams = $builder->getParams();
        $this->assertEquals('jane', $originalParams['search']);
        $this->assertEquals(5, $originalParams['pageNumber']);
    }

    /**
     * Test negative page number is normalized to 0
     */
    public function test_negative_page_normalized()
    {
        $builder = QueryBuilder::users($this->userService)->page(-5);

        $params = $builder->getParams();
        $this->assertEquals(0, $params['pageNumber']);
    }

    /**
     * Test zero page size is normalized to 1
     */
    public function test_zero_page_size_normalized()
    {
        $builder = QueryBuilder::users($this->userService)->limit(0);

        $params = $builder->getParams();
        $this->assertEquals(1, $params['pageSize']);
    }

    /**
     * Test negative page size is normalized to 1
     */
    public function test_negative_page_size_normalized()
    {
        $builder = QueryBuilder::users($this->userService)->limit(-10);

        $params = $builder->getParams();
        $this->assertEquals(1, $params['pageSize']);
    }

    /**
     * Test get with all parameters set
     */
    public function test_get_with_all_parameters()
    {
        $paginatedResult = PaginatedResult::from(
            [
                'users' => [['user_id' => 'u1', 'email' => 'user@example.com']],
                'totalUsers' => 1,
                'currentPage' => 0,
                'pageSize' => 20,
                'hasMoreResults' => false,
            ],
            fn () => null,
        );

        $this->userService
            ->shouldReceive('queryUsers')
            ->with('admin@example.com', 'CREATED_AT_ASC', 3, 20)
            ->once()
            ->andReturn($paginatedResult);

        $builder = QueryBuilder::users($this->userService)
            ->search('admin@example.com')
            ->orderBy('CREATED_AT_ASC')
            ->paginate(3, 20);

        $result = $builder->get();

        $this->assertInstanceOf(PaginatedResult::class, $result);
    }

    /**
     * Test fluent interface returns self
     */
    public function test_fluent_interface()
    {
        $builder = QueryBuilder::users($this->userService);

        $result = $builder->search('test')->orderBy('CREATED_AT_DESC')->page(1)->limit(15);

        $this->assertInstanceOf(QueryBuilder::class, $result);
        $this->assertSame($builder, $result);
    }

    /**
     * Test multiple sequential queries with same builder
     */
    public function test_multiple_queries_with_same_builder()
    {
        $result1 = PaginatedResult::from(
            [
                'users' => [['user_id' => 'u1', 'email' => 'user1@example.com']],
                'totalUsers' => 2,
                'currentPage' => 0,
                'pageSize' => 10,
                'hasMoreResults' => false,
            ],
            fn () => null,
        );

        $result2 = PaginatedResult::from(
            [
                'users' => [['user_id' => 'u2', 'email' => 'user2@example.com']],
                'totalUsers' => 2,
                'currentPage' => 1,
                'pageSize' => 10,
                'hasMoreResults' => false,
            ],
            fn () => null,
        );

        $this->userService
            ->shouldReceive('queryUsers')
            ->times(2)
            ->andReturn($result1, $result2);

        $builder = QueryBuilder::users($this->userService);

        $firstQuery = $builder->page(0)->get();
        $this->assertEquals(0, $firstQuery->currentPage);

        $secondQuery = $builder->page(1)->get();
        $this->assertEquals(1, $secondQuery->currentPage);
    }

    /**
     * Test get params returns all parameters
     */
    public function test_get_params_returns_all()
    {
        $builder = QueryBuilder::users($this->userService)
            ->search('test')
            ->orderBy('CREATED_AT_ASC')
            ->page(2)
            ->limit(25);

        $params = $builder->getParams();

        $this->assertArrayHasKey('type', $params);
        $this->assertArrayHasKey('search', $params);
        $this->assertArrayHasKey('orderBy', $params);
        $this->assertArrayHasKey('pageNumber', $params);
        $this->assertArrayHasKey('pageSize', $params);
    }

    /**
     * Test org query uses correct service method
     */
    public function test_org_query_uses_correct_method()
    {
        $paginatedResult = PaginatedResult::from(
            ['orgs' => [], 'totalOrgs' => 0, 'currentPage' => 0, 'pageSize' => 10, 'hasMoreResults' => false],
            fn () => null,
        );

        $this->orgService
            ->shouldReceive('queryOrganisations')
            ->with('CREATED_AT_DESC', 0, 10)
            ->once()
            ->andReturn($paginatedResult);

        $builder = QueryBuilder::orgs($this->orgService);
        $builder->get();
    }

    /**
     * Test user query uses correct service method
     */
    public function test_user_query_uses_correct_method()
    {
        $paginatedResult = PaginatedResult::from(
            ['users' => [], 'totalUsers' => 0, 'currentPage' => 0, 'pageSize' => 10, 'hasMoreResults' => false],
            fn () => null,
        );

        $this->userService
            ->shouldReceive('queryUsers')
            ->with(null, 'CREATED_AT_DESC', 0, 10)
            ->once()
            ->andReturn($paginatedResult);

        $builder = QueryBuilder::users($this->userService);
        $builder->get();
    }

    /**
     * Test count when totalItems is zero
     */
    public function test_count_when_total_items_zero()
    {
        $paginatedResult = PaginatedResult::from(
            ['users' => [1, 2, 3], 'totalUsers' => 0, 'currentPage' => 0, 'pageSize' => 10, 'hasMoreResults' => false],
            fn () => null,
        );

        $this->userService
            ->shouldReceive('queryUsers')
            ->with(null, 'CREATED_AT_DESC', 0, 10)
            ->once()
            ->andReturn($paginatedResult);

        $builder = QueryBuilder::users($this->userService);
        $count = $builder->count();

        $this->assertEquals(0, $count);
    }
}
