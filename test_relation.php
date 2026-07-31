<?php
require 'vendor/autoload.php';
require 'bootstrap/app.php';
$req = \App\Models\Request::whereNotNull('linked_asset_id')->first();
if($req){
    echo 'Req ID: '.$req->id.', LinkedAssetID: '.$req->linked_asset_id."\n";
    $asset = $req->linkedAsset;
    if($asset){
        echo ' => Asset: '.$asset->item_name."\n";
    } else {
        echo ' => ASSET RELATION IS NULL'."\n";
    }
} else {
    echo 'No request with linked asset found'."\n";
}
