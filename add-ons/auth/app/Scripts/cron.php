<?php

use app\Database\DB;

/* ---------------------------------------------------------------- */

// Execute the start script
require_once 'start.php';

/* ---------------------------------------------------------------- */

//$db = new Database();

/*
 * Cron job to deactivate users that have not verified their email address for more than a day.
 */

// Get all users who are still active
$users = DB::select(
    '*',
    'users',
    [
        'is_active' => 1
    ]
);

foreach ($users as $user) {
    // Get the verification token for the user
    $token = DB::single(
        '*',
        'tokens',
        [
            'user_id' => $user['id'],
            'type' => 'verification'
        ]
    );

    // Deactivate the user if the token is older than a day
    if ($token && $token['updated_at'] < date('Y-m-d H:i:s', strtotime('-1 day'))) DB::update(
        'users',
        [
            'is_active' => 0,
            'deleted_at' => date('Y-m-d H:i:s')
        ],
        [
            'id' => $user['id']
        ]
    );
}

/*
 * Cron job to delete users that have been deleted for more than a week.
 */

// Delete users who have been marked as deleted for more than a week
DB::delete(
    'users',
    [
        'deleted_at' => ['<', date('Y-m-d H:i:s', strtotime('-1 week'))]
    ]
);
