@addon-insert:replace('// @addon-placeholder')
// Order matters, migrations with foreign keys must come after the tables they reference
DatabaseMigrator::register(\app\Database\Migrations\Tables\CreateUsersTable::class);
DatabaseMigrator::register(\app\Database\Migrations\Tables\CreateLoginAttemptsTable::class);
DatabaseMigrator::register(\app\Database\Migrations\Tables\CreateTokensTable::class);
DatabaseMigrator::register(\app\Database\Migrations\Tables\CreateRolesTable::class);
DatabaseMigrator::register(\app\Database\Migrations\Tables\CreateUserRolesTable::class);
DatabaseMigrator::register(\app\Database\Migrations\Tables\CreateUserTwoFactorTable::class);
DatabaseMigrator::register(\app\Database\Migrations\Tables\CreateWebauthnCredentialsTable::class);
DatabaseMigrator::register(\app\Database\Migrations\Tables\CreateTwoFactorRecoveryCodesTable::class);
DatabaseMigrator::register(\app\Database\Migrations\Tables\CreateTrustedDevicesTable::class);
@addon-end
