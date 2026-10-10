<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
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

        // Add brand_id to products table
        Schema::table('products', function (Blueprint $table) {
            $table->uuid('brand_id')->nullable()->after('category_id');
            $table->foreign('brand_id')->references('id')->on('brands')->onDelete('set null');
            $table->index(['organization_id', 'brand_id']);
        });
    }

    public function down()
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['brand_id']);
            $table->dropColumn('brand_id');
        });

        Schema::dropIfExists('brands');
    }
};
