- 👋 Hi, I’m @mert6148
- 👀 I’m interested in ...
- 🌱 I’m currently learning ...
- 💞️ I’m looking to collaborate on ...
- 📫 How to reach me ...

[![CI](https://github.com/mert6148/User-login/actions/workflows/ci.yml/badge.svg)](https://github.com/mert6148/User-login/actions/workflows/ci.yml)

## Continuous Integration (GitHub Actions)

This repository's CI (`.github/workflows/ci.yml`) runs on pushes and pull requests to `main` and `project-main`. The workflow now:

- Runs `pytest` and emits `artifacts/pytest-report.xml` and `artifacts/coverage.xml` (and an HTML coverage report in `artifacts/coverage-html/`).
- Runs `python verify_workflow.py --all --report-format junit` to emit `artifacts/verify-report.xml`.
- Uploads all generated XML and coverage artifacts as the `test-reports` workflow artifact.

You can view and download coverage reports from the CI artifacts after a workflow run.

## Managing Dashboard Users

This project includes a lightweight CLI for managing the dashboard user database (SQLite) and a PowerShell wrapper for convenience.

- CLI script: `scripts/manage_users.php`
- PowerShell wrapper: `scripts/run_manage.ps1`

Common commands (run from project root):

PowerShell example:
```powershell
cd 'C:\Users\mertd\OneDrive\Masaüstü\User-login'
.\scripts\run_manage.ps1 init
.\scripts\run_manage.ps1 list
.\scripts\run_manage.ps1 add alice Secur3P@ss admin
.\scripts\run_manage.ps1 setpw alice NewP@ssw0rd
.\scripts\run_manage.ps1 delete alice
```

Direct PHP usage:
```bash
php scripts/manage_users.php init
php scripts/manage_users.php list
php scripts/manage_users.php add bob StrongP@ss user
```

Security notes:
- The CLI uses PBKDF2 parameters compatible with `UserAuthService.php` (SHA-256, 100000 iterations). Consider using `password_hash()`/Argon2 for production.
- The SQLite DB is written to `data/users.db`. Do not store production user databases in synced folders like OneDrive.

Important: automatic admin seeding has been removed. Create an initial admin explicitly with the CLI or console to avoid shipping default credentials.

Create initial admin (examples):
```powershell
# Direct PHP
php scripts/manage_users.php add admin StrongP@ssw0rd admin

# Using bin/console stub
php bin/console app:user:manage add admin

# Using composer script
composer run console -- app:user:manage add admin
```

Dev-only auto-seed (optional)
-----------------------------

For development convenience there is a guarded seed operation that will only create an admin when explicitly allowed. You can:

- Set environment variable `USER_LOGIN_DEV_SEED=1` and then run the seed command; or
- Use the `--force` flag to bypass the env check (use carefully).

Examples:
```powershell
# Using CLI script (will use --force to override if you prefer)
php scripts/manage_users.php seed
php scripts/manage_users.php seed admin MyTempPass admin --force

# Using bin/console stub
php bin/console app:user:manage seed --force

# Using composer
composer run console -- app:user:manage seed -- --force
```

Note: the seed command will only create the admin if the users table is empty (unless `--force` is used).

Project data (encrypted) management
----------------------------------

This repository includes a `ProjectDataService` which stores sensitive project information (such as API secrets) encrypted at rest using a 256-bit AES key.

Usage and setup:
- The encryption key is read from the environment variable `PROJECT_DATA_KEY` (base64 or raw) if available.
- If not set, a key is generated and persisted to `data/.project_key` with file mode `0600`.
- Projects are stored in `data/projects.db` (SQLite). Each project contains metadata (JSON) and optional secret field which is encrypted using AES-256-CBC and an HMAC for integrity.

CLI and Console support:
- There is a Symfony Console command `app:project:manage` and a CLI helper that can create/list/get/update-secret/delete projects.
- Example using composer console:
```
composer run console -- app:project:manage create --name="My Project" --slug=myproj --metadata='{"desc":"demo"}' --secret="S3cr3t"
You can also run the lightweight CLI without Composer.
```
php scripts/manage_projects.php create "My Project" myproj '{"desc":"demo"}' "S3cr3t"
php scripts/manage_projects.php list
php scripts/manage_projects.php get 1
php scripts/manage_projects.php update-secret 1 "N3wS3cr3t"
php scripts/manage_projects.php delete 1
```

composer run console -- app:project:manage list
composer run console -- app:project:manage get --id=1
composer run console -- app:project:manage update-secret --id=1 --secret="N3wS3cr3t"
composer run console -- app:project:manage delete --id=1

Pushing all files to GitHub
---------------------------

Two convenient scripts are included to push all local changes to a GitHub repo. Choose PowerShell on Windows or the POSIX shell on other systems.

PowerShell (preferred on Windows):
```
.\scripts\push_to_github.ps1 -RemoteUrl "git@github.com:username/repo.git" -Branch main -Message "Update repo" -CreateRepo
```
Arguments:
- `-RemoteUrl`: Git remote URL (optional if `origin` is already configured)
- `-Branch`: Branch to push (defaults to current branch or `main`)
- `-Message`: Commit message
- `-CreateRepo`: If `gh` CLI exists, attempts to create a repo and push
- `-Force`: Force push even if no changes
- `-UseUserRepo`: Use https://github.com/mert6148/User-login.git as the remote URL (convenience flag)

POSIX shell (Linux, macOS):
```
./scripts/push_to_github.sh [-r <remote_url>] [-b <branch>] [-m "Commit message"] [--use-user-repo] [-f]
```
Flags:
- `-r|--remote-url`: Git remote URL (optional if origin is configured)
- `-b|--branch`: Branch to push (defaults to current branch or `main`)
- `-m|--message`: Commit message
- `--use-user-repo`: Use https://github.com/mert6148/User-login.git as the remote URL (convenience flag)
- `-f|--force`: Force push even if no changes

If remote is empty it will use the existing `origin` remote. Ensure `git` is configured and you have authenticated to GitHub via SSH or gh CLI.

Security note: for automation, prefer `gh auth login` (GitHub CLI) or SSH keys rather than embedding tokens in command line arguments. Do not commit secrets.

```

Security and operational notes:
- Keep `PROJECT_DATA_KEY` secure; do not commit it or store it in source control.
- Rotate keys by generating a new key and re-encrypting stored secrets (not implemented in the CLI yet).
- Secrets are only decrypted for authorized users (project owner or admin).
- For production, consider using a dedicated secrets manager (HashiCorp Vault, cloud KMS) instead of a shared filesystem key.


Symfony Console (composer)
-------------------------

If you use Composer and Symfony Console, a minimal `composer.json` is provided. Install dependencies and run the Symfony command:

```powershell
cd 'C:\Users\mertd\OneDrive\Masaüstü\User-login'
composer install
composer run console -- app:user:manage init
composer run console -- app:user:manage list
composer run console -- app:user:manage add alice
```

Notes:
- If your project already has a `composer.json` merge the `scripts` section instead of overwriting.
- `composer run console -- ...` forwards arguments to `php bin/console`.

<!---
mertcash61/mertcash61 is a ✨ special ✨ repository because its `README.md` (this file) appears on your GitHub profile.
You can click the Preview link to take a look at your changes.
--->
