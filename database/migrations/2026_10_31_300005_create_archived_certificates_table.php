<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Archiwum skanów zaświadczeń po usunięciu konta fachowca (ADR-021, retencja 12 miesięcy).
 *
 * Świadomie BEZ kluczy obcych do `users` i `specialist_profiles`: konto i profil są wtedy kasowane.
 * Wiersz powstaje wyłącznie w akcji `PurgeSpecialistData`; dostęp ma tylko administrator;
 * komenda `certificates:purge-archive` usuwa wiersze i pliki po `delete_after`.
 * Podstawa prawna do weryfikacji przez prawnika (art. 6 ust. 1 lit. f RODO w związku z art. 17 ust. 3 lit. e).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('archived_certificates', function (Blueprint $table) {
            $table->id();
            $table->char('ulid', 26)->unique();
            // Zwykła liczba, bez FK: wskazuje wpis w dzienniku aktywności (wyszukiwanie przy roszczeniu).
            $table->unsignedBigInteger('former_user_id')->nullable();
            $table->string('holder_label', 120);
            $table->string('type', 30);
            $table->string('title', 150);
            $table->string('number', 80);
            $table->string('issuer', 150)->nullable();
            $table->date('valid_until')->nullable();
            $table->string('verification_status_at_archive', 20);
            $table->timestamp('verified_at')->nullable();
            $table->string('file_path', 120);
            $table->string('file_mime', 40);
            $table->unsignedInteger('file_size');
            $table->char('file_sha256', 64);
            $table->timestamp('archived_at');
            $table->date('delete_after')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('archived_certificates');
    }
};
