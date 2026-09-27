<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('decks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('format', 30);
            $table->string('strategy', 240)->nullable();
            $table->foreignId('commander_card_id')->nullable()->constrained('cards')->nullOnDelete();
            $table->timestamps();
        });
        Schema::create('deck_cards', function (Blueprint $table) {
            $table->foreignId('deck_id')->constrained()->cascadeOnDelete();
            $table->foreignId('card_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('quantity');
            $table->string('role', 20)->nullable();
            $table->timestamps();
            $table->primary(['deck_id', 'card_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('deck_cards');
        Schema::dropIfExists('decks');
    }
};
