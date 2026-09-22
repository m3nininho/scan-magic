<?php

use App\Data\ProviderCard;

test('type lines without subtypes produce an empty list', function () {
    expect(ProviderCard::fromScryfall(['type_line' => 'Instant'])->subtypes)->toBe([]);
});

test('combined type lines are parsed separately when faces are unavailable', function () {
    $card = ProviderCard::fromScryfall(['type_line' => 'Creature — Human // Sorcery — Adventure']);

    expect($card->types)->toBe(['Creature', 'Sorcery'])
        ->and($card->subtypes)->toBe(['Human', 'Adventure']);
});

test('face types take precedence and are deduplicated while the original line is preserved', function () {
    $card = ProviderCard::fromScryfall([
        'type_line' => 'Original type line',
        'card_faces' => [
            ['name' => 'Front', 'type_line' => 'Legendary Creature — Human'],
            ['name' => 'Back', 'type_line' => 'Legendary Creature — Human Soldier'],
        ],
    ]);

    expect($card->type)->toBe('Original type line')
        ->and($card->types)->toBe(['Creature'])
        ->and($card->supertypes)->toBe(['Legendary'])
        ->and($card->subtypes)->toBe(['Human', 'Soldier']);
});
