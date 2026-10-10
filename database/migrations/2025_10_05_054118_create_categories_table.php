<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // Enable PostgreSQL extensions
        DB::statement('CREATE EXTENSION IF NOT EXISTS "uuid-ossp"');
        DB::statement('CREATE EXTENSION IF NOT EXISTS "ltree"');
        DB::statement('CREATE EXTENSION IF NOT EXISTS "pg_trgm"');
        DB::statement('CREATE EXTENSION IF NOT EXISTS "btree_gin"');

        Schema::create('categories', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('uuid_generate_v4()'));
            $table->unsignedBigInteger('organization_id');
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->string('path')->index(); // ltree type handled in raw SQL
            $table->integer('lft');
            $table->integer('rgt');
            $table->integer('depth')->default(0);
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->string('seo_title')->nullable();
            $table->text('seo_description')->nullable();
            $table->jsonb('meta_data')->default('{}');
            $table->string('image_url', 500)->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'slug']);
            $table->unique(['organization_id', 'path']);

            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('users');
        });

        // Add check constraints using raw SQL
        DB::statement('ALTER TABLE categories ADD CONSTRAINT valid_nested_set CHECK (lft < rgt)');
        DB::statement('ALTER TABLE categories ADD CONSTRAINT valid_depth CHECK (depth >= 0)');

        // Convert path column to ltree type
        DB::statement('ALTER TABLE categories ALTER COLUMN path TYPE ltree USING path::ltree');

        // Create separate indexes instead of combined GIST index
        DB::statement('CREATE INDEX idx_categories_path ON categories USING GIST (path)');
        DB::statement('CREATE INDEX idx_categories_organization ON categories (organization_id)');
        DB::statement('CREATE INDEX idx_categories_lft_rgt ON categories (organization_id, lft, rgt)');
        DB::statement('CREATE INDEX idx_categories_active ON categories (organization_id, is_active)');
    }

    public function down()
    {
        Schema::dropIfExists('categories');
    }
};
