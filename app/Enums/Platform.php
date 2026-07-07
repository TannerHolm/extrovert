<?php

namespace App\Enums;

enum Platform: string
{
    case YouTube = 'youtube';
    case Instagram = 'instagram';
    case TikTok = 'tiktok';

    /**
     * Platforms currently available for discovery in the UI.
     *
     * Instagram and TikTok are hidden until their RapidAPI credentials are configured;
     * add them back here to re-enable their search tabs.
     *
     * @return array<self>
     */
    public static function available(): array
    {
        return [self::YouTube];
    }

    public function label(): string
    {
        return match ($this) {
            self::YouTube => 'YouTube',
            self::Instagram => 'Instagram',
            self::TikTok => 'TikTok',
        };
    }

    public function baseUrl(): string
    {
        return match ($this) {
            self::YouTube => 'https://youtube.com',
            self::Instagram => 'https://instagram.com',
            self::TikTok => 'https://tiktok.com',
        };
    }
}
