## What and why

<!-- Short description of the change and the reason for it. Link issues: Closes #123 -->

## Checklist

- [ ] `docker compose run --rm web composer qa` passes locally
- [ ] Tests added or updated
- [ ] `CHANGELOG.md` updated (user-visible changes)
- [ ] Works without the console (logic in `Application`, console/admin are thin adapters)
- [ ] Layer boundaries respected (`composer deptrac`)
