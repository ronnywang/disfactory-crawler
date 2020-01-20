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
$fp = fopen('result', 'r');
$output = fopen('php://output', 'w');
fputcsv($output, array('x', 'y', 'rx', 'ry', 'xmin', 'ymin', 'xmax', 'ymax'));

while ($line = fgets($fp)) {
    list($tile_x, $tile_y, $points) = explode(',', $line, 3);
    $points = json_decode($points);
    $x_min = $points[0][0];
    $x_max = $points[0][0];
    $y_min = 256 - $points[0][1];
    $y_max = 256 - $points[0][1];
    $map = array();
    foreach ($points as $x_y) {
        $x_min = min($x_y[0], $x_min);
        $x_max = max($x_y[0], $x_max);
        $y_min = min(256 - $x_y[1], $y_min);
        $y_max = max(256 - $x_y[1], $y_max);

        list($x, $y) = $x_y;
        $map[$x . ':' . $y] = true;
    }

    $represent_point = null;
    $represent_point_distance = 0;
    foreach ($points as $x_y) {
        list($x, $y) = $x_y;;
        // 往上下左右找距離最短的
        $distance = null;
        foreach (array(array(1,0), array(0,1), array(-1,0), array(0,-1)) as $way) {
            $cx = $x;
            $cy = $y;
            $d = 0;
            while (is_null($distance) or $d < $distance) {
                $cx += $way[0];
                $cy += $way[1];
                if (!array_key_exists($cx . ':' . $cy, $map)) {
                    $distance = $d;
                    break;
                }
                $d ++;
            }
        }
        if (is_null($represent_point) or $represent_point_distance < $distance) {
            $represent_point = $x_y;
        }
    }
    $x = ($x_min + $x_max) / 2;
    $y = ($y_min + $y_max) / 2;

    list($tile_min_lng, $tile_max_lng, $tile_min_lat, $tile_max_lat) = tiletolatlng(16, $tile_x, $tile_y);
    $x = $tile_min_lng + ($x / 256) * ($tile_max_lng - $tile_min_lng);
    $y = $tile_min_lat + ($y / 256) * ($tile_max_lat - $tile_min_lat);
    $cx = $tile_min_lng + ($represent_point[0] / 256) * ($tile_max_lng - $tile_min_lng);
    $cy = $tile_min_lat + ((256 - $represent_point[1]) / 256) * ($tile_max_lat - $tile_min_lat);
    $x_min = $tile_min_lng + ($x_min / 256) * ($tile_max_lng - $tile_min_lng);
    $y_min = $tile_min_lat + ($y_min / 256) * ($tile_max_lat - $tile_min_lat);
    $x_max = $tile_min_lng + ($x_max / 256) * ($tile_max_lng - $tile_min_lng);
    $y_max = $tile_min_lat + ($y_max / 256) * ($tile_max_lat - $tile_min_lat);
    fputcsv($output, array(
        $x, $y, $cx, $cy, $x_min, $y_min, $x_max, $y_max,
    ));
}
