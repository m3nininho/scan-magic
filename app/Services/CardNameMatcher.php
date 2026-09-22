<?php

namespace App\Services;

use App\Contracts\CardProviderInterface;
use App\Data\CardMatchResult;
use App\Data\ProviderCard;

class CardNameMatcher
{
    public function __construct(private readonly CardProviderInterface $provider) {}

    public function match(string $rawText): CardMatchResult
    {
        $queries = collect(preg_split('/\R/', $rawText) ?: [])
            ->map(fn (string $line): string => $this->normalize($line))
            ->filter(fn (string $line): bool => mb_strlen($line) >= 3 && preg_match('/\pL{3}/u', $line) === 1)
            ->unique()
            ->take(5)
            ->values();

        if ($queries->isEmpty()) {
            return new CardMatchResult($rawText, null, 0.0, []);
        }

        $candidates = $queries->flatMap(fn (string $query): array => $this->provider->searchByName($query))
            ->unique(fn (ProviderCard $card): string => $card->providerId)
            ->values()
            ->all();
        $best = null;
        $bestConfidence = 0.0;

        foreach ($candidates as $candidate) {
            $confidence = $queries->max(fn (string $query): float => $this->confidence($query, $this->normalize($candidate->name)));
            if ($confidence > $bestConfidence) {
                $best = $candidate;
                $bestConfidence = $confidence;
            }
        }

        return new CardMatchResult($rawText, $best?->name, $bestConfidence, $candidates);
    }

    private function normalize(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = preg_replace('/[^\pL\pN ]/u', '', $value) ?? $value;

        return preg_replace('/\s+/', ' ', $value) ?? $value;
    }

    private function confidence(string $left, string $right): float
    {
        if ($left === $right) {
            return 100.0;
        }

        similar_text($left, $right, $percent);
        $distance = levenshtein($left, $right);

        return round(max(0, min(99, ($percent * 0.7) + max(0, 30 - $distance))), 2);
    }
}
