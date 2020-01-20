<?php

function latlngtopoint($lat, $lng, $zoom)
{
    $pow = pow(2, $zoom);

    $sinLat = sin($lat * pi() / 180);

    $pixelX = floor(256 * ($lng + 180) * $pow / 360);
    $pixelY = floor(256 * $pow * (0.5 - log((1 + $sinLat) / (1 - $sinLat)) / (4 * pi())));

    return array(floor($pixelX / 256), floor($pixelY / 256), floor($pixelX) % 256, floor($pixelY) % 256);
}

function tiletolatlng($z, $x, $y)
{
    $pow = pow(2, $z);
    $tile_max_lat = 90 - 360 * atan(exp(- (0.5 - $y / $pow) * 2 * pi())) / pi();
    $tile_min_lat = 90 - 360 * atan(exp(- (0.5 - ($y + 1) / $pow) * 2 * pi())) / pi();
    $tile_min_lng = 360 * ($x / $pow - 0.5);
    $tile_max_lng = 360 * (($x + 1) / $pow - 0.5);
    return array($tile_min_lng, $tile_max_lng, $tile_min_lat, $tile_max_lat);
}

$bbox = array(
    120 + 1/60,  // 西
    121 + 59/60 + 15/3600, // 東
    21 + 53/60 + 50/3600, // 南
    25 + 18/60 + 20/3600, // 北
);

$zoom = 16;
$tile_ws = (latlngtopoint($bbox[2], $bbox[0], $zoom));
$tile_en = (latlngtopoint($bbox[3], $bbox[1], $zoom));
$x_min = min($tile_ws[0], $tile_en[0]);
$x_max = max($tile_ws[0], $tile_en[0]);
$y_min = min($tile_ws[1], $tile_en[1]);
$y_max = max($tile_ws[1], $tile_en[1]);
for ($tile_x = min($tile_ws[0], $tile_en[0]); $tile_x <= max($tile_ws[0], $tile_en[0]); $tile_x ++) {
    for ($tile_y = min($tile_ws[1], $tile_en[1]); $tile_y <= max($tile_ws[1], $tile_en[1]); $tile_y ++) {
        $target = "tiles/FARM07-{$zoom}-{$tile_x}-{$tile_y}.png";
        if (!file_exists($target) or filesize($target) == 0) {
            continue;
        }
        $md5 = md5_file($target);
        if ($md5 == '41ad1e3d34ec92311b20acb1a37ccef7') {
            continue;
        }
        $target_nurban = "tiles/nURBAN-{$zoom}-{$tile_x}-{$tile_y}.png";
        if (!file_exists($target_nurban) or filesize($target_nurban) == 0) {
            continue;
        }
        $md5 = md5_file($target_nurban);
        if ($md5 == '41ad1e3d34ec92311b20acb1a37ccef7') {
            continue;
        }
        error_log("$target $target_nurban");
        $gd = imagecreatefrompng($target);
        $gd_n = imagecreatefrompng($target_nurban);

        $map = array();
        $group = new StdClass;
        $idx = 0;
        for ($x = 0; $x < 256; $x ++) {
            $map[$x] = array();
            for ($y = 0; $y < 256; $y ++) {
                $map[$x][$y] = -1;
                $rgb = imagecolorat($gd, $x, $y);
                $r = ($rgb >> 16) & 0xFF;
                $g = ($rgb >> 8) & 0xFF;
                $b = $rgb & 0xFF;
                $rgb = sprintf("%02x%02x%02x", $r, $g, $b);
                if ($rgb == '000000') {
                    continue;
                }
                if ($rgb != 'ff0000') {
                    throw new Exception("not red");
                }

                $rgb = imagecolorat($gd, $x, $y);
                $r = ($rgb >> 16) & 0xFF;
                $g = ($rgb >> 8) & 0xFF;
                $b = $rgb & 0xFF;
                $rgb = sprintf("%02x%02x%02x", $r, $g, $b);
                if ($rgb == '000000') {
                    continue;
                }
                if ($rgb != 'ff0000') {
                    throw new Exception("not red");
                }

                $rgb = imagecolorat($gd_n, $x, $y);
                $r = ($rgb >> 16) & 0xFF;
                $g = ($rgb >> 8) & 0xFF;
                $b = $rgb & 0xFF;
                $rgb = sprintf("%02x%02x%02x", $r, $g, $b);
                if ($rgb != 'ffff00' and $rgb != 'ffa500') {
                    continue;
                }

                $hit = array();
                if ($x > 0 and $map[$x - 1][$y] >= 0) {
                    $hit[$map[$x - 1][$y]] = true;
                } 
                if ($y > 0 and $map[$x][$y - 1] >= 0) {
                    $hit[$map[$x][$y - 1]] = true;
                } 
                if ($x > 0 and $y > 0 and $map[$x - 1][$y - 1] >= 0) {
                    $hit[$map[$x - 1][$y - 1]] = true;
                }
                $hit = array_keys($hit);
                if (count($hit)) {
                    $map[$x][$y] = $hit[0];
                    $group->{$hit[0]}[] = array($x, $y);
                    for ($i = 1; $i < count($hit); $i ++) {
                        $group->{$hit[0]} = array_merge($group->{$hit[0]}, $group->{$hit[$i]});
                        foreach ($group->{$hit[$i]} as $x_y) {
                            $map[$x_y[0]][$x_y[1]] = $hit[0];
                        }
                        unset($group->{$hit[$i]});
                    }
                } else {
                    $map[$x][$y] = $idx;
                    $group->{$idx} = array(array($x, $y));
                    $idx ++;
                }
            }
        }
        foreach ($group as $points) {
            echo "{$tile_x},{$tile_y}," . json_encode($points) . "\n";
        }
    }
}
