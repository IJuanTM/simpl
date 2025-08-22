<?php

use app\Database\Database;

/* ---------------------------------------------------------------- */

// Execute the start script
require_once 'start.php';

/* ---------------------------------------------------------------- */

$db = new Database();

/*
 * Cron job to deactivate users that have not verified their email address for more than a day.
 */
$db->query('SELECT * FROM users');
$users = $db->fetchAll();

foreach ($users as $user) {
    $db->query('SELECT * FROM tokens WHERE user_id = :id AND type = :type');
    $db->bind(':id', $user['id']);
    $db->bind(':type', 'verification');
    $token = $db->single();

    if ($token && $token['updated_at'] < date('Y-m-d H:i:s', strtotime('-1 day'))) {
        $db->query('UPDATE users SET is_active = 0, deleted_at = NOW() WHERE id = :id');
        $db->bind(':id', $user['id']);
        $db->execute();
    }
}

/*
 * Cron job to delete users that have been deleted for more than a week.
 */
$db->query('DELETE FROM users WHERE deleted_at IS NOT NULL AND deleted_at < NOW() - INTERVAL 1 WEEK');
$db->execute();
