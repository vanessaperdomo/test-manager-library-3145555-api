<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Implements data-model.md §3 for PostgreSQL 16 schema 'library'.
     */
    public function up(): void
    {
        // Asegurar que el esquema library exista
        DB::statement('CREATE SCHEMA IF NOT EXISTS library;');

        // 1. category
        DB::statement('
            CREATE TABLE library.category (
                id UUID PRIMARY KEY,
                name VARCHAR(120) NOT NULL UNIQUE
            );
        ');

        // 2. book
        DB::statement('
            CREATE TABLE library.book (
                id UUID PRIMARY KEY,
                title VARCHAR(250) NOT NULL,
                author VARCHAR(200) NOT NULL,
                isbn CHAR(13) NOT NULL,
                category_id UUID NOT NULL REFERENCES library.category(id) ON DELETE RESTRICT,
                total_copies INTEGER NOT NULL,
                available_copies INTEGER NOT NULL,
                cover_key VARCHAR(512) NULL,
                deleted_at TIMESTAMPTZ NULL,
                CONSTRAINT ck_book_available_range CHECK (0 <= available_copies AND available_copies <= total_copies),
                CONSTRAINT ck_book_total_positive CHECK (total_copies >= 1)
            );
        ');

        // Índice único parcial para ISBN entre libros activos (WHERE deleted_at IS NULL)
        DB::statement('CREATE UNIQUE INDEX idx_book_isbn_active ON library.book (isbn) WHERE deleted_at IS NULL;');
        // Índice de búsqueda por categoría y título
        DB::statement('CREATE INDEX idx_book_category_title ON library.book (category_id, title) WHERE deleted_at IS NULL;');

        // 3. member
        DB::statement('
            CREATE TABLE library.member (
                id UUID PRIMARY KEY,
                full_name VARCHAR(200) NOT NULL,
                document_number VARCHAR(30) NOT NULL UNIQUE
            );
        ');

        // 4. user (se entrecomilla para evitar palabra reservada SQL)
        DB::statement('
            CREATE TABLE library."user" (
                id UUID PRIMARY KEY,
                username VARCHAR(120) NOT NULL UNIQUE,
                password_hash VARCHAR(512) NOT NULL,
                role VARCHAR(40) NOT NULL,
                CONSTRAINT ck_user_role CHECK (role IN (\'admin\', \'librarian\'))
            );
        ');

        // 5. loan
        DB::statement('
            CREATE TABLE library.loan (
                id UUID PRIMARY KEY,
                member_id UUID NOT NULL REFERENCES library.member(id) ON DELETE RESTRICT,
                loaned_by_user_id UUID NOT NULL REFERENCES library."user"(id) ON DELETE RESTRICT,
                loaned_at TIMESTAMPTZ NOT NULL
            );
        ');
        DB::statement('CREATE INDEX idx_loan_loaned_at ON library.loan (loaned_at);');

        // 6. loan_item
        DB::statement('
            CREATE TABLE library.loan_item (
                id UUID PRIMARY KEY,
                loan_id UUID NOT NULL REFERENCES library.loan(id) ON DELETE CASCADE,
                book_id UUID NOT NULL REFERENCES library.book(id) ON DELETE RESTRICT,
                book_title VARCHAR(250) NOT NULL,
                isbn CHAR(13) NOT NULL,
                category_name VARCHAR(120) NOT NULL,
                due_at TIMESTAMPTZ NOT NULL,
                returned_at TIMESTAMPTZ NULL,
                CONSTRAINT uq_loan_book UNIQUE (loan_id, book_id)
            );
        ');
        DB::statement('CREATE INDEX idx_loan_item_book ON library.loan_item (book_id);');
        DB::statement('CREATE INDEX idx_loan_item_overdue ON library.loan_item (due_at) WHERE returned_at IS NULL;');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS library.loan_item CASCADE;');
        DB::statement('DROP TABLE IF EXISTS library.loan CASCADE;');
        DB::statement('DROP TABLE IF EXISTS library."user" CASCADE;');
        DB::statement('DROP TABLE IF EXISTS library.member CASCADE;');
        DB::statement('DROP TABLE IF EXISTS library.book CASCADE;');
        DB::statement('DROP TABLE IF EXISTS library.category CASCADE;');
    }
};
