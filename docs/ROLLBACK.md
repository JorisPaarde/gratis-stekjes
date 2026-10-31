# Rollback

1. **Code:** herdeploy een eerdere tag (production `workflow_dispatch`) of eerdere staging-commit.
2. **APP_KEY:** niet willekeurig vernieuwen — sessies breken. Bewaar in secrets of op de server.
3. **Data:** versleutelde DB-/storage-restore — zie `docs/backups.md` (decrypt → mysql/tar).
4. Geen secrets of hostnamen in git.
