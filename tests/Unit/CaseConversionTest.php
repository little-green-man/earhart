<?php

use LittleGreenMan\Earhart\Services\BaseApiService;
use LittleGreenMan\Earhart\Services\CacheService;

/**
 * @property TestApiService $service
 */

// Create a concrete implementation for testing
class TestApiService extends BaseApiService
{
    protected function executeHttpRequest(string $method, string $endpoint, array $data): array
    {
        // Mock implementation - not used in these tests
        return [];
    }

    // Expose protected methods for testing
    public function publicToSnakeCase(array $data): array
    {
        return $this->toSnakeCase($data);
    }

    public function publicToCamelCase(array $data): array
    {
        return $this->toCamelCase($data);
    }
}

beforeEach(function () {
    $this->service = new TestApiService(
        apiKey: 'test-api-key',
        authUrl: 'https://test.propelauth.com',
        cache: new CacheService(enabled: false, ttlMinutes: 60),
    );
});

describe('toSnakeCase', function () {
    test('converts simple camelCase keys to snake_case', function () {
        $input = [
            'firstName' => 'John',
            'lastName' => 'Doe',
            'emailAddress' => 'john@example.com',
        ];

        $result = $this->service->publicToSnakeCase($input);

        expect($result)->toBe([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email_address' => 'john@example.com',
        ]);
    });

    test('handles nested arrays recursively', function () {
        $input = [
            'userId' => '123',
            'userProfile' => [
                'firstName' => 'Jane',
                'lastName' => 'Smith',
                'contactInfo' => [
                    'emailAddress' => 'jane@example.com',
                    'phoneNumber' => '555-1234',
                ],
            ],
        ];

        $result = $this->service->publicToSnakeCase($input);

        expect($result)->toBe([
            'user_id' => '123',
            'user_profile' => [
                'first_name' => 'Jane',
                'last_name' => 'Smith',
                'contact_info' => [
                    'email_address' => 'jane@example.com',
                    'phone_number' => '555-1234',
                ],
            ],
        ]);
    });

    test('preserves already snake_case keys', function () {
        $input = [
            'first_name' => 'Bob',
            'last_name' => 'Jones',
        ];

        $result = $this->service->publicToSnakeCase($input);

        expect($result)->toBe([
            'first_name' => 'Bob',
            'last_name' => 'Jones',
        ]);
    });

    test('handles numeric suffixes correctly', function () {
        $input = [
            'mfaBase32EncodedSecret' => 'secret123',
            'oauth2Token' => 'token456',
        ];

        $result = $this->service->publicToSnakeCase($input);

        expect($result)->toBe([
            'mfa_base32_encoded_secret' => 'secret123',
            'oauth2_token' => 'token456',
        ]);
    });

    test('handles empty arrays', function () {
        $result = $this->service->publicToSnakeCase([]);

        expect($result)->toBe([]);
    });

    test('preserves non-array values', function () {
        $input = [
            'userName' => 'testuser',
            'isActive' => true,
            'loginCount' => 42,
            'lastLogin' => null,
            'metadata' => ['key' => 'value'],
        ];

        $result = $this->service->publicToSnakeCase($input);

        expect($result['user_name'])->toBe('testuser');
        expect($result['is_active'])->toBe(true);
        expect($result['login_count'])->toBe(42);
        expect($result['last_login'])->toBe(null);
        expect($result['metadata'])->toBe(['key' => 'value']);
    });

    test('handles PropelAuth specific parameters', function () {
        $input = [
            'userId' => 'user123',
            'orgId' => 'org456',
            'requireEmailConfirmation' => true,
            'durationInMinutes' => 60,
            'activeOrgId' => 'org789',
            'createIfNotExists' => false,
        ];

        $result = $this->service->publicToSnakeCase($input);

        expect($result)->toBe([
            'user_id' => 'user123',
            'org_id' => 'org456',
            'require_email_confirmation' => true,
            'duration_in_minutes' => 60,
            'active_org_id' => 'org789',
            'create_if_not_exists' => false,
        ]);
    });
});

describe('toCamelCase', function () {
    test('converts simple snake_case keys to camelCase', function () {
        $input = [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email_address' => 'john@example.com',
        ];

        $result = $this->service->publicToCamelCase($input);

        expect($result)->toBe([
            'firstName' => 'John',
            'lastName' => 'Doe',
            'emailAddress' => 'john@example.com',
        ]);
    });

    test('handles nested arrays recursively', function () {
        $input = [
            'user_id' => '123',
            'user_profile' => [
                'first_name' => 'Jane',
                'last_name' => 'Smith',
                'contact_info' => [
                    'email_address' => 'jane@example.com',
                    'phone_number' => '555-1234',
                ],
            ],
        ];

        $result = $this->service->publicToCamelCase($input);

        expect($result)->toBe([
            'userId' => '123',
            'userProfile' => [
                'firstName' => 'Jane',
                'lastName' => 'Smith',
                'contactInfo' => [
                    'emailAddress' => 'jane@example.com',
                    'phoneNumber' => '555-1234',
                ],
            ],
        ]);
    });

    test('preserves already camelCase keys', function () {
        $input = [
            'firstName' => 'Bob',
            'lastName' => 'Jones',
        ];

        $result = $this->service->publicToCamelCase($input);

        expect($result)->toBe([
            'firstName' => 'Bob',
            'lastName' => 'Jones',
        ]);
    });

    test('handles empty arrays', function () {
        $result = $this->service->publicToCamelCase([]);

        expect($result)->toBe([]);
    });

    test('preserves non-array values', function () {
        $input = [
            'user_name' => 'testuser',
            'is_active' => true,
            'login_count' => 42,
            'last_login' => null,
            'metadata' => ['key' => 'value'],
        ];

        $result = $this->service->publicToCamelCase($input);

        expect($result['userName'])->toBe('testuser');
        expect($result['isActive'])->toBe(true);
        expect($result['loginCount'])->toBe(42);
        expect($result['lastLogin'])->toBe(null);
        expect($result['metadata'])->toBe(['key' => 'value']);
    });

    test('handles PropelAuth API response keys', function () {
        $input = [
            'user_id' => 'user123',
            'org_id' => 'org456',
            'email_confirmed' => true,
            'first_name' => 'Test',
            'last_name' => 'User',
            'picture_url' => 'https://example.com/pic.jpg',
            'has_password' => true,
            'mfa_enabled' => false,
            'created_at' => 1234567890,
            'last_active_at' => 1234567900,
        ];

        $result = $this->service->publicToCamelCase($input);

        expect($result)->toBe([
            'userId' => 'user123',
            'orgId' => 'org456',
            'emailConfirmed' => true,
            'firstName' => 'Test',
            'lastName' => 'User',
            'pictureUrl' => 'https://example.com/pic.jpg',
            'hasPassword' => true,
            'mfaEnabled' => false,
            'createdAt' => 1234567890,
            'lastActiveAt' => 1234567900,
        ]);
    });
});

describe('round-trip conversion', function () {
    test('camelCase to snake_case and back preserves values', function () {
        $original = [
            'firstName' => 'John',
            'lastName' => 'Doe',
            'emailAddress' => 'john@example.com',
            'metadata' => [
                'userId' => '123',
                'lastLogin' => 1234567890,
            ],
        ];

        $snakeCase = $this->service->publicToSnakeCase($original);
        $backToCamel = $this->service->publicToCamelCase($snakeCase);

        expect($backToCamel)->toBe($original);
    });

    test('snake_case to camelCase and back preserves values', function () {
        $original = [
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'email_address' => 'jane@example.com',
            'metadata' => [
                'user_id' => '456',
                'last_login' => 1234567890,
            ],
        ];

        $camelCase = $this->service->publicToCamelCase($original);
        $backToSnake = $this->service->publicToSnakeCase($camelCase);

        expect($backToSnake)->toBe($original);
    });
});

describe('edge cases', function () {
    test('handles single character keys', function () {
        $input = ['a' => 1, 'b' => 2];

        $snakeResult = $this->service->publicToSnakeCase($input);
        $camelResult = $this->service->publicToCamelCase($input);

        expect($snakeResult)->toBe(['a' => 1, 'b' => 2]);
        expect($camelResult)->toBe(['a' => 1, 'b' => 2]);
    });

    test('handles keys with consecutive capitals', function () {
        $input = [
            'HTTPResponse' => 'ok',
            'URLPath' => '/api',
        ];

        $result = $this->service->publicToSnakeCase($input);

        expect($result)->toBe([
            'http_response' => 'ok',
            'url_path' => '/api',
        ]);
    });

    test('handles deeply nested structures', function () {
        $input = [
            'level1' => [
                'level2' => [
                    'level3' => [
                        'level4' => [
                            'deepValue' => 'found',
                        ],
                    ],
                ],
            ],
        ];

        $result = $this->service->publicToSnakeCase($input);

        expect($result['level1']['level2']['level3']['level4']['deep_value'])->toBe('found');
    });

    test('handles arrays with numeric keys', function () {
        $input = [
            'items' => [
                ['itemName' => 'first'],
                ['itemName' => 'second'],
            ],
        ];

        $result = $this->service->publicToSnakeCase($input);

        expect($result['items'][0]['item_name'])->toBe('first');
        expect($result['items'][1]['item_name'])->toBe('second');
    });
});

describe('user-defined data preservation', function () {
    test('preserves keys in properties array', function () {
        $input = [
            'firstName' => 'John',
            'properties' => [
                'legacy_id' => '12345',
                'custom_field' => 'value',
                'user_metadata' => 'data',
            ],
        ];

        $result = $this->service->publicToSnakeCase($input);

        expect($result['first_name'])->toBe('John');
        expect($result['properties']['legacy_id'])->toBe('12345');
        expect($result['properties']['custom_field'])->toBe('value');
        expect($result['properties']['user_metadata'])->toBe('data');
    });

    test('preserves keys in metadata array', function () {
        $input = [
            'displayName' => 'Acme Corp',
            'metadata' => [
                'external_id' => 'ext-123',
                'billing_tier' => 'premium',
            ],
        ];

        $result = $this->service->publicToSnakeCase($input);

        expect($result['display_name'])->toBe('Acme Corp');
        expect($result['metadata']['external_id'])->toBe('ext-123');
        expect($result['metadata']['billing_tier'])->toBe('premium');
    });

    test('preserves keys in orgs array from API response', function () {
        $input = [
            'user_id' => '123',
            'orgs' => [
                [
                    'org_id' => 'org-1',
                    'user_role' => 'owner',
                ],
            ],
        ];

        $result = $this->service->publicToCamelCase($input);

        expect($result['userId'])->toBe('123');
        expect($result['orgs'][0]['org_id'])->toBe('org-1');
        expect($result['orgs'][0]['user_role'])->toBe('owner');
    });

    test('preserves nested user data in properties', function () {
        $input = [
            'email' => 'test@example.com',
            'properties' => [
                'user_settings' => [
                    'notification_prefs' => 'email',
                    'dark_mode' => true,
                ],
            ],
        ];

        $result = $this->service->publicToSnakeCase($input);

        expect($result['email'])->toBe('test@example.com');
        expect($result['properties']['user_settings']['notification_prefs'])->toBe('email');
        expect($result['properties']['user_settings']['dark_mode'])->toBe(true);
    });
});
