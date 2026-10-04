@addon-insert:replace('// @addon-placeholder')
// Order matters, seeders with foreign keys must come after the tables they reference
DatabaseSeeder::register(\app\Database\Seeders\RolesSeeder::class);
DatabaseSeeder::register(\app\Database\Seeders\UsersSeeder::class);
DatabaseSeeder::register(\app\Database\Seeders\UserRolesSeeder::class);
@addon-end
