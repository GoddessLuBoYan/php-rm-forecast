<?php
define('CACHE_FILE', 'allDataCache.php');
define('CACHE_EXPIRE', 86400);

$dataPath = "data/";
$grade = ['', 'D', 'C', 'B', 'A', 'S', 'SS', 'SSS'];
$difficulties = ['', '简单', '一般', '困难'];
$keys = ['', '4键', '5键', '6键'];
$effects = ['', '上隐', '下隐', '闪烁', '镜像'];

$allData = [];
function download() {
    global $dataPath, $allData;
    $tmp_filename = "data.zip";
    $url = "http://game.ds.qq.com/Com_TableCom_Android_Bin/TableComBin.zip";
    $content = file_get_contents($url);
    $fp = fopen($tmp_filename, "wb");
    fwrite($fp, $content);
    fclose($fp);
    $zip = new ZipArchive();
    $zip->open($tmp_filename);
    $zip->extractTo($dataPath);
    $zip->close();
    return;
}

function load() {
    global $allData;
    $cache_ok = false;
    if (file_exists(CACHE_FILE)) {
        require CACHE_FILE;
        if (time() - $data["time"] > CACHE_EXPIRE) {
            $cache_ok = true;
        }
    }
    if(!$cache_ok) {
        download();
        read_map_data();
        read_song_data();
        read_txt_data();
        save();
    }
}

function save() {
    global $allData;
    $allData["time"] = time();
    file_put_contents(CACHE_FILE, '<?php $allData=' . var_export($allData, true) . '?>');
}

function read_map_data() {
    global $dataPath;
    global $allData;
    $map_data = [];
    $map_path = $dataPath."mrock_Map_client.bin";
    $fp = fopen($map_path, "rb");
    fseek($fp, 193);
    for ($j=0; $j < 4; $j++) { 
        $week_data = [];
        for ($i=0; $i < 20; $i++) { 
            $onelevel = [];
            $id = 0;
            $array = unpack("I*", fread($fp, 8));
            $keys = ["Id", "MapId"];
            for ($idx=0; $idx < 2; $idx++) { 
                $onelevel[$keys[$idx]] = $array[$idx+1];
            }
            
            fseek($fp, 5, SEEK_CUR);
            $array = unpack("I*", fread($fp, 44));
            $keys = ["LevelId", "NodeId", "NodeValue", "SongId", "Difficulty", "Keys"
            , "Value1", "Value2", "LevelRecord", "MiniVersion", "Effect"];
            for ($idx=0; $idx < 11; $idx++) { 
                $onelevel[$keys[$idx]] = $array[$idx+1];
            }
            $week_data[$i] = $onelevel;
        }
        $map_data[$j] = $week_data;
    }
    fclose($fp);
    $allData["Map"] = $map_data;
}

function parse_week_map_data($week_data) {
    global $allData, $keys, $difficulties, $effects;
    echo '<table border="1" cellspacing="0">';
    // thead
    echo '<thead><th>';
    foreach(['歌名', '键数', '难度', '要求', '特效'] as $key) {
        echo '<td>'.$key.'</td>';
    }
    echo '</th></thead>';
    // tbody
    echo '<tbody>';
    foreach($week_data as $_ => $data) {
        echo '<tr>';
        $array = [
            $data['LevelId'], // 序号
            $allData['Songs'][$data['SongId']]['songName'], // 歌名
            $keys[$data['Keys']], // 键数
            $difficulties[$data['Difficulty']], // 难度
            get_txt_data($data['NodeId'], $data['NodeValue']), // 要求
            $effects[$data['Effect']]
        ];
        foreach($array as $value) {
            echo '<td>'.$value.'</td>';
        }
        echo '</tr>';
    }
    echo '</tbody>';
    echo '</table>';
}

function get_map_data() {
    global $allData;
    echo '<div id="friend">';
    foreach($allData["Map"] as $key=>$week_data) {
        echo '<p>第'.$key.'组数据</p>';
        parse_week_map_data($week_data);
    }
    echo '</div>';
}

function read_song_data() {
    global $dataPath;
    global $allData;
    $files = [
        $dataPath."mrock_song_client_android.bin",
        $dataPath."mrock_songlevel_client.bin"
    ];
    $data = [];
    foreach ($files as $key => $file) {
        $fp = fopen($file, "rb");
        fseek($fp, 8, SEEK_CUR);
        $array = unpack("I2", fread($fp, 8));
        $size = $array[1];
        $num = $array[2];
        fseek($fp, 120, SEEK_CUR);
        for ($i=0; $i < $num; $i++) { 
            $d = [];
            $d["songId"] = unpack("S", fread($fp, 2))[1];
            $d["iVersion"] = unpack("I", fread($fp, 4))[1];
            $d["songName"] = ltrim(fread($fp, 64));
            $d["songPath"] = ltrim(fread($fp, 64));
            $d["songArtist"] = ltrim(fread($fp, 64));
            $d["songComposer"] = ltrim(fread($fp, 64));

            if ($d["songId"] == 504) { // 双重间谍TGA版本
                $d["songName"] = $d["songName"].'[TGA]';
            }
            fseek($fp, 568, SEEK_CUR);
            $data[$d["songId"]] = $d;
        }
        fclose($fp);
    }
    $allData["Songs"] = $data;
}

function read_txt_data() {
    global $allData, $dataPath;
    $data = [];
    $filename = $dataPath."mrock_txt_client.bin";
    $fp = fopen($filename, "rb");
    fread($fp, 8);
    $array = unpack("I2", fread($fp, 8));
    $size = $array[1];
    $num = $array[2];
    fread($fp, 120);
    for ($i = 0; $i < $num; $i++) {
        $id = unpack("I", fread($fp, 4))[1];
        $text = ltrim(fread($fp, 256));
        $data[$id] = $text;
    }
    $allData["Txt"] = $data;
}

function get_txt_data($nodeId, $nodeValue) {
    global $allData, $grade;
    $nodeTxt = $allData["Txt"][$nodeId];
    if (strpos($nodeTxt, '%s')) {
        return sprintf($nodeTxt, $grade[$nodeValue]);
    } else if(strpos($nodeTxt, '%d')) {
        return sprintf($nodeTxt, $nodeValue);
    } else {
        return $nodeTxt;
    }
}

load();
$allData_json = json_encode($allData, JSON_UNESCAPED_UNICODE);
?>

<!DOCTYPE html>
<html lang="en">


<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>节奏大师预告</title>
</head>
<body>
    <a href="#friend"><h1>好友闯关预告</h1></a>
    <?php 
        get_map_data();
    ?>
</body>
</html>
