<?php

declare(strict_types=1);

use app\Database\Seeders\DatabaseSeeder;

/* ---------------------------------------------------------------- */

// Execute the start script
require_once 'start.php';

/* ---------------------------------------------------------------- */

// If --fresh flag is provided, truncate existing data
if (in_array('--fresh', $argv, true)) {
    echo "🗑️  Dropping existing data...\n";

    DatabaseSeeder::truncate();

    echo "✓ Data dropped successfully.\n\n";
}

echo "🌱 Seeding database...\n";

try {
    // Run the database seeder
    DatabaseSeeder::run();
} catch (Exception $e) {
    // Handle seeding errors
    echo "❌ Seeding failed: " . $e->getMessage() . "\n";
    exit(1);
}

echo "✓ Database seeded successfully.\n";
