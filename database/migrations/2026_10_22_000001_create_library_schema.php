<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// T-02 · Migración inicial. Todo el DDL vive aquí (ADR-001). Fuente: data-model.md §3, §4 y §9.
// Ninguna columna lleva DEFAULT: los valores los pone el dominio.
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE SCHEMA IF NOT EXISTS library');

        Schema::create('category', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 120)->unique();
        });

        Schema::create('book', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('title', 250);
            $table->string('author', 200);
            $table->char('isbn', 13);
            $table->uuid('category_id');
            $table->integer('total_copies');
            $table->integer('available_copies');
            $table->string('cover_key', 512)->nullable();
            $table->timestampTz('deleted_at')->nullable();
            $table->integer('version'); // hallazgo H-01: testigo de versión del ADR-002
            $table->foreign('category_id')->references('id')->on('category')->restrictOnDelete();
        });

        Schema::create('member', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('full_name', 200);
            $table->string('document_number', 30)->unique();
        });

        Schema::create('user', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('username', 120)->unique();
            $table->string('password_hash', 512);
            $table->string('role', 40);
        });

        Schema::create('loan', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('member_id');
            $table->uuid('loaned_by_user_id');
            $table->timestampTz('loaned_at');
            $table->foreign('member_id')->references('id')->on('member')->restrictOnDelete();
            $table->foreign('loaned_by_user_id')->references('id')->on('user')->restrictOnDelete();
            $table->index('loaned_at');
        });

        Schema::create('loan_item', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('loan_id');
            $table->uuid('book_id');
            $table->string('book_title', 250); // congelado
            $table->char('isbn', 13);          // congelado
            $table->string('category_name', 120); // congelado, sin FK a propósito (ADR-004)
            $table->timestampTz('due_at');
            $table->timestampTz('returned_at')->nullable();
            $table->foreign('loan_id')->references('id')->on('loan')->cascadeOnDelete();
            $table->foreign('book_id')->references('id')->on('book')->restrictOnDelete();
            $table->unique(['loan_id', 'book_id']);
            $table->index('book_id');
        });

        // Reglas que el motor impone y Laravel no expresa con su constructor (data-model.md §4)
        DB::statement('ALTER TABLE library.book ADD CONSTRAINT ck_book_available_range CHECK (available_copies >= 0 AND available_copies <= total_copies)');
        DB::statement('CREATE UNIQUE INDEX ux_book_isbn_active ON library.book (isbn) WHERE deleted_at IS NULL');
        DB::statement('CREATE INDEX ix_book_category_title_active ON library.book (category_id, title) WHERE deleted_at IS NULL');
        DB::statement('CREATE INDEX ix_loan_item_due_open ON library.loan_item (due_at) WHERE returned_at IS NULL');

        // Semilla: las cinco categorías con identificadores fijos (data-model.md §9)
        DB::table('category')->insert([
            ['id' => '11111111-1111-4111-8111-111111111111', 'name' => 'Novela'],
            ['id' => '22222222-2222-4222-8222-222222222222', 'name' => 'Ciencia'],
            ['id' => '33333333-3333-4333-8333-333333333333', 'name' => 'Tecnología'],
            ['id' => '44444444-4444-4444-8444-444444444444', 'name' => 'Historia'],
            ['id' => '55555555-5555-4555-8555-555555555555', 'name' => 'Infantil'],
        ]);
    }

    public function down(): void
    {
        DB::statement('DROP SCHEMA IF EXISTS library CASCADE');
    }
};
