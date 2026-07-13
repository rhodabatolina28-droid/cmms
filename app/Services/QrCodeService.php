<?php

namespace App\Services;

use App\Models\InventoryAsset;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class QrCodeService
{
    public static function generateForAsset(InventoryAsset $asset): string
    {
        // Use config() instead of env() so it works after config:cache
        // env() returns null when config is cached, config() always works
        $baseUrl = rtrim(config('app.url'), '/');
        $data = $baseUrl . '/r/' . $asset->asset_id;

        $svg = QrCode::format('svg')
            ->size(200)
            ->margin(1)
            ->errorCorrection('L')
            ->generate($data);

        return self::sanitizeSvg($svg);
    }

    public static function regenerateForAll(): int
    {
        $count = 0;
        InventoryAsset::chunk(100, function ($assets) use (&$count) {
            foreach ($assets as $asset) {
                $asset->qr_code = self::generateForAsset($asset);
                $asset->saveQuietly();
                $count++;
            }
        });
        return $count;
    }

    private static function sanitizeSvg(string $svg): string
    {
        // Remove XML declaration/prolog if present (e.g. the prolog before the svg tag)
        $svg = preg_replace('/^<\?xml[^>]*\?>\s*/i', '', trim($svg));

        if (!str_starts_with(trim($svg), '<svg')) {
            return '<svg xmlns="http://www.w3.org/2000/svg" width="200" height="200"><rect width="200" height="200" fill="white"/></svg>';
        }

        $svg = preg_replace('/<script[^>]*>.*?<\/script>/is', '', $svg);
        $svg = preg_replace('/\bon\w+\s*=\s*"[^"]*"/i', '', $svg);
        $svg = preg_replace("/\bon\w+\s*=\s*'[^']*'/i", '', $svg);
        $svg = preg_replace('/<foreignObject[^>]*>.*?<\/foreignObject>/is', '', $svg);
        $svg = preg_replace('/\bhref\s*=\s*"[^"]*"/i', '', $svg);
        $svg = preg_replace("/\bhref\s*=\s*'[^']*'/i", '', $svg);

        return $svg;
    }
}
