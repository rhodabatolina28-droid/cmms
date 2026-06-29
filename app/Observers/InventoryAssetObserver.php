<?php

namespace App\Observers;

use App\Models\InventoryAsset;
use App\Services\QrCodeService;

class InventoryAssetObserver
{
    public function created(InventoryAsset $asset): void
    {
        $asset->qr_code = QrCodeService::generateForAsset($asset);
        $asset->saveQuietly();
    }
}
