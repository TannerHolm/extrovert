<?php

namespace App\Enums;

enum Platform: string
{
    case YouTube = 'youtube';
    case Instagram = 'instagram';
    case TikTok = 'tiktok';

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
