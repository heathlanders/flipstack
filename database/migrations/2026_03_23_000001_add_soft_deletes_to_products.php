<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add 'archived' to the status enum
        DB::statement('ALTER TABLE products DROP CONSTRAINT IF EXISTS products_status_check');
        DB::statement("ALTER TABLE products ADD CONSTRAINT products_status_check CHECK (status::text = ANY (ARRAY['active'::text, 'inactive'::text, 'discontinued'::text, 'draft'::text, 'sold'::text, 'archived'::text]))");

        Schema::table('products', function (Blueprint $table) {
            $table->softDeletes();
            $table->index('deleted_at');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        // Revert status enum
        DB::statement('ALTER TABLE products DROP CONSTRAINT IF EXISTS products_status_check');
        DB::statement("ALTER TABLE products ADD CONSTRAINT products_status_check CHECK (status::text = ANY (ARRAY['active'::text, 'inactive'::text, 'discontinued'::text, 'draft'::text, 'sold'::text]))");
    }
};
