<?php

function latlngtopoint($lat, $lng, $zoom)
{
    $pow = pow(2, $zoom);

    $sinLat = sin($lat * pi() / 180);

    $pixelX = floor(256 * ($lng + 180) * $pow / 360);
    $pixelY = floor(256 * $pow * (0.5 - log((1 + $sinLat) / (1 - $sinLat)) / (4 * pi())));

    return array(floor($pixelX / 256), floor($pixelY / 256), floor($pixelX) % 256, floor($pixelY) % 256);
}

$bbox = array(
    120 + 1/60,  // 西
    121 + 59/60 + 15/3600, // 東
    21 + 53/60 + 50/3600, // 南
    25 + 18/60 + 20/3600, // 北
);

if (!file_exists('tiles')) {
    mkdir('tiles');
}

$zoom = 15;
$tile_ws = (latlngtopoint($bbox[2], $bbox[0], $zoom));
$tile_en = (latlngtopoint($bbox[3], $bbox[1], $zoom));
$x_min = min($tile_ws[0], $tile_en[0]);
$x_max = max($tile_ws[0], $tile_en[0]);
$y_min = min($tile_ws[1], $tile_en[1]);
$y_max = max($tile_ws[1], $tile_en[1]);
for ($x = min($tile_ws[0], $tile_en[0]); $x <= max($tile_ws[0], $tile_en[0]); $x ++) {
    for ($y = min($tile_ws[1], $tile_en[1]); $y <= max($tile_ws[1], $tile_en[1]); $y ++) {
        error_log("$x $y / $x_max $y_max");
        $target = "tiles/FARM07-{$zoom}-{$x}-{$y}.png";
        if (!file_exists($target) or filesize($target) == 0) {
            system("curl https://wmts.nlsc.gov.tw/wmts/FARM07/default/EPSG:3857/{$zoom}/{$y}/{$x} > tiles/FARM07-{$zoom}-{$x}-{$y}.png");
        }
        $md5 = md5_file($target);
        if ($md5 == '41ad1e3d34ec92311b20acb1a37ccef7') {
            continue;
        }
        $z= 16;
        for ($nx = $x * 2; $nx < $x * 2 + 2; $nx ++) {
            for ($ny = $y * 2; $ny < $y * 2 + 2; $ny ++) {
                $target2 = "FARM07-{$z}-{$nx}-{$ny}.png";
                if (!file_exists($target2) or filesize($target2) == 0) {
                    system("curl https://wmts.nlsc.gov.tw/wmts/FARM07/default/EPSG:3857/{$z}/{$ny}/{$nx} > tiles/FARM07-{$z}-{$nx}-{$ny}.png");
                }
                $target2 = "nURBAN-{$z}-{$nx}-{$ny}.png";
                if (!file_exists($target2) or filesize($target2) == 0) {
                    // https://wmts.nlsc.gov.tw/wmts/nURBAN1/default/GoogleMapsCompatible/16/28271/54731
                    system("curl --referer https://maps.nlsc.gov.tw/T09/mapshow.action https://wmts.nlsc.gov.tw/wmts/nURBAN1/default/EPSG:3857/{$z}/{$ny}/{$nx} > tiles/nURBAN-{$z}-{$nx}-{$ny}.png");
                }
                /*$target2 = "URBAN-{$z}-{$nx}-{$ny}.png";
                if (!file_exists($target2) or filesize($target2) == 0) {
                    system("curl --referer https://maps.nlsc.gov.tw/T09/mapshow.action https://wmts.nlsc.gov.tw/wmts/URBAN/default/EPSG:3857/{$z}/{$ny}/{$nx} > tiles/URBAN-{$z}-{$nx}-{$ny}.png");
                }*/
            }
        }

    }
}
