<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dodaje do users:
 * - `pending_email`: nowy adres czekający na potwierdzenie (users.email zmienia się dopiero po kliknięciu w link),
 * - `session_version`: licznik unieważniania sesji (reset 2FA / hasła kończy sesje niezależnie od sterownika sesji).
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('pending_email', 190)->nullable()->after('email_verified_at');
            $table->unsignedInteger('session_version')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['pending_email', 'session_version']);
        });
    }
};
