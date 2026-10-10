<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // Drop tables with CASCADE to handle all dependencies
        DB::statement('DROP TABLE IF EXISTS brands CASCADE');
        DB::statement('DROP TABLE IF EXISTS categories CASCADE');

        // Recreate categories with UUID (WITHOUT parent_id foreign key yet)
        Schema::create('categories', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('uuid_generate_v4()'));
            $table->unsignedBigInteger('organization_id');
            $table->uuid('parent_id')->nullable();

            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->string('image', 500)->nullable();

            $table->integer('lft')->default(0);
            $table->integer('rgt')->default(0);
            $table->integer('depth')->default(0);

            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);

            $table->jsonb('metadata')->default('{}');

            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->unique(['organization_id', 'slug']);
            $table->index(['organization_id', 'is_active']);
        });

        // Add ltree column using raw SQL
        DB::statement('ALTER TABLE categories ADD COLUMN path ltree NOT NULL DEFAULT \'\'::ltree');
        DB::statement('CREATE INDEX idx_categories_path ON categories USING GIST (path)');

        // NOW add the self-referencing foreign key after table is created
        Schema::table('categories', function (Blueprint $table) {
            $table->foreign('parent_id')->references('id')->on('categories')->onDelete('cascade');
        });

        // Recreate brands with UUID
        Schema::create('brands', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('uuid_generate_v4()'));
            $table->unsignedBigInteger('organization_id');

            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->string('logo', 500)->nullable();
            $table->string('website')->nullable();

            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);

            $table->jsonb('metadata')->default('{}');

            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->unique(['organization_id', 'slug']);
            $table->index(['organization_id', 'is_active']);
        });

        // Re-add foreign keys to products table if they were dropped
        try {
            Schema::table('products', function (Blueprint $table) {
                $table->foreign('category_id')->references('id')->on('categories')->onDelete('set null');
            });
        } catch (\Exception $e) {
            // Foreign key might already exist, that's ok
        }

        try {
            Schema::table('products', function (Blueprint $table) {
                $table->foreign('brand_id')->references('id')->on('brands')->onDelete('set null');
            });
        } catch (\Exception $e) {
            // Foreign key might already exist, that's ok
        }
    }

    public function down()
    {
        DB::statement('DROP TABLE IF EXISTS brands CASCADE');
        DB::statement('DROP TABLE IF EXISTS categories CASCADE');
    }
};
