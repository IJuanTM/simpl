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
//$db->query('SELECT * FROM users');
//$users = $db->fetchAll();

$users = DB::select(
    '*',
    'users'
);

foreach ($users as $user) {
    $token = DB::single(
        '*',
        'tokens',
        ['user_id' => $user['id'], 'type' => 'verification']
    );

    if ($token && $token['updated_at'] < date('Y-m-d H:i:s', strtotime('-1 day'))) DB::update(
        'users',
        ['is_active' => 0, 'deleted_at' => date('Y-m-d H:i:s')],
        ['id' => $user['id']]
    );
}

/*
 * Cron job to delete users that have been deleted for more than a week.
 */
//$db->query('DELETE FROM users WHERE deleted_at IS NOT NULL AND deleted_at < NOW() - INTERVAL 1 WEEK');
//$db->execute();

DB::delete(
    'users',
    ['deleted_at <' => date('Y-m-d H:i:s', strtotime('-1 week'))]
);
