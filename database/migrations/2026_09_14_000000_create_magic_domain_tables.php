<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cards', function (Blueprint $table) {
            $table->id();
            $table->string('provider');
            $table->string('provider_id');
            $table->string('name');
            $table->string('layout')->nullable();
            $table->text('mana_cost')->nullable();
            $table->decimal('cmc', 8, 3)->nullable();
            $table->text('type')->nullable();
            $table->string('rarity')->nullable();
            $table->string('set_code')->nullable();
            $table->string('set_name')->nullable();
            $table->text('text')->nullable();
            $table->text('flavor')->nullable();
            $table->string('artist')->nullable();
            $table->string('number')->nullable();
            $table->string('power')->nullable();
            $table->string('toughness')->nullable();
            $table->string('loyalty')->nullable();
            $table->string('language')->nullable();
            $table->string('image_url')->nullable();
            $table->string('watermark')->nullable();
            $table->string('border')->nullable();
            $table->boolean('timeshifted')->nullable();
            $table->boolean('reserved')->nullable();
            $table->boolean('starter')->nullable();
            $table->integer('hand')->nullable();
            $table->integer('life')->nullable();
            $table->date('release_date')->nullable();
            $table->text('original_text')->nullable();
            $table->text('original_type')->nullable();
            $table->string('source')->nullable();
            $table->json('api_data');
            $table->timestamps();
            $table->unique(['provider', 'provider_id']);
            $table->index('name');
        });

        Schema::create('colors', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 1)->unique();
            $table->timestamps();
        });

        Schema::create('card_color', function (Blueprint $table) {
            $table->foreignId('card_id')->constrained()->cascadeOnDelete();
            $table->foreignId('color_id')->constrained()->cascadeOnDelete();
            $table->primary(['card_id', 'color_id']);
        });

        Schema::create('card_color_identity', function (Blueprint $table) {
            $table->foreignId('card_id')->constrained()->cascadeOnDelete();
            $table->foreignId('color_id')->constrained()->cascadeOnDelete();
            $table->primary(['card_id', 'color_id']);
        });

        Schema::create('card_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('category');
            $table->timestamps();
            $table->unique(['name', 'category']);
        });

        Schema::create('card_card_type', function (Blueprint $table) {
            $table->foreignId('card_id')->constrained()->cascadeOnDelete();
            $table->foreignId('card_type_id')->constrained()->cascadeOnDelete();
            $table->primary(['card_id', 'card_type_id']);
        });

        Schema::create('sets', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name')->nullable();
            $table->timestamps();
        });

        Schema::create('card_printing', function (Blueprint $table) {
            $table->foreignId('card_id')->constrained()->cascadeOnDelete();
            $table->foreignId('set_id')->constrained()->cascadeOnDelete();
            $table->primary(['card_id', 'set_id']);
        });

        Schema::create('formats', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });

        Schema::create('card_legalities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('card_id')->constrained()->cascadeOnDelete();
            $table->foreignId('format_id')->constrained()->cascadeOnDelete();
            $table->string('legality');
            $table->unique(['card_id', 'format_id']);
        });

        Schema::create('card_rulings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('card_id')->constrained()->cascadeOnDelete();
            $table->date('ruling_date')->nullable();
            $table->text('text');
            $table->timestamps();
        });

        Schema::create('card_foreign_names', function (Blueprint $table) {
            $table->id();
            $table->foreignId('card_id')->constrained()->cascadeOnDelete();
            $table->string('language');
            $table->string('name');
            $table->string('multiverse_id')->nullable();
            $table->timestamps();
        });

        Schema::create('card_names', function (Blueprint $table) {
            $table->id();
            $table->foreignId('card_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedInteger('position')->nullable();
            $table->timestamps();
        });

        Schema::create('collections', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('collection_cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('collection_id')->constrained()->cascadeOnDelete();
            $table->foreignId('card_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('quantity')->default(1);
            $table->timestamps();
            $table->unique(['collection_id', 'card_id']);
        });

        Schema::create('card_scans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('card_id')->nullable()->constrained()->nullOnDelete();
            $table->string('original_image_path');
            $table->text('ocr_raw_text')->nullable();
            $table->string('detected_name')->nullable();
            $table->decimal('confidence', 5, 2)->nullable();
            $table->string('status')->default('processing');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('card_scans');
        Schema::dropIfExists('collection_cards');
        Schema::dropIfExists('collections');
        Schema::dropIfExists('card_names');
        Schema::dropIfExists('card_foreign_names');
        Schema::dropIfExists('card_rulings');
        Schema::dropIfExists('card_legalities');
        Schema::dropIfExists('formats');
        Schema::dropIfExists('card_printing');
        Schema::dropIfExists('sets');
        Schema::dropIfExists('card_card_type');
        Schema::dropIfExists('card_types');
        Schema::dropIfExists('card_color_identity');
        Schema::dropIfExists('card_color');
        Schema::dropIfExists('colors');
        Schema::dropIfExists('cards');
    }
};
