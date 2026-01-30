# Contributing to Earhart

Thank you for considering contributing to Earhart! This document provides guidelines and instructions for contributing to the project.

## Code of Conduct

Be respectful, inclusive, and constructive in all interactions. We're building a community where everyone feels welcome.

## Getting Started

### Prerequisites

- PHP 8.2 or higher
- Laravel 10.x or 11.x
- Composer
- Git

### Setting Up Your Development Environment

1. **Fork the repository**
   ```bash
   git clone https://github.com/your-username/earhart.git
   cd earhart
   ```

2. **Install dependencies**
   ```bash
   composer install
   ```

3. **Create a feature branch**
   ```bash
   git checkout -b feature/your-feature-name
   ```

## Development Workflow

### Before You Start

1. Check existing [GitHub Issues](https://github.com/little-green-man/earhart/issues) to avoid duplicate work
2. Open an issue for major features to discuss before implementation
3. For bug fixes, reference the issue number in your commit

### Making Changes

#### Code Style

- Follow PSR-12 coding standards
- Use strict types: `declare(strict_types=1);`
- Add PHPDoc comments for all public methods
- Use meaningful variable and function names

**Example:**
```php
<?php

declare(strict_types=1);

namespace LittleGreenMan\Earhart\Services;

use Illuminate\Support\Facades\Http;

class UserService
{
    /**
     * Fetch a user by ID with optional caching.
     *
     * @param string $userId The PropelAuth user ID
     * @param bool $fresh Bypass cache and fetch fresh data
     * @return UserData The user data object
     *
     * @throws InvalidUserException If user not found
     */
    public function getUser(string $userId, bool $fresh = false): UserData
    {
        // Implementation
    }
}
```

#### File Organization

```
src/
├── Controllers/          # HTTP controllers
├── Events/
│   └── PropelAuth/       # Webhook event classes
├── Exceptions/           # Custom exceptions
├── Middleware/           # Authentication middleware
├── Services/             # Business logic services
├── Webhooks/             # Webhook handling classes
└── ...
```

#### Naming Conventions

- **Classes**: PascalCase (e.g., `UserService`, `OrgCreated`)
- **Methods**: camelCase (e.g., `getUser`, `createOrganisation`)
- **Constants**: UPPER_SNAKE_CASE (e.g., `MAX_RETRIES`)
- **Properties**: camelCase (e.g., `$apiKey`, `$maxRetries`)

### Writing Tests

All code must include tests. We use [Pest](https://pestphp.com/) for testing.

#### Test File Location

```
tests/
├── Unit/                 # Unit tests
│   └── Services/
│   └── Webhooks/
├── Feature/              # Integration tests
│   └── Webhooks/
└── TestCase.php
```

#### Test Example

```php
<?php

namespace Tests\Unit\Services;

use LittleGreenMan\Earhart\Services\UserService;
use Tests\TestCase;

class UserServiceTest extends TestCase
{
    /**
     * @test
     */
    public function it_fetches_user_by_id()
    {
        $userService = app(UserService::class);
        
        $user = $userService->getUser('user_123');
        
        $this->assertNotNull($user);
        $this->assertEquals('user_123', $user->user_id);
    }
}
```

#### Running Tests

```bash
# Run all tests
./vendor/bin/pest

# Run specific test file
./vendor/bin/pest tests/Unit/Services/UserServiceTest.php

# Run with coverage
./vendor/bin/pest --coverage

# Watch mode for development
./vendor/bin/pest --watch
```

### Documentation

Update documentation for any user-facing changes:

1. **README.md**: Major features or usage changes
2. **CHANGELOG.md**: Add entry under "Unreleased" section
3. **Inline Comments**: Complex logic should have explanatory comments

#### Documentation Format

```php
/**
 * Create a magic link for passwordless login.
 *
 * This generates a one-time use link that allows users to authenticate
 * without entering their password. The link expires after the configured
 * duration (default 24 hours).
 *
 * @param string $email The user's email address
 * @param string|null $redirectUrl URL to redirect to after login
 * @param int|null $expiresInHours Hours until link expires (default 24)
 * @param bool $createIfNotExists Create user if they don't exist
 *
 * @return string The magic link URL
 *
 * @throws ValidationException If email is invalid
 * @throws RateLimitException If too many requests
 */
public function createMagicLink(
    string $email,
    ?string $redirectUrl = null,
    ?int $expiresInHours = 24,
    bool $createIfNotExists = false,
): string {
    // Implementation
}
```

## Submitting Changes

### Commit Messages

Use conventional commits format for clear, descriptive commits:

```
type(scope): subject

body (optional)

footer (optional)
```

**Types:**
- `feat`: New feature
- `fix`: Bug fix
- `docs`: Documentation changes
- `test`: Test additions or changes
- `refactor`: Code refactoring without feature changes
- `perf`: Performance improvements
- `chore`: Build, dependencies, or tooling

**Examples:**
```
feat(webhooks): add user.login event support

Implement UserLogin event class and update AuthWebhookController
to handle login webhooks with optional organization context.

Closes #123
```

```
fix(services): handle null response in user query

Add proper null checking in UserService::queryUsers to prevent
TypeError when API returns unexpected response structure.
```

### Pull Request Process

1. **Before pushing:**
   - Run tests: `./vendor/bin/pest`
   - Check code style: `./vendor/bin/pint --test` (if available)
   - Update CHANGELOG.md
   - Add/update documentation

2. **Create a descriptive PR:**
   - Title: Clear, concise summary
   - Description: What changed and why
   - References: Link related issues (#123)
   - Breaking changes: Clearly note any breaking changes

3. **PR Template:**
   ```markdown
   ## Description
   Brief description of changes

   ## Type of Change
   - [ ] Bug fix
   - [ ] New feature
   - [ ] Breaking change
   - [ ] Documentation update

   ## Changes Made
   - Specific change 1
   - Specific change 2

   ## Testing Done
   - How was this tested?
   - Any edge cases covered?

   ## Checklist
   - [ ] Tests pass locally
   - [ ] Documentation updated
   - [ ] No breaking changes (or clearly documented)
   - [ ] CHANGELOG.md updated
   - [ ] Code follows PSR-12

   ## Related Issues
   Closes #123
   ```

4. **After submission:**
   - Respond to review feedback promptly
   - Make requested changes in new commits (don't rewrite history)
   - Reference PR feedback in commit messages

## Code Review Guidelines

When reviewing code:

- **Be constructive**: Focus on the code, not the person
- **Ask questions**: "Have you considered...?" instead of "That's wrong"
- **Approve quickly**: Don't block on minor style issues
- **Test locally**: Pull and run tests before approving

### Approval Criteria

PRs must:
- ✅ Pass all tests (100% pass rate)
- ✅ Include tests for new functionality
- ✅ Follow PSR-12 coding standards
- ✅ Have clear commit messages
- ✅ Update CHANGELOG.md
- ✅ Have no breaking changes (or be clearly documented)
- ✅ Include documentation updates

## Adding New Features

### Adding a New Service Method

Example: Adding a new user query capability

1. **Add method to service**
   ```php
   public function getUsersByRole(string $role, int $pageSize = 100): PaginatedResult
   {
       // Implementation
   }
   ```

2. **Add unit tests**
   ```php
   public function it_queries_users_by_role()
   {
       $userService = app(UserService::class);
       
       $result = $userService->getUsersByRole('admin');
       
       $this->assertInstanceOf(PaginatedResult::class, $result);
   }
   ```

3. **Update documentation**
   - Add to README.md if it's user-facing
   - Add PHPDoc comments
   - Add CHANGELOG entry

### Adding a New Webhook Event

Example: Supporting a new PropelAuth event

1. **Create event class**
   ```php
   namespace LittleGreenMan\Earhart\Events\PropelAuth;

   class UserPasswordReset
   {
       use Dispatchable;

       public string $user_id;
       public string $reset_method;

       public function __construct(array $data)
       {
           $this->user_id = $data['user_id'];
           $this->reset_method = $data['reset_method'];
       }
   }
   ```

2. **Update AuthWebhookController**
   ```php
   match ($data['event_type']) {
       // ... existing events ...
       'user.password_reset' => UserPasswordReset::dispatch($data),
       default => null,
   };
   ```

3. **Add tests**
   ```php
   public function it_dispatches_user_password_reset_event()
   {
       Event::fake();
       
       $this->post('/webhooks', [
           'event_type' => 'user.password_reset',
           'user_id' => 'user_123',
           'reset_method' => 'email',
       ]);
       
       Event::assertDispatched(UserPasswordReset::class);
   }
   ```

4. **Update documentation**
   - Add to webhook events list in README.md
   - Update CHANGELOG.md

## Reporting Bugs

Found a bug? Please create a GitHub issue with:

1. **Clear title**: Describe the bug concisely
2. **Description**: What's happening vs. what should happen
3. **Steps to reproduce**: Exact steps to trigger the bug
4. **Environment**:
   - PHP version
   - Laravel version
   - Earhart version
5. **Logs/Errors**: Any error messages or stack traces
6. **Proposed fix** (optional): If you have an idea

**Example:**
```markdown
## Bug: Webhook signature verification fails with special characters

### Description
When a webhook payload contains special characters, the signature
verification throws a WebhookVerificationException.

### Steps to Reproduce
1. Send webhook with payload containing emoji: `{"name": "Test 🚀"}`
2. Signature verification should pass but fails

### Environment
- PHP 8.2
- Laravel 11
- Earhart 1.4.0

### Error
```
WebhookVerificationException: Signature verification failed
```

### Expected Behavior
Webhook should verify successfully regardless of payload content.
```

## Feature Requests

Have an idea? Please create a GitHub issue with:

1. **Clear title**: Describe the feature
2. **Use case**: Why is this needed?
3. **Proposed solution**: How should it work?
4. **Alternatives**: Any other approaches?

## Performance Considerations

When contributing code:

- **Use caching**: Leverage `CacheService` for repeated API calls
- **Paginate results**: Don't load thousands of records at once
- **Lazy load**: Don't fetch related data unless needed
- **Async operations**: Consider using queued jobs for heavy operations
- **Rate limiting**: Be aware of PropelAuth rate limits

Example:
```php
// Good: Uses caching
$user = $this->cache->get("user.{$userId}", 
    fn() => $this->fetchUserFromAPI($userId)
);

// Good: Uses pagination
$users = $this->queryUsers($searchTerm, pageSize: 50);

// Avoid: Fetching all users at once
$allUsers = $this->queryUsers(); // Could be thousands
```

## Security Guidelines

- **Never hardcode credentials**: Use environment variables
- **Validate input**: Check all user input
- **Escape output**: Prevent injection attacks
- **Hash passwords**: Use Laravel's Hash facade
- **Rate limit**: Implement rate limiting for API endpoints
- **HTTPS only**: Ensure webhook endpoints are HTTPS

Example:
```php
// Good: Validates and uses config
$secret = config('propelauth.webhook_secret');
if (empty($secret)) {
    throw new \RuntimeException('Webhook secret not configured');
}

// Good: Uses Laravel validation
$request->validate([
    'email' => 'required|email',
    'name' => 'required|string|max:255',
]);
```

## Getting Help

- **Documentation**: Check [README.md](README.md) and [API_PARITY_REVIEW.md](API_PARITY_REVIEW.md)
- **Issues**: Search [existing issues](https://github.com/little-green-man/earhart/issues)
- **Discussions**: Join project discussions
- **Email**: Contact maintainers if needed

## License

By contributing to Earhart, you agree that your contributions will be licensed under the MIT License (see LICENSE.md).

## Recognition

Contributors will be recognized in:
- CHANGELOG.md for each version
- GitHub contributors page
- Project documentation (with permission)

## Questions?

Don't hesitate to ask! Open an issue, start a discussion, or reach out to the maintainers.

---

**Thank you for contributing to Earhart! 🙏**

We appreciate your time, effort, and dedication to making this package better for everyone.