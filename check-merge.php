<?php
ini_set('memory_limit', '2g');
$fp = fopen('result', 'r');
$total = trim(`wc -l result`);
$merges = array();
$tiles_edges = array();
$init = function($x, $y, $way) use (&$tiles_edges) {
    if (array_key_exists($y, $tiles_edges[$x])) {
        return;
    }
    error_log("hit {$x} {$y} {$way}");
    $tiles_edges[$x][$y] = array(
        'left' => array(),
        'top' => array(),
        'bottom' => array(),
        'right' => array(),
    );
};

$parents = array();
$check = function($tile_x, $tile_y, $way, $pos) use (&$tiles_edges, &$parents){
    if ($way == 'left') {
        $t_way = 'right';
        $t_tile_x = $tile_x - 1;
        $t_tile_y = $tile_y;
    } elseif ($way == 'right') {
        $t_way = 'left';
        $t_tile_x = $tile_x + 1;
        $t_tile_y = $tile_y;
    } elseif ($way == 'top') {
        $t_way = 'bottom';
        $t_tile_x = $tile_x;
        $t_tile_y = $tile_y - 1;
    } elseif ($way == 'bottom') {
        $t_way = 'top';
        $t_tile_x = $tile_x;
        $t_tile_y = $tile_y + 1;
    }

    if (!array_key_exists($t_tile_x, $tiles_edges)) {
        return;
    }
    if (!array_key_exists($t_tile_y, $tiles_edges[$t_tile_x])) {
        return;
    }
    if (!array_key_exists($pos, $tiles_edges[$t_tile_x][$t_tile_y][$t_way])) {
        return;
    }
    //$tiles_edges[$tile_x][$tile_y]['left'][$y] = $points[0];
    $x_y = implode('-', $tiles_edges[$tile_x][$tile_y][$way][$pos]);
    $parents[$t_tile_x . '-' . $t_tile_y . '-' . $x_y] = array($t_tile_x, $t_tile_y, $tiles_edges[$t_tile_x][$t_tile_y][$t_way][$pos]);
    echo json_encode($parents). "\n";
};

$c = 0;
while ($line = fgets($fp)) {
    $c ++;
    error_log("$c / $total");
    list($tile_x, $tile_y, $points) = explode(',', $line, 3);
    if (!array_key_Exists($tile_x, $tiles_edges)) {
        $tiles_edges[$tile_x] = array();
    }

    $points = json_decode($points);
    foreach ($points as $point) {
        list($x, $y) = $point;
        if ($x == 0) {
            $init($tile_x, $tile_y, 'left');
            $tiles_edges[$tile_x][$tile_y]['left'][$y] = $points[0];
            $check($tile_x, $tile_y, 'left', $y);
        } elseif ($x == 255) {
            $init($tile_x, $tile_y, 'right');
            $tiles_edges[$tile_x][$tile_y]['right'][$y] = $points[0];
            $check($tile_x, $tile_y, 'right', $y);
        }
        if ($y == 0) {
            $init($tile_x, $tile_y, 'top');
            $tiles_edges[$tile_x][$tile_y]['top'][$x] = $points[0];
            $check($tile_x, $tile_y, 'top', $x);
        } elseif ($y == 255) {
            $init($tile_x, $tile_y, 'bottom');
            $tiles_edges[$tile_x][$tile_y]['bottom'][$x] = $points[0];
            $check($tile_x, $tile_y, 'bottom', $x);
        }

        file_put_contents('parent.json', json_encode($parents));
    }
}


