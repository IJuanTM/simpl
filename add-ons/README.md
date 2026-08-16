# Add-ons

Extensions that add functionality to the Simpl framework. Add-ons range from simple utilities to complete systems that extend the framework's capabilities.

## Available Add-ons

### DB

**Database layer and task scheduler**

A modern query builder, schema/migration DDL builder, generic migration/seeder runners, and a
cron-style scheduler - with no domain-specific tables or tasks of its own, a foundation other
add-ons can depend on.

**Includes:**

- Modern `DB` class with clean query builder syntax
- Support for operators: `=`, `!=`, `>`, `<`, `>=`, `<=`, `LIKE`, `IS NULL`, etc.
- Prepared statements with automatic type detection
- Transaction support
- `Blueprint`/`Schema` fluent DDL builder for writing migrations
- `DatabaseMigrator`/`DatabaseSeeder` - register-and-run migration/seeder infrastructure
- `Scheduler` - cron-style task scheduling with persisted run history

[View full documentation →](db/README.md)

---

### Auth

**Complete authentication and user management system**

A production-ready authentication system with modern security practices. Depends on the [`db`](db/README.md) add-on.

**Core Features:**

- User authentication (login/logout/register)
- Email verification system
- Password reset flow
- User profile management
- Role-based access control
- Admin dashboard for user management

**Security:**

- Automatic lockout protection (account & IP-based)
- Secure password hashing (bcrypt/Argon2id)
- Session security with timezone support
- CSRF protection and input sanitization
- SQL injection prevention

**Includes:**

- Email templates (verification, password reset, contact)
- TypeScript form enhancements
- Responsive SCSS styling
- Table/seeder definitions registered with the `db` add-on's runners
- Scheduled cleanup tasks registered with the `db` add-on's `Scheduler`

[View full documentation →](auth/README.md)

---

## Installing Add-ons

Navigate to your project directory and run (this should be a clean installation of Simpl):

```bash
# List available add-ons
npx @ijuantm/simpl-addon --list

# Install an add-on (e.g. auth)
npx @ijuantm/simpl-addon auth
```

Available commands:

- `npx @ijuantm/simpl-addon <addon-name>` - Install an add-on
- `npx @ijuantm/simpl-addon --list` - List all available add-ons
- `npx @ijuantm/simpl-addon --help` - Show help

## Contributing

Have an idea for an add-on or created one yourself? Contributions are welcome! Please open an issue or pull request to discuss adding new add-ons to the framework.

## Requirements

Add-ons may have specific requirements. Check individual add-on documentation for:

- Minimum PHP version
- Required PHP extensions
- Database requirements
- Third-party dependencies
