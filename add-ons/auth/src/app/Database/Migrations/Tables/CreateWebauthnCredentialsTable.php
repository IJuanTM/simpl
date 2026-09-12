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
            // 512 chars of base64url: comfortably fits a spec-legal credential id (up to 1023 raw bytes) without an index-length overflow.
            $t->varchar('credential_id', 512, notNull: true)->unique();
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
