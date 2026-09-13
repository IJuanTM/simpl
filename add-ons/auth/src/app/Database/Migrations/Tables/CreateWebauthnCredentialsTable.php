<?php

declare(strict_types=1);

namespace app\Database\Migrations\Tables;

use app\Database\Migrations\Blueprint;
use app\Database\Migrations\Schema;

class CreateWebauthnCredentialsTable
{
    /**
     * Creates the webauthn_credentials table: one row per registered passkey, storing the credential id, its public key, the signature counter, and user-facing metadata.
     */
    public static function up(): void
    {
        Schema::create('webauthn_credentials', static function (Blueprint $t) {
            $t->bigintUnsigned('id', notNull: true)->autoIncrement();
            $t->bigintUnsigned('user_id', notNull: true);
            // 1364 base64url chars covers the full spec-legal credential id (up to 1023 raw bytes, ceil(1023/3)*4 = 1364).
            // Stored as ascii (1 byte/char, base64url is already ASCII-only) so the UNIQUE index stays under InnoDB's byte-length
            // limit; the table's default utf8mb4 charset would need 4 bytes/char and overflow it at this length.
            $t->varchar('credential_id', 1364, notNull: true, charset: 'ascii')->unique();
            $t->text('public_key', notNull: true);
            $t->intUnsigned('sign_count', notNull: true, default: 0);
            $t->varchar('transports', 255);
            $t->varchar('aaguid', 64);
            $t->varchar('name', 100, notNull: true, default: 'Passkey');
            $t->timestamp('created_at', notNull: true, default: 'CURRENT_TIMESTAMP');
            $t->timestamp('last_used_at', default: null);
            $t->primary('id');
            $t->foreign('user_id', 'users');
            $t->index('idx_webauthn_user', ['user_id']);
        });
    }

    /**
     * Drops the webauthn_credentials table.
     */
    public static function down(): void
    {
        Schema::drop('webauthn_credentials');
    }
}
