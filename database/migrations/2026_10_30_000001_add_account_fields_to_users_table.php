<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dodaje do tabeli users dane rejestracyjne: NIP i nazwę firmy (fachowiec),
 * zgody RODO z czasem i wersją tekstu oraz znacznik blokady konta.
 * Podstawy prawne poszczególnych pól: docs/RODO_REJESTR.md.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // NIP nie jest unikalny: kolejne konta z tym samym NIP-em są dozwolone (SPECYFIKACJA.md §10).
            $table->char('nip', 10)->nullable()->after('email_verified_at')->index('users_nip_index');
            $table->string('company_name', 190)->nullable()->after('nip');

            $table->timestamp('terms_accepted_at')->nullable();
            $table->string('terms_version', 32)->nullable();
            $table->timestamp('privacy_accepted_at')->nullable();
            $table->string('privacy_version', 32)->nullable();
            // NULL = brak zgody marketingowej.
            $table->timestamp('marketing_consent_at')->nullable();
            $table->string('marketing_consent_version', 32)->nullable();

            // Zaślepka pod DSA: pełny proces banów powstanie w Etapie 6.
            $table->timestamp('banned_at')->nullable();
            // "Przypomnij później" dla banera 2FA właściciela.
            $table->timestamp('two_factor_reminder_until')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('users_nip_index');
            $table->dropColumn([
                'nip',
                'company_name',
                'terms_accepted_at',
                'terms_version',
                'privacy_accepted_at',
                'privacy_version',
                'marketing_consent_at',
                'marketing_consent_version',
                'banned_at',
                'two_factor_reminder_until',
            ]);
        });
    }
};
