<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Zaświadczenia o uprawnieniach fachowca: metadane w bazie, plik na dysku `local` (ADR-019).
 * Klucz w adresach to `ulid` (nigdy numeryczne ID). Numer, wystawca i plik nie są nigdy publiczne.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('specialist_certificates', function (Blueprint $table) {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('specialist_profile_id')->constrained('specialist_profiles')->cascadeOnDelete();
            $table->string('type', 30);
            $table->string('title', 150);
            $table->string('number', 80);
            $table->string('issuer', 150)->nullable();
            $table->date('valid_until')->nullable();
            $table->string('file_path', 120);
            $table->string('file_original_name', 150);
            $table->string('file_mime', 40);
            $table->unsignedInteger('file_size');
            $table->char('file_sha256', 64);
            $table->timestamps();

            // Przypomnienia o wygasaniu uprawnień (późniejsze zadanie).
            $table->index(['specialist_profile_id', 'valid_until'], 'specialist_certificates_expiry_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('specialist_certificates');
    }
};
