# Auth Add-on

Complete authentication system for Simpl projects with user management, email verification, password reset, and admin controls.

**Depends on the [`db`](../db/README.md) add-on** for its query builder, migration/seeder runners, and scheduler - the installer resolves this automatically, installing `db` first if it isn't already present.

## Features

### User Authentication

- **Login/Logout** - Secure session-based authentication with optional "remember me"
- **Registration** - User account creation with customizable validation
- **Email Verification** - Optional account verification via email
- **Password Reset** - Forgot password flow with secure token-based reset
- **Profile Management** - Users can edit username, email, and password
- **Contact Form** - Built-in contact functionality

### Admin System

- **User Management** - View, edit, and soft-delete (with restore) user accounts, with admin-created accounts required to set a new password on first login
- **Role Management** - Assign and manage user roles
- **Login Tracking** - Monitor failed attempts with automatic lockout protection
- **Sortable, Searchable, Paginated Tables** - With breadcrumb navigation and hideable columns

### Security Features

- **Lockout Protection** - Automatic account/IP lockouts after failed login attempts, with exponential backoff
- **Verification & Reset Throttling** - Per-account and per-IP rate limits on verification codes and password resets
- **Password Policy Enforcement** - Configurable length/complexity requirements, validated live as you type and again server-side
- **Password Hashing** - Secure bcrypt/Argon2id hashing with configurable cost
- **CSRF Protection** - Form validation and sanitization
- **Session Security** - Secure session handling with timezone support
- **SQL Injection Prevention** - Parameterized queries with operator support

## Database

Auth's migrations and seeders are *data* registered with the [`db`](../db/README.md) add-on's generic `DatabaseMigrator`/`DatabaseSeeder` runners - the runners themselves, the `DB` query builder, and the `Blueprint`/`Schema` DDL builder all live in `db`. Auth ships its own
`Config/migrations.php` and `Config/seeders.php`, which patch into `db`'s base files of the same name via `@addon-insert`/`@addon-end` markers on install - `db` owns the base file (a placeholder extension point), `auth`'s patch inserts its own `DatabaseMigrator::register(...)`/
`DatabaseSeeder::register(...)` calls in place of it. Auth's own `Database/` folder only holds its table/seeder *definitions* (`users`, `roles`, `tokens`, `login_attempts`, ...), in dependency order. Auth's scheduled cleanup tasks (`Config/scheduler.php`) work the same way, patched into `db`'s base `Config/scheduler.php`.

## Structure

```
auth/
├── README.md
├── src/                 # Merges into a project's src/
│   ├── app/
│   │   ├── Config/       # auth.php, mail.php, lockout.php, upload.php, validation.php,
│   │   │                 #   migrations.php/seeders.php/scheduler.php (patches into db's base files)
│   │   ├── Controllers/  # AuthController, MailController, plus patches into App/Alias/Form
│   │   ├── Cron/         # Scheduled task implementations
│   │   ├── Database/     # Table/seeder definitions only - run by the db add-on's runners
│   │   ├── Enums/        # Role, TokenType, UserStatus
│   │   ├── Mails/        # Email templates (verification, reset, account-created, contact)
│   │   └── Pages/        # Page controllers (Login, Register, Profile, Users, etc.)
│   ├── scss/             # Styling for forms, tables, and pages
│   ├── ts/                # TypeScript for form interactions
│   └── views/             # Templates for all auth pages
└── tests/                # Merges into a project's tests/ - PHPUnit test classes
    └── app/
```

## Configuration

### Auth Settings (`src/app/Config/auth.php`)

- Email verification requirement
- Password requirements (length, complexity)
- Remember me duration
- Login attempt limits and lockout durations

### Database

Provided by the [`db`](../db/README.md) add-on's `src/app/Config/database.php`. Set your database credentials in `.env`:

```env
DB_SERVER=localhost
DB_NAME=your_database
DB_USERNAME=your_user
DB_PASSWORD=your_password
```

### Mail Settings (`src/app/Config/mail.php`)

- Site/no-reply sender addresses and the mail logo URL
- SMTP server configuration (dev/production), sent via [PHPMailer](https://github.com/PHPMailer/PHPMailer)
- Email templates (verification, password reset, admin-created account, contact)

## Installation

**Automated Installation (Recommended):**

From your Simpl project's root directory, run:

```bash
npx @ijuantm/simpl-addon auth
```

The installer will:

- Install the [`db`](../db/README.md) add-on first automatically if it isn't already present - it's `db`'s composer.json patch that adds the `migrate`/`seed`/`cron:test` commands
- Copy all new files from the add-on into your project's `src/` and `tests/`
- Automatically merge files that need integration (PHP, TypeScript, SCSS, `.env`)
- Skip files that already exist and don't need merging
- Show you which files (if any) need manual review

**Post-Installation Steps:**

1. Update `.env` with your database and mail credentials
2. Run `composer install` (if needed)
3. Run `composer migrate` to create the database tables, then `composer seed` to populate default roles/data - this picks up auth's registered migrations/seeders automatically
4. Manually merge `src/views/parts/layout/header.phtml` for navigation links (if needed)
5. Run `npm run build` to compile assets

**Manual Method (If needed):**

1. Copy the add-on's `src/` contents into your project's `src/`, and its `tests/` contents into your project's `tests/`
2. Manually merge any conflicting files by following their inline `@addon-insert`/`@addon-end` markers
3. Follow the post-installation steps above

## Tests

Ships a PHPUnit suite (`tests/`, merges into a project's `tests/`) covering `AuthController`'s config-driven password-policy/token surface, `FormController::validatePasswords()`,
`AdminTableTrait`'s pagination/sort/filter logic, `RateLimitedForm`, `MailController::template`'s not-found branch, and the `PruneRateLimitCache` cron task. Once installed, run `composer test`
from your project's root the same way you would for the framework itself. Anything that touches the database directly (auth's own migrations/seeders, the DB-backed cron tasks, most `Pages/*`
classes) isn't covered here - that requires a real database connection.

## Requirements

- **PHP**: >= 8.5
- **Database**: MySQL >= 9.5.0 or MariaDB >= 12.1.2
- **Extensions**: PDO, pdo_mysql (via the [`db`](../db/README.md) add-on)

## Email Templates

Includes responsive, email-client-compatible templates:

- Account verification
- Password reset
- Admin-created account (temporary password + login link)
- Contact form notifications

All templates use tables and inline styles for maximum compatibility.

## TypeScript Features

- Password visibility toggle
- Live password policy validation as you type
- Caps Lock warning
- Textarea character counter
- Form validation with submit button disabling
- Auto-save prevention when no changes detected
- Sortable, filterable, paginated admin tables
- Shared confirmation modal for admin user actions

## Security Notes

- Change default database credentials immediately
- Use a dedicated database user (not root)

## License

This add-on is provided as-is for use with Simpl framework projects.
