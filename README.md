違法農地工廠資料抓取
====================
這是在 2019/7/20 的 [g0v 第參拾伍次最旁邊黑客松](https://beta.hackfoldr.org/g0v-hackath35n/https%253A%252F%252Fdocs.google.com%252Fspreadsheets%252Fd%252F1USJdfE8FW5L4cYeE0yXiQZNLYzA38IFW--GKbacNP-4%252Fedit)，小海提案 [違章工廠舉報系統](https://drive.google.com/file/d/1MB3xjIwZd8NbgH39ax6ojsy76HjaN6Rh/view?usp=sharing) ，根據 [當日成果](https://docs.google.com/document/d/102m47zR9ifDG-kP3_QqNdWRzsl-_4AaN4Alty6jH9uE/edit?ts=5d32d5e2) 內「圖層比較流程」段落的步驟，寫成爬蟲，產生出 [資料結果](https://gist.github.com/ronnywang/f8bbf008e641b296c755f0167b51a550) 

注意事項
--------
* 爬蟲行為非真實人類行為，若要將這爬蟲修改去使用，請注意抓取頻率不要太過快速，以免造成原服務的影響

檔案說明
--------
* php crawl-tile.php
  * 從 [國土測繪圖資服務雲](https://maps.nlsc.gov.tw/) 抓取 FARM07 (農業及農地資源盤查 => 農地資源盤查\_工廠) 和 nURBAN1 (土地圖層 => 非都市土地使用分區圖(II)) 的圖磚，抓取到 tiles/(FARM07|nURBAN)-$Z-$X-$Y.png ，其中 x, y, z 的範圍是 z=15, x=120度1分 ~ 121度59分15秒, y=21度53分50秒 ~ 25度18分20秒 (台灣本島範圍) 的所有圖磚，Z 的範圍會先從 zoom=15 抓取，如果抓到是空白就不會再抓 zoom=16
* php check.php > result
  * 透過 gd lib 將 crawl-tile.php 抓的圖磚一一檢查是否 FARM07 顏色是紅色（農地資源盤查是工廠的）以及 nURBAN 是橘色（一般農業區），將重合的範圍的點位以「54630,28445,[[29,195],[29,196],[29,197],[29,198],[29,199],[29,200],[29,201],[29,202],[30,195],[30,196],[30,197],[30,198],[30,199],[30,200],[30,201],[30,202]]」的格式輸出（tile_x, tile_y, points_json）
* php check-merge.php
  * 將 result 裡面各別 tile 的結果，如果有範圍是跟臨近 tile 連接的話，就把他合併，並輸出到 parent.json
  * 這隻因為速度有點慢加上耗記憶體大，因此並未被使用
* php list.php > points.csv
  * 將 result 裡面的 tile_x, tile_y 跟圖磚的點位，轉換回經緯度，取出每個多邊形的四個邊界
  * 輸出欄位有 x,y,rx,ry,xmin,ymin,xmax,ymax
  * x, y: 是在多邊形狀內的盡量中心點（假如是中空或是C字形的多邊形，中心點有可能並不是多邊形範圍內，因此這個 x, y 是用來想辦法找出一個一定是在圖形內並盡量靠中心的點
  * rx, ry: 是多邊形形狀的邊界中心，如果是中空或 C 字形這個點可能不在圖形內
  * xmin, ymin, xmax, ymax: 圖形的四個邊界
* php crawl-info.php > full-info.csv
  * 透過 points.csv 的各經緯度位置查詢「行政區,國土利用調查,地政事務所,地政事務所代碼,段號,段名,地號,面積,使用分區 >使用地類別,公告現值年月,公告現值」等欄位

授權方式
--------
* 程式碼以 BSD License 開放授權
