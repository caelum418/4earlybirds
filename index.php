<?php
$time_start = microtime(true);
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
$accessToken = 'HERE_IS_API_TOKEN';

$content = file_get_contents('php://input');
$json_obj = json_decode($content,true);

$reply_token = $json_obj['events'][0]['replyToken'];
$type = $json_obj['events'][0]['type'];
$msg_obj = $json_obj['events'][0]['message']['type'];
$msg_text = $json_obj['events'][0]['message']['text'];

$userId = $json_obj['events'][0]['source']['userId'];

// require_once '../../dbconfig/db_config.php';
// this is connecting DB file include password and so on.

try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
    $pdo = new PDO($dsn, DB_USER, DB_PASSWORD);
} catch (PDOException $e) {
    echo 'DB接続エラー: ' . $e->getMessage();
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM linebot WHERE userId = :userId");
$stmt->bindParam(':userId', $userId);
$stmt->execute();

if ($stmt->rowCount() == 0 && !empty($userId)) {
    $insertStmt = $pdo->prepare("INSERT INTO linebot (userId) VALUES (:userId)");
    $insertStmt->bindParam(':userId', $userId);
    $insertStmt->execute();
    echo "新しいユーザーIDが追加されました。";
} else {
    echo "ユーザーIDは既に存在します。";
}
// firstFlag変数
$selectStmt = $pdo->prepare("SELECT firstFlag FROM linebot WHERE userId = :userId");
$selectStmt->bindParam(':userId', $userId);
$selectStmt->execute();
$row = $selectStmt->fetch(PDO::FETCH_ASSOC);
$firstFlag = $row ? $row['firstFlag'] : null;

// settingFlag変数
$select2Stmt = $pdo->prepare("SELECT settingFlag FROM linebot WHERE userId = :userId");
$select2Stmt->bindParam(':userId', $userId);
$select2Stmt->execute();
$row2 = $select2Stmt->fetch(PDO::FETCH_ASSOC);
$settingFlag = $row2 ? $row2['settingFlag'] : null;

// city変数
$selectCityStmt = $pdo->prepare("SELECT city FROM linebot WHERE userId = :userId");
$selectCityStmt->bindParam(':userId', $userId);
$selectCityStmt->execute();
$cityRow = $selectCityStmt->fetch(PDO::FETCH_ASSOC);
$inputCityName = $cityRow['city'];

// weatherFlag変数
$weatherFlagStmt = $pdo->prepare("SELECT weatherFlag FROM linebot WHERE userId = :userId");
$weatherFlagStmt->bindParam(':userId', $userId);
$weatherFlagStmt->execute();
$weatherFlagRow = $weatherFlagStmt->fetch(PDO::FETCH_ASSOC);
$weatherFlag = $weatherFlagRow['weatherFlag'];

// line変数
$selectLineStmt = $pdo->prepare("SELECT line FROM linebot WHERE userId = :userId");
$selectLineStmt->bindParam(':userId', $userId);
$selectLineStmt->execute();
$lineRow = $selectLineStmt->fetch(PDO::FETCH_ASSOC);
$inputLineName = $lineRow['line'];

// slectedLine変数
$selectedLineStmt = $pdo->prepare("SELECT selectedLine FROM linebot WHERE userId = :userId");
$selectedLineStmt->bindParam(':userId', $userId);
$selectedLineStmt->execute();
$selectedLineRow = $selectedLineStmt->fetch(PDO::FETCH_ASSOC);
$inputSelectedLineName = $selectedLineRow['selectedLine'];

// exchange変数
$selectExchangeStmt = $pdo->prepare("SELECT exchange FROM linebot WHERE userId = :userId");
$selectExchangeStmt->bindParam(':userId', $userId);
$selectExchangeStmt->execute();
$exchangeRow = $selectExchangeStmt->fetch(PDO::FETCH_ASSOC);
$inputExchangeName = $exchangeRow['exchange'];

// exchangeFlag変数
$exchangeFlagStmt = $pdo->prepare("SELECT exchangeFlag FROM linebot WHERe userId = :userId");
$exchangeFlagStmt->bindParam(':userId', $userId);
$exchangeFlagStmt->execute();
$exchangeFlagRow = $exchangeFlagStmt->fetch(PDO::FETCH_ASSOC);
$exchangeFlag = $exchangeFlagRow['exchangeFlag'];

// moreInfoFlag変数
$moreInfoStmt = $pdo->prepare("SELECT moreInfo FROM linebot WHERE userId = :userId");
$moreInfoStmt->bindParam(':userId', $userId);
$moreInfoStmt->execute();
$moreInfoRow = $moreInfoStmt->fetch(PDO::FETCH_ASSOC);
$moreInfo = $moreInfoRow['moreInfo'];

$lines = [
    "hokurikubiwako", //0
    "kyoto", //1
    "kobesanyo", //2
    "ako", //3
    "kosei", //4
    "kusatsu", //5 
    "nara", //6
    "sagano", //7
    "sanin1", //8
    "sanin2", //9
    "osakahigashi", //10
    "takarazuka", //11
    "fukuchiyama", //12
    "tozai", //13
    "gakkentoshi", //14
    "bantan", //15
    "maizuru", //16
    "osakaloop", //17
    "yumesaki", //18
    "yamatoji", //19
    "hanwahagoromo", //20
    "kansaiairport", //21
    "wakayama1", //22
    "wakayama2", //23
    "manyomahoroba", //24
    "kansai", //25
    "kinokuni" //26
];

function delayTrainInfo($lastSelectedLine) {
    $url = 'https://www.train-guide.westjr.co.jp/api/v3/' . $lastSelectedLine . '.json';
    $jsonRawData = file_get_contents($url);
    $jsonData = json_decode($jsonRawData, true);
    $trainData = $jsonData['trains'];

    $numberOfTrain = count($trainData);
    $inboundDirectionArray = ["上り線"]; //上り線の遅延情報
    $outboundDirectionArray = ["下り線"]; //下り線の遅延情報
    $delayInformationArray = [];

    for ($i = 0; $i < $numberOfTrain; $i++) {
        $delayMinutes = $trainData[$i]['delayMinutes']; //遅延分数
        $direction = $trainData[$i]['direction']; // inbound/outbound
        if ($direction == 0) {
            if ($delayMinutes >1 && $delayMinutes < 5) {
                $inboundDirectionArray[] = "遅れ  " . $trainData[$i]['displayType'] . $trainData[$i]['dest']['text'] . "行き  " . $delayMinutes . " 分遅れ";
            } else if ($delayMinutes >= 5 && $delayMinutes < 20) {
                $inboundDirectionArray[] = "大きな遅れ  " . $trainData[$i]['displayType'] . $trainData[$i]['dest']['text'] . "行き  " . $delayMinutes . " 分遅れ";
            } else if ($delayMinutes >= 20) {
                $inboundDirectionArray[] = "深刻な遅れ  " . $trainData[$i]['displayType'] . $trainData[$i]['dest']['text'] . "行き  " . $delayMinutes . " 分遅れ";
            }
        } else {
            if ($delayMinutes >1 && $delayMinutes < 5) {
                $outboundDirectionArray[] = "遅れ  " . $trainData[$i]['displayType'] . $trainData[$i]['dest']['text'] . "行き  " . $delayMinutes . " 分遅れ";
            } else if ($delayMinutes >= 5 && $delayMinutes < 20) {
                $outboundDirectionArray[] = "大きな遅れ  " . $trainData[$i]['displayType'] . $trainData[$i]['dest']['text'] . "行き  " . $delayMinutes . " 分遅れ";
            } else if ($delayMinutes >= 20) {
                $outboundDirectionArray[] = "深刻な遅れ  " . $trainData[$i]['displayType'] . $trainData[$i]['dest']['text'] . "行き  " . $delayMinutes . " 分遅れ";
            }
        }
    }

    $delayInformationArray = array_merge($inboundDirectionArray, $outboundDirectionArray);
    
    if (count($delayInformationArray) > 2) {
        $delayTrainInfo = implode("\n", $delayInformationArray);
    } else {
        $delayTrainInfo = '遅延している電車はありません。';
    }

    return $delayTrainInfo;
}

function delayInfo($userId, $pdo, $line) {
    $setMoreInfoStmt = $pdo->prepare("UPDATE linebot SET moreInfo = 1 WHERE userId = :userId");
    $setMoreInfoStmt->bindParam(':userId', $userId);
    $setMoreInfoStmt->execute();

    $url = 'https://www.train-guide.westjr.co.jp/api/v3/' . $line . '.json';
    $jsonRawData = file_get_contents($url);
    $jsonData = json_decode($jsonRawData, true);
    $trainData = $jsonData['trains'];

    $numberOfTrain = count($trainData);
    $numberOfVeryShortDelayedTrain = 0;
    $numberOfShortDelayedTrain = 0;
    $numberOfDelayedTrain = 0;
    $numberOfLongDelayedTrain = 0;
    $delaySeverity = 0;
    $delaySeverityText = "";

    // 2分以上の遅れを遅延とカウントする
    // 2分~4分以内の遅れを遅延とし、遅延度に0.25ポイント加算する
    // 5分~19分以内の遅れを大きな遅延とし、遅延度に1ポイント加算する
    // 20分以上の遅れを深刻な遅れとし、遅延度に3ポイント加算する

    for ($i = 0; $i < $numberOfTrain; $i++) {
        $delayMinutes = $trainData[$i]['delayMinutes'];
        if ($delayMinutes >1 && $delayMinutes < 5) {
            $numberOfVeryShortDelayedTrain += 1;
            $delaySeverity += 0.25;
        } else if ($delayMinutes >= 5 && $delayMinutes < 15) {
            $numberOfShortDelayedTrain += 1;
            $delaySeverity += 1;
        } else if ($delayMinutes >= 15 && $delayMinutes < 30) {
            $numberOfDelayedTrain += 1;
            $delaySeverity += 2;
        } else if ($delayMinutes >= 30) {
            $numberOfLongDelayedTrain += 1;
            $delaySeverity += 3;
        }
    }

    $sumOfDelayedTrain = $numberOfVeryShortDelayedTrain + $numberOfShortDelayedTrain + $numberOfDelayedTrain + $numberOfLongDelayedTrain;

    if ($delaySeverity <= 1) {
        $delaySeverityText = "遅延状況：" . date('h時i分') . "現在、遅延は発生していません。";
        return $delaySeverityText;
    } else if ($delaySeverity > 1 && $delaySeverity < $numberOfTrain/3) {
        $delaySeverityText = "遅延状況：" . date('h時i分'). "現在、遅延が発生しています。\n遅延本数：  " . $sumOfDelayedTrain . " 本が遅延しています。\n遅延している列車の情報をご希望の場合、列車ボタンまたは「詳細」と送信してください。\n詳しくは走行位置情報をご確認ください。\nhttps://www.train-guide.westjr.co.jp/" . $line . ".html";
        return $delaySeverityText;
    } else if ($delaySeverity >= $numberOfTrain/3 && $delaySeverity < $numberOfTrain) {
        $delaySeverityText = "遅延状況：" . date('h時i分') . "現在、大きな遅延が発生しています。\n遅延本数：  " . $sumOfDelayedTrain . " 本が遅延しています。\n遅延している列車の列車情報をご希望の場合、列車ボタンまたは「詳細」と送信してください。\n詳しくは走行位置情報をご確認ください。\nhttps://www.train-guide.westjr.co.jp/" . $line . ".html";
        return $delaySeverityText;
    } else if ($delaySeverity >= $numberOfTrain) {
        $delaySeverityText = "遅延状況：" . date('h時i分') . "現在、深刻な遅延が発生しています。\n遅延本数：  " . $sumOfDelayedTrain . " 本が遅延しています。\n遅延している列車の列車情報をご希望の場合、列車ボタンまたは「詳細」と送信してください。\n詳しくは走行位置情報をご確認ください。\nhttps://www.train-guide.westjr.co.jp/" . $line . ".html";
        return $delaySeverityText;
    }
}

function weatherInfo($inputCityName) {
    $cityNameEncoded = urlencode($inputCityName);
    $url = "https://map.yahooapis.jp/geocode/V1/geoCoder?appid="HERE_IS_API_KEY"&query=" . $cityNameEncoded;
    $xmlString = file_get_contents($url);

    $xml = simplexml_load_string($xmlString);
    $firstFeature = $xml->Feature[0];
    $coordinates = $firstFeature->Geometry->Coordinates;
    $cityName = $firstFeature->Name;
    $coordinatesArray = explode(",", $coordinates);

    $longitude = $coordinatesArray[0];
    $latitude = $coordinatesArray[1];

    $apiKey = "HERE_IS_API_KEY";
    $apiUrl = "https://api.openweathermap.org/data/3.0/onecall?lat=" . $latitude . "&lon=" . $longitude . "&lang=ja&exclude=minutely,hourly&appid=" . $apiKey . "&units=metric";

    $weatherData = json_decode(file_get_contents($apiUrl), true);

    $temp = round($weatherData['current']['temp'], 1);
    $humidity = $weatherData['current']['temp'];
    $windSpeed = round($weatherData['current']['temp'], 1);
    $uvi = $weatherData['current']['temp'];
    $weather = $weatherData['current']['weather'][0]['main'];

    $maxTemp = round($weatherData['daily'][0]['temp']['max'], 1);
    $minTemp = round($weatherData['daily'][0]['temp']['min'], 1);
    $weatherDay = $weatherData['daily'][0]['weather'][0]['main'];

    if (intval($temp) == $temp) {
        $temp = intval($temp);
    }
    if (intval($maxTemp) == $maxTemp) {
        $maxTemp = intval($maxTemp);
    }
    if (intval($minTemp) == $minTemp) {
        $minTemp = intval($minTemp);
    }
    if (intval($windSpeed) == $windSpeed) {
        $windSpeed = intval($windSpeed);
    }
    switch ($weather) {
        case "Clear":
            $weather = "快晴";
            $description = "";
            break;
        case "Clouds":
            $weather = "曇り";
            break;
        case "Rain":
            $weather = "雨";
            break;
        case "Drizzle":
            $weather = "霧雨";
            $description = "";
            break;
        case "Thunderstorm":
            $weather = "雷雨";
            $description = "";
            break;
        case "Snow":
            $weather = "雪";
            $description = "";
            break;
        case "Mist":
            $weather = "霧";
            $description = "";
            break;
        case "Smoke":
            $weather = "煙霧";
            $description = "";
            break;
        case "Fog":
            $weather = "濃霧";
            $description = "";
            break;
    }
    switch ($weatherDay) {
        case "Clear":
            $weatherDay = "快晴";
            $description = "";
            break;
        case "Clouds":
            $weatherDay = "曇り";
            break;
        case "Rain":
            $weatherDay = "雨";
            break;
        case "Drizzle":
            $weatherDay = "霧雨";
            $description = "";
            break;
        case "Thunderstorm":
            $weatherDay = "雷雨";
            $description = "";
            break;
        case "Snow":
            $weatherDay = "雪";
            $description = "";
            break;
        case "Mist":
            $weatherDay = "霧";
            $description = "";
            break;
        case "Smoke":
            $weatherDayweather = "煙霧";
            $description = "";
            break;
        case "Fog":
            $weatherDay= "濃霧";
            $description = "";
            break;
    }
    return date('m月d日') . $cityName . "\n一日の天気：" . $weatherDay . "\n最高/最低気温：" . $maxTemp . "℃ / " . $minTemp . "℃\n" . date('H') . "時現在の天気：" . $weather . "\n気温：" . $temp . "℃\n風速：" . $windSpeed . "m/s";
}


if ($moreInfo == 1 && $msg_text !== "鉄道") {
    $setMoreInfoStmt = $pdo->prepare("UPDATE linebot SET moreInfo = 0 WHERE userId = :userId");
    $setMoreInfoStmt->bindParam(':userId', $userId);
    $setMoreInfoStmt->execute();
}

if ($firstFlag == 0) {
    $updateFirstFlagStmt = $pdo->prepare("UPDATE linebot SET firstFlag = 1 WHERE userId = :userId");
    $updateFirstFlagStmt->bindParam(':userId', $userId);
    $updateFirstFlagStmt->execute();
    $message = [
        'type' => 'text',
        'text' => "はじめまして！\n自動送信の為、路線・都市・為替のユーザー設定をお願いします。設定に進む場合は『設定』と送信してください。\nまた、そのままでもご利用いただけます。\n使用できるコマンドは以下の通りです。\n『天気』\n『鉄道』\n『為替』\n『設定』"
    ];
} else if ($msg_text == "設定") {
    $updateSettingFlagStmt = $pdo->prepare("UPDATE linebot SET settingFlag = 1 WHERE userId = :userId");
    $updateSettingFlagStmt->bindParam(':userId', $userId);
    $updateSettingFlagStmt->execute();
    $message = [
        'type' => 'text',
        'text' => "既定の路線・都市・為替を設定します。\n『路線名 都市名 通貨ペア』となるメッセージをスペースで区切って送信してください。\n例『神戸線 大阪 ドル円』\n設定を初期化する場合は、『初期化』と送信してください。"
    ];
} else if ($settingFlag == 1) {
    $updateSettingFlagStmt = $pdo->prepare("UPDATE linebot SET settingFlag = 0 WHERE userId = :userId");
    $updateSettingFlagStmt->bindParam(':userId', $userId);
    $updateSettingFlagStmt->execute();

    $updateLineStmt = $pdo->prepare("UPDATE linebot SET line = :lines WHERE userId = :userId");
    $updateLineStmt->bindParam(':userId', $userId);

    $messageArray = explode(" ", $msg_text);
    $setLineError = "";
    $setCityError = "";
    $setExchangeError ="";
    
    if ($msg_text == "初期化") {
        $updateLineStmt = $pdo->prepare("UPDATE linebot SET line = '' WHERE userId = :userId");
        $updateLineStmt->bindParam(':userId', $userId);
        $updateLineStmt->execute();

        $updateCityStmt = $pdo->prepare("UPDATE linebot SET city = '' WHERE userId = :userId");
        $updateCityStmt->bindParam(':userId', $userId);
        $updateCityStmt->execute();

        $updateExchangeStmt = $pdo->prepare("UPDATE linebot SET exchange = '' WHERE userId = :userId");
        $updateExchangeStmt->bindParam(':userId', $userId);
        $updateExchangeStmt->execute();
        $message = [
            'type' => 'text',
            'text' => "設定の初期化が完了しました。"
        ];
    } else if (count($messageArray) < 3) {
        $message = [
            'type' => 'text',
            'text' => "設定の記述数が足りていません。記述数：" . count($messageArray) . "個\n一度の送信で三つ全てを、それぞれスペースで区切って送信してください。\nもう一度『設定』の送信からやり直してください。"
        ];
    } else {
        $setLine = $messageArray[0];
        $setCity = $messageArray[1];
        $setExchange = $messageArray[2];
        switch($setLine) {
            case "京都線":
                $updateLineStmt->bindParam(':lines', $lines[1]);
                $updateLineStmt->execute();
                break;
            case "神戸線":
            case "山陽線":
                $updateLineStmt->bindParam(':lines', $lines[2]);
                $updateLineStmt->execute();
                break;
            case "環状線":
                $updateLineStmt->bindParam(':lines', $lines[17]);
                $updateLineStmt->execute();
                break;
            case "宝塚線":
                $updateLineStmt->bindParam(':lines', $lines[11]);
                $updateLineStmt->execute();
                break;
            case "東西線":
                $updateLineStmt->bindParam(':lines', $lines[13]);
                $updateLineStmt->execute();
                break;
            case "福知山線":
                $updateLineStmt->bindParam(':lines', $lines[12]);
                $updateLineStmt->execute();
                break;
            case "桜島線":
                $updateLineStmt->bindParam(':lines', $lines[18]);
                $updateLineStmt->execute();
                break;
            case "阪和線":
                $updateLineStmt->bindParam(':lines', $lines[20]);
                $updateLineStmt->execute();
                break;
            default:
                $setLineError = "路線名";
                break;
        }

        $updateCityStmt = $pdo->prepare("UPDATE linebot SET city = :setCity WHERE userId = :userId");
        $updateCityStmt->bindParam(':setCity', $setCity);
        $updateCityStmt->bindParam(':userId', $userId);
        $updateCityStmt->execute();

        $updateExchangeStmt = $pdo->prepare("UPDATE linebot SET exchange = :setExchange WHERE userId = :userId");
        $updateExchangeStmt->bindParam(':setExchange', $setExchange);
        $updateExchangeStmt->bindParam(':userId', $userId);
        $updateExchangeStmt->execute();

        $dom = new DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);
        
        $exchangeUrl = file_get_contents('https://www.google.com/search?q=' . $setExchange);
        $exchangeUrl = mb_convert_encoding($exchangeUrl, 'HTML-ENTITIES', "UTF-8");
        $dom->loadHTML($exchangeUrl); 
        
        $xpath = new DOMXPath($dom);
        $currency = $xpath->query("//span[contains(@class, 'rQMQod')]")->item(0)->nodeValue;
        $value = $xpath->query("//div[contains(@class, 'AP7Wnd')]")->item(1)->nodeValue;

        if (strpos($currency, '=') && isset($setLineError) && isset($setCityError)) {
            $message = [
                'type' => 'text',
                'text' => "設定が完了しました。"
            ];
        } else {
            $message = [
                'type' => 'text',
                'text' => $setLineError . " " . $setCityError . " " . $setExchangeError . "\nの記述形式に問題があります。もう一度『設定』からやり直して下さい。"
            ];
        }
    }
} else if ($weatherFlag == 1) {
    $setWeatherFlagStmt = $pdo->prepare("UPDATE linebot SET weatherFlag = 0 WHERE userId = :userId");
    $setWeatherFlagStmt->bindParam(':userId', $userId);
    $setWeatherFlagStmt->execute();
    $message = [
        'type' => 'text',
        'text' => weatherInfo($msg_text)
    ];
} else if ($exchangeFlag == 1) {
    $setExhangeFlagStmt = $pdo->prepare("UPDATE linebot SET exchangeFlag = 0 WHERE userId = :userId");
    $setExhangeFlagStmt->bindParam(':userId', $userId);
    $setExhangeFlagStmt->execute();

    $dom = new DOMDocument('1.0', 'UTF-8');
    libxml_use_internal_errors(true);

    $exchangeUrl = file_get_contents('https://www.google.com/search?q=' . $msg_text);
    $exchangeUrl = mb_convert_encoding($exchangeUrl, 'HTML-ENTITIES', "UTF-8");
    $dom->loadHTML($exchangeUrl); 

    $xpath = new DOMXPath($dom);
    $currency = $xpath->query("//span[contains(@class, 'rQMQod')]")->item(0)->nodeValue;
    $value = $xpath->query("//div[contains(@class, 'AP7Wnd')]")->item(1)->nodeValue;

    if (strpos($currency, '=')) {
        $message = [
            'type' => 'text',
            'text' => $currency . ' ' . $value
        ];
    } else {
        $message = [
            'type' => 'text',
            'text' => "エラーが発生しました。\nこのエラーが頻出する際は、通貨ペアの記述方法に問題があります。"
        ];
    }
} else if ($moreInfo == 1 && $msg_text == "鉄道") {
    $setMoreInfoStmt = $pdo->prepare("UPDATE linebot SET moreInfo = 0 WHERE userId = :userId");
    $setMoreInfoStmt->bindParam(':userId', $userId);
    $setMoreInfoStmt->execute();
    $message = ['type' => 'text', 'text' => delayTrainInfo($inputSelectedLineName)];
} else if ($type === 'message' && $msg_obj === 'text') {
    $msg_text = $json_obj['events'][0]['message']['text'];
    $setSelectedLineStmt = $pdo->prepare("UPDATE linebot SET selectedLine = :lines WHERE userId = :userId");
    $setSelectedLineStmt->bindParam(':userId', $userId);
    switch ($msg_text) {
        case "鉄道":
            if (!is_null($inputLineName) && !empty($inputLineName)) {
                $setSelectedLineStmt->bindParam(':lines', $inputLineName);
                $setSelectedLineStmt->execute();
                $message = [
                    'type' => 'text',
                    'text' => delayInfo($userId, $pdo, $inputLineName)
                ];
            } else {
                $message = [
                    'type' => 'text',
                    'text' => "既定路線が設定されていません。\n表示する路線を送信してください。\n京都線\n神戸線または山陽線\n環状線\n東西線\n宝塚線\n福知山線\n桜島線\n阪和線"
                ];
            }
            break;
        case "路線":
        case "路線名":
            $message = [
                'type' => 'text',
                'text' => "選択可能な路線を表示します。\n京都線\n神戸線または山陽線\n環状線\n東西線\n宝塚線\n福知山線\n桜島線\n阪和線"
            ];
            break;
        case "京都線":
            $setSelectedLineStmt->bindParam(':lines', $lines[1]);
            $setSelectedLineStmt->execute();
            $message = ['type' => 'text', 'text' => delayInfo($userId, $pdo, $lines[1])];
            break;
        case "神戸線":
        case "山陽線":
            $setSelectedLineStmt->bindParam(':lines', $lines[2]);
            $setSelectedLineStmt->execute();
            $message = ['type' => 'text', 'text' => delayInfo($userId, $pdo, $lines[2])];
            break;
        case "環状線":
            $setSelectedLineStmt->bindParam(':lines', $lines[17]);
            $setSelectedLineStmt->execute();
            $message = ['type' => 'text', 'text' => delayInfo($userId, $pdo, $lines[17])];
            break;
        case "宝塚線":
            $setSelectedLineStmt->bindParam(':lines', $lines[11]);
            $setSelectedLineStmt->execute();
            $message = ['type' => 'text', 'text' => delayInfo($userId, $pdo, $lines[11])];
            break;
        case "東西線":
            $setSelectedLineStmt->bindParam(':lines', $lines[13]);
            $setSelectedLineStmt->execute();
            $message = ['type' => 'text', 'text' => delayInfo($userId, $pdo, $lines[13])];
            break;
        case "福知山線":
            $setSelectedLineStmt->bindParam(':lines', $lines[12]);
            $setSelectedLineStmt->execute();
            $message = ['type' => 'text', 'text' => delayInfo($userId, $pdo, $lines[12])];
            break;
        case "桜島線":
            $setSelectedLineStmt->bindParam(':lines', $lines[18]);
            $setSelectedLineStmt->execute();
            $message = ['type' => 'text', 'text' => delayInfo($userId, $pdo, $lines[18])];
            break;
        case "阪和線":
            $setSelectedLineStmt->bindParam(':lines', $lines[20]);
            $setSelectedLineStmt->execute();
            $message = ['type' => 'text', 'text' => delayInfo($userId, $pdo, $lines[20])];
            break;
        case "詳細":
            $message = ['type' => 'text', 'text' => delayTrainInfo($inputSelectedLineName)];
            break;
        case "天気":
            if (!is_null($inputCityName) && !empty($inputCityName)) {
                $message = [
                    'type' => 'text',
                    'text' => weatherInfo($inputCityName)
                ];
            } else {
                $setWeatherFlagStmt = $pdo->prepare("UPDATE linebot SET weatherFlag = 1 WHERE userId = :userId");
                $setWeatherFlagStmt->bindParam(':userId', $userId);
                $setWeatherFlagStmt->execute();
                $message = [
                    'type' => 'text',
                    'text' => "既定の都市が設定されていません。\n続けて、表示したい都市名を送信してください。\n例：『神戸』『京都市中京区』『品川区』"
                ];
            }
            break;
        case "為替":
            if (!is_null($inputExchangeName) && !empty($inputExchangeName)) {
                $dom = new DOMDocument('1.0', 'UTF-8');
                libxml_use_internal_errors(true);
                
                $exchangeUrl = file_get_contents('https://www.google.com/search?q=' . $inputExchangeName);
                $exchangeUrl = mb_convert_encoding($exchangeUrl, 'HTML-ENTITIES', "UTF-8");
                $dom->loadHTML($exchangeUrl); 
                
                $xpath = new DOMXPath($dom);
                $currency = $xpath->query("//span[contains(@class, 'rQMQod')]")->item(0)->nodeValue;
                $value = $xpath->query("//div[contains(@class, 'AP7Wnd')]")->item(1)->nodeValue;
                $message = [
                    'type' => 'text',
                    'text' => $currency . ' ' . $value
                ];
            } else {
                $setExhangeFlagStmt = $pdo->prepare("UPDATE linebot SET exchangeFlag = 1 WHERE userId = :userId");
                $setExhangeFlagStmt->bindParam(':userId', $userId);
                $setExhangeFlagStmt->execute();
                $message = [
                    'type' => 'text', 
                    'text' => "既定の通貨ペアが設定されていません。\n続けて、調べたい通貨ペアを送信してください。\n例：ドル円、ユーロドル、BTC円..."
                    ];
            }
            break;
        default:
            $message = [
                'type' => 'text', 
                'text' => "使用できるコマンド一覧を表示します\n・天気\n・鉄道\n・路線名\n・為替\n・設定\n以上のコマンドを送信してください。\nまた、コマンドリストからのご利用も可能です。"
            ];
            break;
    }
}

$data = array(
    'replyToken' => $reply_token,
    'messages' => array($message)
);

$ch = curl_init('https://api.line.me/v2/bot/message/reply');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, array(
    'Content-Type: application/json; charser=UTF-8',
    'Authorization: Bearer ' . $accessToken
));
$result = curl_exec($ch);
if (!$result) {
    error_log(curl_error($ch));
}
curl_close($ch);

echo "{$time} 秒";
return $result;
?>
