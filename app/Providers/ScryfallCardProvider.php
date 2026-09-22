<?php

namespace App\Providers;

use App\Contracts\CardProviderInterface;
use App\Data\ProviderCard;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class ScryfallCardProvider implements CardProviderInterface
{
    private function client(): PendingRequest
    {
        return Http::baseUrl((string) config('services.scryfall.base_url'))
            ->withHeaders(['User-Agent' => 'ScanMagic/1.0'])
            ->acceptJson()
            ->connectTimeout((int) config('services.scryfall.connect_timeout', 3))
            ->timeout((int) config('services.scryfall.timeout', 10));
    }

    public function searchByName(string $name): array
    {
        $name = trim($name);
        if ($name === '') {
            return [];
        }

        return Cache::remember(
            'scryfall:name:'.hash('sha256', mb_strtolower($name)),
            (int) config('services.scryfall.cache_ttl', 3600),
            function () use ($name): array {
                $response = $this->client()->get('/cards/named', ['exact' => $name]);
                if ($response->successful() && is_array($response->json())) {
                    return [ProviderCard::fromScryfall($response->json())];
                }

                if (! $response->notFound()) {
                    $response->throw();
                }

                $response = $this->client()->get('/cards/named', ['fuzzy' => $name]);

                if (! $response->successful() && ! $response->notFound()) {
                    $response->throw();
                }

                return $response->successful() && is_array($response->json())
                    ? [ProviderCard::fromScryfall($response->json())]
                    : [];
            },
        );
    }

    public function find(string $id): ?ProviderCard
    {
        return Cache::remember(
            'scryfall:card:'.hash('sha256', $id),
            (int) config('services.scryfall.cache_ttl', 3600),
            function () use ($id): ?ProviderCard {
                $response = $this->client()->get("/cards/{$id}");

                if (! $response->successful() && ! $response->notFound()) {
                    $response->throw();
                }

                return $response->successful() && is_array($response->json())
                    ? ProviderCard::fromScryfall($response->json())
                    : null;
            },
        );
    }
}
