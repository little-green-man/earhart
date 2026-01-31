# Release Checklist

This document guides you through releasing a new version of Earhart. Most of the process is automated via GitHub Actions—you just need to create a Git tag.

## Quick Start (Recommended)

### 1. Prepare Your Release

Update the version number and changelog:

```shell
# Edit composer.json and change the "version" field
nano composer.json

# Edit CHANGELOG.md and add a new section for your version
nano CHANGELOG.md
```

### 2. Commit Your Changes

```shell
git add composer.json CHANGELOG.md
git commit -m "chore: bump version to X.Y.Z"
```

### 3. Create and Push Tag

```shell
git tag -a vX.Y.Z -m "Release version X.Y.Z"
git push origin main
git push origin vX.Y.Z
```

**That's it!** GitHub Actions will automatically:
- ✅ Validate that your tag version matches `composer.json`
- ✅ Create a GitHub Release with auto-generated release notes
- ✅ Make the release available at https://github.com/little-green-man/earhart/releases

## Before You Release

Make sure your code is ready by running the full test suite locally:

```shell
./vendor/bin/pest              # Run tests
./vendor/bin/phpstan analyse   # Static analysis
./vendor/bin/pint --test       # Code style check
```

If any check fails, fix the issues and commit them before creating the tag.

## Automate Quality Checks (Optional)

You can automatically run quality checks before pushing tags using a Git pre-push hook. This prevents accidental bad releases.

### Setup (One-Time)

Configure Git to use the hooks directory:

```shell
git config core.hooksPath .githooks
chmod +x .githooks/pre-push
```

### How It Works

Now whenever you push a version tag (e.g., `git push origin vX.Y.Z`), the hook will automatically:
- ✅ Verify the tag version matches `composer.json`
- ✅ Run Pest tests
- ✅ Run Larastan static analysis
- ✅ Check code style with Pint
- ❌ Block the push if any check fails

The hook only runs for version tags (v*.*.*)—regular pushes are unaffected.

### Bypass the Hook (Emergency Only)

If you need to push without running checks:

```shell
git push --no-verify origin vX.Y.Z
```

Use this only in emergencies and investigate why checks are failing.

## Troubleshooting

### Version Mismatch Error

**Problem**: GitHub Actions fails with "Tag version doesn't match composer.json"

**Solution**:
```shell
# Delete the local tag
git tag -d vX.Y.Z

# Update composer.json version
nano composer.json

# Recreate the tag
git add composer.json
git commit --amend --no-edit
git tag -a vX.Y.Z -m "Release version X.Y.Z"
git push origin vX.Y.Z
```

### Undo a Release

```shell
# Delete local tag
git tag -d vX.Y.Z

# Delete remote tag
git push origin --delete vX.Y.Z
```

### Tests Fail During Pre-Push Hook

The hook prevents pushing if tests fail. Fix the issues:

```shell
# Fix the failing code
# Then run checks locally to verify
./vendor/bin/pest
./vendor/bin/phpstan analyse
./vendor/bin/pint --test

# Delete and recreate the tag
git tag -d vX.Y.Z
git tag -a vX.Y.Z -m "Release version X.Y.Z"
git push origin vX.Y.Z
```

## Version Numbering

Follow [Semantic Versioning](https://semver.org/):

- **MAJOR** (X.0.0): Breaking changes
- **MINOR** (0.X.0): New features, backwards compatible
- **PATCH** (0.0.X): Bug fixes

Example: If the current version is 1.5.0:
- New feature? → 1.6.0
- Bug fix? → 1.5.1
- Breaking change? → 2.0.0

## CHANGELOG Format

Keep `CHANGELOG.md` organized and consistent. Each release should have:

```markdown
## [X.Y.Z] - YYYY-MM-DD

### Added
- New feature description

### Changed
- Change description

### Fixed
- Bug fix description

### Removed
- Removed feature description
```

Only include sections that apply to your release. See [CHANGELOG.md](./CHANGELOG.md) for examples.

## Release Notes

GitHub Actions automatically generates release notes from your commit messages. To get better notes:

- Use conventional commits (e.g., `feat:`, `fix:`, `chore:`, `docs:`)
- Reference issues and PRs in commit messages (e.g., `fixes #123`)
- Keep commit messages descriptive

Example: `feat: add webhook signature verification (fixes #45)`

## Summary

**Manual process:**
1. Update `composer.json` version
2. Update `CHANGELOG.md`
3. Commit changes
4. Create and push tag
5. GitHub Actions creates the release

**With optional git hooks:**
- Same as above, but pre-push hook automatically validates everything before allowing the push
- Prevents accidental bad releases

**GitHub Actions automatically handles:**
- Version validation
- Release creation
- Auto-generated release notes