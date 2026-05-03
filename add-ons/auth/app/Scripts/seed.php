<?php

declare(strict_types=1);

use app\Database\Seeders\DatabaseSeeder;

/* ---------------------------------------------------------------- */

// Execute the start script
require_once 'start.php';

/* ---------------------------------------------------------------- */

// Run the database seeder
DatabaseSeeder::run();

echo "Database seeded successfully.\n";
