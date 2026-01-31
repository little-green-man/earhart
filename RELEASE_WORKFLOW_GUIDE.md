# Release Workflow Guide

This document summarizes the release workflow for Earhart and explains the improvements made.

## Overview

Releasing Earhart is a **3-step process**:

1. **Update** `composer.json` version and `CHANGELOG.md`
2. **Commit** your changes
3. **Push** a Git tag → GitHub Actions automatically creates the release

That's it! GitHub Actions handles everything else.

## The Release Process

### Step 1: Prepare Your Release

```bash
# Update the version in composer.json
nano composer.json
# Change: "version": "X.Y.Z"

# Update CHANGELOG.md with what changed
nano CHANGELOG.md
# Add a new section at the top for your version
```

### Step 2: Commit Your Changes

```bash
git add composer.json CHANGELOG.md
git commit -m "chore: bump version to X.Y.Z"
```

### Step 3: Create and Push Tag

```bash
git tag -a vX.Y.Z -m "Release version X.Y.Z"
git push origin main          # Push commits
git push origin vX.Y.Z        # Push the tag
```

### Automatic: GitHub Actions Creates Release

Once you push the tag, the `.github/workflows/release.yml` workflow automatically:

1. ✅ **Validates** that tag version matches `composer.json`
2. ✅ **Creates** a GitHub Release
3. ✅ **Generates** release notes from your commits
4. ✅ **Publishes** to https://github.com/little-green-man/earhart/releases

## Optional: Git Hooks for Quality Assurance

To prevent accidental bad releases, you can set up an optional pre-push Git hook that automatically runs quality checks before allowing you to push a release tag.

### Setup (One-Time)

```bash
git config core.hooksPath .githooks
chmod +x .githooks/pre-push
```

### How It Works

Now whenever you try to push a version tag:

```bash
git push origin vX.Y.Z
```

The hook automatically runs:
- ✅ Version consistency check (tag vs composer.json)
- ✅ Pest tests
- ✅ Larastan static analysis
- ✅ Pint code style check

If any check fails, the push is blocked and you see:

```
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
❌ Tests failed!
```

Fix the issues, commit them, delete and recreate the tag, then try pushing again.

### Bypass Hook (Emergency Only)

```bash
git push --no-verify origin vX.Y.Z
```

## What Changed

### Before
```bash
# Manual process with no automation
1. Check composer.json version
2. Update CHANGELOG.md
3. Manually run tests locally
4. Manually run phpstan
5. Manually run pint
6. Push tag
7. Manually create GitHub release
```

### After
```bash
# Simplified 3-step process + optional git hooks + automatic GitHub Actions
1. Update composer.json and CHANGELOG.md
2. Commit changes
3. Push tag
→ Optional: Git hook validates before push
→ Automatic: GitHub Actions creates release
```

### Improvements Made

| Area | Before | After |
|------|--------|-------|
| **Action Versions** | `checkout@v3`, deprecated `create-release@v1` | `checkout@v4`, modern `softprops/action-gh-release@v1` |
| **Release Notes** | Manual copy-paste | Auto-generated from commits |
| **Local Testing** | Manual & repeatable | Optional git hook automates it |
| **Push Protection** | None | Optional pre-push validation hook |
| **Documentation** | Minimal checklist | Comprehensive guide with troubleshooting |
| **Permissions** | Implicit | Explicit `permissions: contents: write` |
| **Error Messages** | Generic | Clear with emojis and guidance |

## Files Modified/Created

### Modified
- **`.github/workflows/release.yml`**: Modernized with latest actions, better error handling, auto-generated release notes
- **`RELEASE_CHECKLIST.md`**: Simplified and expanded with git hook setup instructions

### Created
- **`.githooks/pre-push`**: Optional git hook for pre-push validation
- **`RELEASE_WORKFLOW_GUIDE.md`**: This file - comprehensive guide

## Version Numbering

Follow [Semantic Versioning](https://semver.org/):

- **MAJOR** (1.0.0): Breaking changes
- **MINOR** (1.1.0): New features (backwards compatible)
- **PATCH** (1.0.1): Bug fixes

## CHANGELOG Format

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

## [Previous Version]
...
```

Only include sections that apply. See `CHANGELOG.md` for examples.

## Conventional Commits

To get better auto-generated release notes, use conventional commit prefixes:

- `feat:` → Features (in release notes)
- `fix:` → Bug fixes (in release notes)
- `perf:` → Performance (in release notes)
- `docs:` → Documentation
- `chore:` → Maintenance
- `test:` → Tests
- `style:` → Formatting
- `refactor:` → Code refactoring

Example: `feat: add webhook signature verification (closes #45)`

## Troubleshooting

### Version Mismatch Error

If GitHub Actions fails with "Tag version doesn't match composer.json":

```bash
git tag -d vX.Y.Z                    # Delete local tag
nano composer.json                   # Fix the version
git add composer.json
git commit --amend --no-edit         # Add to previous commit
git tag -a vX.Y.Z -m "Release..."   # Recreate tag
git push origin vX.Y.Z               # Push again
```

### Undo a Release

```bash
git tag -d vX.Y.Z                    # Delete local
git push origin --delete vX.Y.Z      # Delete remote
# Optionally delete on GitHub web UI
```

### Pre-Push Hook Blocks Release

The hook failed because a check didn't pass. Examples:

**Tests failed**: Run `./vendor/bin/pest` to debug, fix issues, commit, recreate tag

**Code style issues**: Run `./vendor/bin/pint --fix` to auto-fix, commit, recreate tag

**Static analysis failed**: Run `./vendor/bin/phpstan analyse` to debug, fix, commit, recreate tag

### Git Hook Not Running

Ensure it's set up:

```bash
git config core.hooksPath              # Should output: .githooks
ls -la .githooks/pre-push              # Should exist and be executable
```

If not set up yet:

```bash
git config core.hooksPath .githooks
chmod +x .githooks/pre-push
```

## FAQ

**Q: Do I have to use the git hook?**
A: No, it's optional. It helps catch issues before pushing, but you can skip it and rely on GitHub Actions validation.

**Q: What if tests pass locally but fail on GitHub?**
A: GitHub Actions runs in a clean environment. Check the Actions tab for the full error log. Usually it's a missing dependency or environment difference.

**Q: Can I release from a different branch?**
A: The workflow triggers on any branch when you push a tag. Best practice: always merge to `main` before tagging.

**Q: How do I see generated release notes?**
A: After the workflow completes, go to https://github.com/little-green-man/earhart/releases

**Q: Can I edit the release notes after creation?**
A: Yes, edit directly on the GitHub releases page.

## Quick Reference

```bash
# Full release workflow
nano composer.json                         # Update version
nano CHANGELOG.md                          # Document changes
git add composer.json CHANGELOG.md
git commit -m "chore: bump version to X.Y.Z"
git tag -a vX.Y.Z -m "Release version X.Y.Z"
git push origin main && git push origin vX.Y.Z
# ✅ GitHub Actions creates release automatically
```

```bash
# Setup optional git hooks (one-time)
git config core.hooksPath .githooks
chmod +x .githooks/pre-push
# Now git will validate before pushing tags
```

## Resources

- [Release Checklist](./RELEASE_CHECKLIST.md) - Detailed step-by-step guide
- [GitHub Actions Workflow](./github/workflows/release.yml) - See the automation
- [CHANGELOG](./CHANGELOG.md) - View version history