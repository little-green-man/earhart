# Release Checklist

1. Check composer.json version number
2. Update CHANGELOG.md
3. Run tests locally

```shell
./vendor/bin/pest 
./vendor/bin/phpstan analyse
./vendor/bin/pint --test
```

4. Create and push tag

```shell
git tag -a v1.2.3 -m "Release version 1.2.3"
git push origin v1.2.3
```
