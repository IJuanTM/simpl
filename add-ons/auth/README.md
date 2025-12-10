# Auth Add-on

Complete authentication system for Simpl projects with user management, email verification, password reset, and admin controls.

## Features

### User Authentication

- **Login/Logout** - Secure session-based authentication with optional "remember me"
- **Registration** - User account creation with customizable validation
- **Email Verification** - Optional account verification via email
- **Password Reset** - Forgot password flow with secure token-based reset
- **Profile Management** - Users can edit username, email, and password
- **Contact Form** - Built-in contact functionality

### Admin System

- **User Management** - View, edit, and soft-delete user accounts
- **Role Management** - Assign and manage user roles
- **Login Tracking** - Monitor failed attempts with automatic lockout protection

### Security Features

- **Lockout Protection** - Automatic account/IP lockouts after failed login attempts
- **Password Hashing** - Secure bcrypt/Argon2id hashing with configurable cost
- **CSRF Protection** - Form validation and sanitization
- **Session Security** - Secure session handling with timezone support
- **SQL Injection Prevention** - Parameterized queries with operator support

## Database Class

The included `DB` class provides a clean, modern interface for database operations:

```php
// Basic SELECT (fetches multiple rows)
DB::select(
    SELECT: '*',
    FROM: 'users',
    WHERE: ['id' => 5] // Default operator is '='
);

// Single value SELECT
DB::single(
    SELECT: 'email',
    FROM: 'users',
    WHERE: ['username' => 'john']
);

// Insert a row
DB::insert(
    INTO: 'users',
    VALUES: [
        'username' => 'jane',
        'email' => 'jane@example.com'
    ]
);

// Update rows
DB::update(
    UPDATE: 'users',
    SET: [
        'status' => 'active'
    ],
    WHERE: [
        'id' => 5
    ]
);

// Delete rows
DB::delete(
    FROM: 'tokens', 
    WHERE: [
        'expires' => ['<', date('Y-m-d H:i:s')] // Using a custom operator
    ]
);

// Operators supported: =, !=, <>, >, >=, <, <=, LIKE, NOT LIKE, IS, IS NOT
```

## Structure

```
auth/
├── app/
│   ├── Config/           # Configuration files (auth, mail, database)
│   ├── Controllers/      # Core logic (Auth, Mail, Form)
│   ├── Database/         # DB class and example SQL schema
│   ├── Mails/           # Email templates (verification, reset, contact)
│   ├── Pages/           # Page controllers (Login, Register, Profile, Users, etc.)
│   └── Scripts/         # Helper scripts (CRON jobs)
├── scss/                # Styling for forms, tables, and pages
├── ts/                  # TypeScript for form interactions
├── views/               # Templates for all auth pages
└── README.md
```

## Configuration

### Auth Settings (`config/auth.php`)

- Email verification requirement
- Password requirements (length, complexity)
- Remember me duration
- Login attempt limits and lockout durations

### Database (`config/database.php`)

Set your database credentials in `.env`:

```env
DB_SERVER=localhost
DB_NAME=your_database
DB_USERNAME=your_user
DB_PASSWORD=your_password
```

### Mail Settings (`config/mail.php`)

- SMTP server configuration
- Email templates (verification, password reset, contact)

## Installation

**Quick Method:**

1. Extract the add-on into your Simpl project folder
2. Skip overwriting existing files when prompted
3. Manually merge conflicting files (see below)

**Manual Method:**

1. Copy `.env` contents to your project's `.env`
2. Copy `app/Config`, `app/Controllers`, `app/Database`, `app/Enums`, `app/Pages` folders
3. Merge `app/Controllers/AppController.php` - add the remember-me check before PageController instantiation
4. Copy `ts/input.ts` and merge `ts/main.ts`
5. Copy `scss/components` and merge `scss/views` and `scss/main.scss`
6. Merge `views/parts/header.phtml` for navigation changes
7. Import the database schema from `Database/example.sql`

## Requirements

- **PHP**: >= 8.4
- **Database**: MySQL >= 9.5.0 or MariaDB >= 12.1.2
- **Extensions**: PDO

## Email Templates

Includes responsive, email-client-compatible templates:

- Account verification
- Password reset
- Contact form notifications

All templates use tables and inline styles for maximum compatibility.

## TypeScript Features

- Password visibility toggle
- Caps Lock warning
- Textarea character counter
- Form validation with submit button disabling
- Auto-save prevention when no changes detected

## Security Notes

- Change default database credentials immediately
- Use a dedicated database user (not root)

## License

This add-on is provided as-is for use with Simpl framework projects.
