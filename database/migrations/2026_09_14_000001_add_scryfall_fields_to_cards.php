<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cards', function (Blueprint $table) {
            $table->uuid('oracle_id')->nullable()->after('provider_id');
            $table->string('set_id')->nullable()->after('set_name');
            $table->string('set_type')->nullable()->after('set_id');
            $table->string('image_status')->nullable()->after('image_url');
            $table->json('image_uris')->nullable()->after('image_status');
            $table->json('keywords')->nullable()->after('image_uris');
            $table->json('produced_mana')->nullable()->after('keywords');
            $table->json('color_indicator')->nullable()->after('produced_mana');
            $table->json('finishes')->nullable()->after('color_indicator');
            $table->json('games')->nullable()->after('finishes');
            $table->json('frame_effects')->nullable()->after('games');
            $table->json('promo_types')->nullable()->after('frame_effects');
            $table->json('card_faces')->nullable()->after('promo_types');
            $table->json('all_parts')->nullable()->after('card_faces');
            $table->string('scryfall_uri')->nullable()->after('all_parts');
            $table->string('rulings_uri')->nullable()->after('scryfall_uri');
            $table->string('prints_search_uri')->nullable()->after('rulings_uri');
            $table->boolean('booster')->nullable()->after('starter');
            $table->boolean('digital')->nullable()->after('booster');
            $table->boolean('promo')->nullable()->after('digital');
            $table->boolean('reprint')->nullable()->after('promo');
            $table->boolean('full_art')->nullable()->after('reprint');
            $table->boolean('textless')->nullable()->after('full_art');
            $table->boolean('oversized')->nullable()->after('textless');
            $table->boolean('game_changer')->nullable()->after('oversized');
            $table->unsignedInteger('edhrec_rank')->nullable()->after('game_changer');
            $table->string('security_stamp')->nullable()->after('edhrec_rank');
            $table->string('frame')->nullable()->after('security_stamp');
        });
    }

    public function down(): void
    {
        Schema::table('cards', function (Blueprint $table) {
            $table->dropColumn([
                'oracle_id', 'set_id', 'set_type', 'image_status', 'image_uris', 'keywords',
                'produced_mana', 'color_indicator', 'finishes', 'games', 'frame_effects',
                'promo_types', 'card_faces', 'all_parts', 'scryfall_uri', 'rulings_uri',
                'prints_search_uri', 'booster', 'digital', 'promo', 'reprint', 'full_art',
                'textless', 'oversized', 'game_changer', 'edhrec_rank', 'security_stamp', 'frame',
            ]);
        });
    }
};
