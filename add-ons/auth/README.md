# Auth Add-on

Complete authentication system for Simpl projects with user management, email verification, password reset, and admin controls.

**Depends on the [`db`](../db/README.md) add-on** for its query builder, migration/seeder runners, and scheduler - `simpl add` resolves this automatically, installing `db` first if it isn't already present.

## Features

### User Authentication

- **Login/Logout** - Secure session-based authentication with optional "remember me"
- **Registration** - User account creation with customizable validation
- **Email Verification** - Optional account verification via email
- **Password Reset** - Forgot password flow with secure token-based reset
- **Profile Management** - Users can edit username, email, and password; changing the email asks for the current password in a modal
- **Username Rules** - Usernames are limited to letters, numbers, dots, dashes and underscores, and regular users can't pick a name that imitates a role, the site name or an entry in `RESERVED_USERNAMES` (matched case-insensitively, ignoring separators and digit look-alikes like `4dm1n`); admins creating or editing a user are exempt from the reserved-name check
- **Contact Form** - A contact form in a modal, opened from a floating button in the bottom-right corner of every page

### Admin System

- **User Management** - View, edit, and soft-delete (with restore) user accounts, with admin-created accounts required to set a new password on first login
- **Role Management** - Assign and manage user roles
- **Login Tracking** - Monitor failed attempts with automatic lockout protection
- **Sortable, Searchable, Paginated Tables** - With breadcrumb navigation and hideable columns

### Two-Factor Authentication

- **Email OTP** - 6-digit one-time code sent by email, the default method when 2FA is first enabled
- **TOTP** - Authenticator app support via QR code enrolment
- **Passkeys** - WebAuthn-based passwordless second factor
- **Recovery Codes & Trusted Devices** - Single-use recovery codes and per-device "remember this device" trust
- **Admin Controls** - Per-role/user required-methods enforcement and admin-initiated 2FA reset

### Security Features

- **Lockout Protection** - Automatic account/IP lockouts after failed login attempts, with exponential backoff
- **Verification & Reset Throttling** - Per-account and per-IP rate limits on verification codes and password resets
- **Password Policy Enforcement** - Configurable length/complexity requirements, validated live in the browser and again server-side
- **Password Hashing** - Secure bcrypt/Argon2id hashing with configurable cost
- **CSRF Protection** - Form validation and sanitization
- **Session Security** - Secure session handling with timezone support; changing a password invalidates every session for that account, on any device
- **SQL Injection Prevention** - Parameterized queries with operator support
- **Non-Enumerable User URLs** - Every user-facing URL (profiles, verification and password reset links, admin user pages) names the user by a random 16-character public id instead of the sequential database id, so users can't be listed by counting up; the admin users table, edit page and (for admins) profile pages show both ids

## Database

Auth's migrations and seeders are *data* registered with the [`db`](../db/README.md) add-on's generic `DatabaseMigrator`/`DatabaseSeeder` runners - the runners themselves, the `DB` query builder, and the `Blueprint`/`Schema` DDL builder all live in `db`. Auth ships its own `Config/migrations.php` and `Config/seeders.php`, which patch into `db`'s base files of the same name via `@addon-insert`/`@addon-end` markers on install - `db` owns the base file (a placeholder extension point), `auth`'s patch inserts its own `DatabaseMigrator::register(...)`/`DatabaseSeeder::register(...)` calls in place of it. Auth's own `Database/` folder only holds its table/seeder *definitions* (`users`, `roles`, `tokens`, `login_attempts`, ...), in dependency order. Auth's scheduled cleanup tasks
(`Config/scheduler.php`) work the same way, patched into `db`'s base `Config/scheduler.php`.

## Structure

```
auth/
├── README.md
├── src/                 # Merges into a project's src/
│   ├── app/
│   │   ├── Config/       # auth.php, mail.php, lockout.php, two-factor.php, upload.php, validation.php, migrations.php/seeders.php/scheduler.php (patches into db's base files)
│   │   ├── Controllers/  # AuthController, MailController, TwoFactorController, WebauthnController, plus patches into App/Alias/Form
│   │   ├── Cron/         # Scheduled task implementations
│   │   ├── Database/     # Table/seeder definitions only - run by the db add-on's runners
│   │   ├── Enums/        # Role, TokenType, TwoFactorMethod, UserStatus
│   │   ├── Mails/        # Email templates (verification, reset, account-created, contact)
│   │   ├── Pages/        # Page controllers (Login, Register, Profile, Users, etc.)
│   │   ├── Scripts/      # app-key.php (generates APP_KEY)
│   │   └── Utils/        # UserAgentParser, Crypto (encryption keyed by APP_KEY), RateLimiter, Timebox
│   ├── scss/             # Styling for the auth and admin pages, on top of core's components
│   ├── ts/               # TypeScript for the auth and admin pages, on top of core's modules
│   └── views/            # Templates for all auth pages
└── tests/                # Merges into a project's tests/ - PHPUnit test classes
    └── app/
```

## Configuration

### Auth Settings (`src/app/Config/auth.php`)

- Email verification requirement
- Password requirements (length, complexity)
- Remember me duration
- `APP_KEY`, the encryption key for authenticator app secrets: generated into `.env` on `composer install`, regenerated with `composer key:regenerate`
- Login attempt limits and lockout durations

### Validation Settings (`src/app/Config/validation.php`)

- Maximum field lengths, matching the database column lengths
- `RESERVED_USERNAMES`, the names regular users can't take on top of every role name and `APP_NAME`

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
- Development SMTP server via `.env`'s `SMTP_DEV_HOST`/`SMTP_DEV_PORT` (default `localhost:25`) - with Docker, this points at a bundled [Mailpit](https://mailpit.axllent.org/) container that catches every email at `http://127.0.0.1:8025/`
- Email templates (verification, password reset, admin-created account, contact)

## Installation

**Automated Installation (Recommended):**

From your Simpl project's root directory, run:

```bash
simpl add auth
```

`simpl add` will:

- Install the [`db`](../db/README.md) add-on first automatically if it isn't already present - it's `db`'s composer.json patch that adds the `migrate`/`seed`/`cron:test` commands
- Copy all new files from the add-on into your project's `src/` and `tests/`
- Automatically merge files that need integration (PHP, TypeScript, SCSS, `.env`)
- Skip files that already exist and don't need merging
- Show you which files (if any) need manual review

**Post-Installation Steps:**

1. Update `.env` with your database and mail credentials
2. Run `simpl composer install` (if needed)
3. Run `simpl migrate` to create the database tables, then `simpl seed` to populate default roles/data - this picks up auth's registered migrations/seeders automatically
4. Run `npm run build` to compile assets

**Manual Method (If needed):**

1. Copy the add-on's `src/` contents into your project's `src/`, and its `tests/` contents into your project's `tests/`
2. Manually merge any conflicting files by following their inline `@addon-insert`/`@addon-end` markers
3. Follow the post-installation steps above

## Tests

Ships a PHPUnit suite (`tests/`, merges into a project's `tests/`) in two parts:

- **Unit and feature tests** (`simpl test`, no database needed) cover `AuthController`'s password policy, tokens and reserved-username matching, `FormController::validatePasswords()`, `AdminTableTrait`'s pagination/sort/filter logic, form and two-tier rate limiting, `TwoFactorController`'s role requirements, login codes and TOTP matching, `WebauthnController`'s encoding helpers, `UserAgentParser`, the `TwoFactorMethod` enum, the cron report output, and the `PruneRateLimitCache` cron task.
- **Integration tests** (`simpl test:integration`, against the database in `src/.env`, using the `db` add-on's test base) cover the database-backed cron tasks: `DeactivateUnverifiedUsers`, `DeleteDeactivatedUsers` and `PruneTwoFactorData`.

## Requirements

- **PHP**: >= 8.5
- **Database**: MySQL >= 9.5.0 or MariaDB >= 12.1.2
- **Extensions**: fileinfo (profile image uploads), plus PDO and pdo_mysql via the [`db`](../db/README.md) add-on
- **Packages**: `phpmailer/phpmailer` (email), `spomky-labs/otphp` (TOTP), `bacon/bacon-qr-code` (QR codes), `web-auth/webauthn-lib` (passkeys)

## Email Templates

Includes responsive, email-client-compatible templates:

- Account verification
- Password reset
- Admin-created account (temporary password + login link)
- Contact form notifications

All templates use tables and inline styles for maximum compatibility.

## TypeScript Features

Builds on core's form, modal and table components:

- Reserved-username check added to core's live form validation
- Contact modal that sends in the background, showing any errors inside the modal and keeping what was typed, with a character counter
- Admin tables extend core's `table/table` component with server-side search, filters, sorting and pagination, reloaded via AJAX
- Confirmation modals for admin user and role actions
- Admin users table rows open that user's profile, and the user cell of a login attempt opens the profile of the account it was for

## Security Notes

- Change default database credentials immediately
- Use a dedicated database user (not root)

## License

This add-on is provided as-is for use with Simpl framework projects.
