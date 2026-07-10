<?php

namespace App\Services;

use App\Models\Translation;
use Illuminate\Support\Facades\Cache;

class TranslationService
{
    private const CACHE_TTL = 3600; // 1 heure

    /**
     * Retourne toutes les traductions pour une langue donnée.
     * Format : ['tag_key' => 'translated_value', ...]
     * Mise en cache pour éviter une requête SQL à chaque page.
     */
    public static function getForLocale(string $locale): array
    {
        $cacheKey = "translations.{$locale}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($locale) {
            return Translation::where('language_code', $locale)
                ->where('is_active', true)
                ->whereNull('deleted_at')
                ->pluck('translated_value', 'tag_key')
                ->toArray();
        });
    }

    /**
     * Traduit une clé dans la locale donnée.
     * Si la traduction n'existe pas, retourne la clé elle-même.
     */
    public static function translate(string $key, string $locale, array $replace = []): string
    {
        $translations = self::getForLocale($locale);
        $value = $translations[$key] ?? $key;

        // Remplacement de variables : :name → valeur
        foreach ($replace as $placeholder => $replacement) {
            $value = str_replace(":{$placeholder}", $replacement, $value);
        }

        return $value;
    }

    /**
     * Vide le cache des traductions pour une locale (après modification admin).
     */
    public static function clearCache(string $locale): void
    {
        Cache::forget("translations.{$locale}");
    }

    /**
     * Vide le cache de toutes les locales.
     */
    public static function clearAll(): void
    {
        foreach (self::getAvailableLocales() as $locale) {
            self::clearCache($locale);
        }
    }

    /**
     * Retourne la liste des codes de langue disponibles en DB.
     */
    public static function getAvailableLocales(): array
    {
        return Translation::where('is_active', true)
            ->distinct()
            ->pluck('language_code')
            ->toArray();
    }
}
