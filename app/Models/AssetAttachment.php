<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssetAttachment extends Model
{
    protected $table = 'asset_attachments';

    protected $fillable = [
        'asset_id',
        'filename',
        'filepath',
        'filetype',
        'label',
        'uploaded_by',
    ];

    public function asset()
    {
        return $this->belongsTo(InventoryAsset::class, 'asset_id', 'asset_id');
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /** Human-readable file size from the stored file */
    public function getFileSizeAttribute(): string
    {
        $path = storage_path('app/public/' . $this->filepath);
        if (!file_exists($path)) return 'N/A';
        $bytes = filesize($path);
        if ($bytes < 1024) return $bytes . ' B';
        if ($bytes < 1048576) return round($bytes / 1024, 1) . ' KB';
        return round($bytes / 1048576, 1) . ' MB';
    }
}
