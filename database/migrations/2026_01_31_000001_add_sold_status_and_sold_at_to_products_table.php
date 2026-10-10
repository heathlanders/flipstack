<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Drop and recreate the CHECK constraint to add 'sold'
        DB::statement('ALTER TABLE products DROP CONSTRAINT products_status_check');
        DB::statement("ALTER TABLE products ADD CONSTRAINT products_status_check CHECK (((status)::text = ANY ((ARRAY['active'::character varying, 'inactive'::character varying, 'discontinued'::character varying, 'draft'::character varying, 'sold'::character varying])::text[])))");

        Schema::table('products', function (Blueprint $table) {
            $table->timestamp('sold_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('sold_at');
        });

        DB::statement('ALTER TABLE products DROP CONSTRAINT products_status_check');
        DB::statement("ALTER TABLE products ADD CONSTRAINT products_status_check CHECK (((status)::text = ANY ((ARRAY['active'::character varying, 'inactive'::character varying, 'discontinued'::character varying, 'draft'::character varying])::text[])))");
    }
};
