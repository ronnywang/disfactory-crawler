<?php
$fetch_time = null;
if (!file_exists('cache')) {
    mkdir('cache');
}

$get_location = function($x, $y) use (&$fetch_time) {
    $target = "cache/location-{$x}-{$y}";
    if (!file_exists($target)) {
        while (!is_null($fetch_time) and microtime(true) - $fetch_time < 1) { usleep(100); };
        $fetch_time = microtime(true);

        error_log("geting {$x} {$y}");
        $curl = curl_init("https://api.nlsc.gov.tw/MapSearch/LocationQuery");
        curl_setopt($curl, CURLOPT_REFERER, "https://maps.nlsc.gov.tw/T09/mapshow.action");
        curl_setopt($curl, CURLOPT_USERAGENT, "Chrome");
        curl_setopt($curl, CURLOPT_POSTFIELDS, "center={$x},{$y}");
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        $content = curl_exec($curl);
        if (!strpos($content, '經緯度')) {
            var_dump($content);
            throw new Exception("{$x},{$y} 失敗");
        }
        curl_close($curl);
        file_put_contents($target, $content);
    }
    return file_get_contents($target);
};
$tile_map_index = function($x, $y, $city, $retry = 0) use (&$fetch_time, $tile_map_index){
    $target = "cache/land-{$x}-{$y}-{$city}";
    if (!file_exists($target)) {
        while (!is_null($fetch_time) and microtime(true) - $fetch_time < 1) { usleep(100); };
        $fetch_time = microtime(true);

        error_log("geting {$x} {$y}");
        $curl = curl_init("https://landmaps.nlsc.gov.tw/S_Maps/qryTileMapIndex?type=2&flag=1&city={$city}&x={$x}&y={$y}&alpha=0.5f");
        curl_setopt($curl, CURLOPT_REFERER, "https://maps.nlsc.gov.tw/T09/mapshow.action");
        curl_setopt($curl, CURLOPT_USERAGENT, "Chrome");
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        $content = curl_exec($curl);
        if (!$obj = json_decode($content) or !is_array($obj) or !$obj or !$obj[0]->landno) {
            if ($obj[0]->msg == '地號查詢無資料!') {
            } else {
                if ($retry < 3) {
                    sleep(1);
                    error_log("retry {$retry}");
                    return $tile_map_index($x, $y, $city, $retry + 1);
                }

                var_dump($content);
                throw new Exception("{$x},{$y},{$city} 失敗");
            }
        }
        curl_close($curl);
        file_put_contents($target, $content);
    }
    return file_get_contents($target);
};
$get_land_info = function($city, $sect, $landno) use (&$fetch_time){
    $target = "cache/landinfo-{$city}-{$sect}-{$landno}";
    if (!file_exists($target)) {
        while (!is_null($fetch_time) and microtime(true) - $fetch_time < 1) { usleep(100); };
        $fetch_time = microtime(true);

        error_log("geting {$city} {$sect} {$landno}");
        $curl = curl_init("https://api.nlsc.gov.tw/S09_Ralid/getLandInfo");
        curl_setopt($curl, CURLOPT_REFERER, "https://maps.nlsc.gov.tw/T09/mapshow.action");
        curl_setopt($curl, CURLOPT_USERAGENT, "Chrome");
        curl_setopt($curl, CURLOPT_POSTFIELDS, "city={$city}&sect={$sect}&landno={$landno}");
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        $content = curl_exec($curl);
        if (!$obj = json_decode($content) or !property_exists($obj, 'ralid')) {
            //var_dump($content);
            throw new Exception("get_land_info {$city} {$sect} {$landno} 失敗");
        }
        curl_close($curl);
        file_put_contents($target, $content);
    }
    return file_get_contents($target);
};
$total = trim(`wc -l points.csv`);
$fp = fopen('points.csv', 'r');
$columns = fgetcsv($fp);
$c = 0;
$output = fopen('php://output', 'w');
fputcsv($output, array(
    '經度', '緯度', 'xmin', 'ymin', 'xmax', 'ymax', '行政區', '國土利用調查', '地政事務所', '地政事務所代碼', '段號', '段名', '地號', '面積', '使用分區', '使用地類別', '公告現值年月', '公告現值',
));
while ($rows = fgetcsv($fp)) {
    $c ++;
    error_log("{$c} / {$total}");
    list($x, $y, $rx, $ry, $xmin, $ymin, $xmax, $ymax) = $rows;

    $content = $get_location($rx, $ry);
    list($city, $body) = explode('@', $content, 2);
    // 行政區:臺南市七股區十份里<br>經緯度:120.044689,23.079515   (度)<br>經緯度:120-02-40.8 23-04-46.2 ( 度分秒)<br>國土利用調查:農業相關設施 (2018年6月)
    preg_match('#行政區:([^<]*)<br>#u', $body, $matches);
    $area = trim($matches[1]);

    preg_match('#國土利用調查:(.*)#u', $body, $matches);
    $landuse = trim($matches[1]);

    $content = $tile_map_index($rx, $ry, $city);
    $obj =  json_decode($content);
    if (!$sect = $obj[0]->sect) {
        if ($obj[0]->msg == '地號查詢無資料!') {
            continue;
        }
        print_r($obj);
        exit;
    };
    $sectStr = base64_decode($obj[0]->sectStr);
    $officeStr= base64_decode($obj[0]->officeStr);
    $office = $obj[0]->office;
    $landno = $obj[0]->landno;
    $city = $obj[0]->office[0];

    error_log($rx . ' ' . $ry);
    try {
        $content = $get_land_info($city, $sect, $landno);
    } catch (Exception $e) {
    }
    $obj =  json_decode($content);
    $ralid = $obj->ralid;
    /*
    '經度', '緯度', 'xmin', 'ymin', 'xmax', 'ymax', '行政區', '國土利用調查', '地政事務所', '地政事務所代碼', '段號', '段名', '地號', '面積', '使用分區', '使用地類別', '公告現值年月', '公告現值',
     */
    fputcsv($output, array(
        $x, $y, $xmin, $ymin, $xmax, $ymax, $area, $landuse, $officeStr, $office, $sect, $sectStr, $landno, $ralid->AA10, base64_decode($ralid->AA11), base64_decode($ralid->AA12), $ralid->AA27, $ralid->AA16
    ));
}
