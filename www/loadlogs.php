<?php

// Настройки:
$defaultRowsPerQuery = 1;
$maxRowsPerQuery = 100000;
$logfileName = 'logs';
$logfileExtention = 'log';  // Расширение файла с логами

$message = array();

$logRegex = '/((\d{1,3}\.){3}(\d{1,3})) - (-|"") \[((\d+)\/(\w+)\/(\d+):(\d+):(\d+):(\d+) .*?)\] "(.*?) ((.*?)(\?.*?)?) (.*?)" (\d+) (\d+|-) "((.*?)(\?.*?)?)" "(.*?)"/';

if (isset($_REQUEST['load'])){
    $message["load"] = "true";

    // Начало подготовки БД
    // Подклчение к БД:
    $db = null;
    try{
        $db = new PDO('mysql:host=localhost;dbname=lab5DB', 'root', '');
    }
    catch (PDOException $e){
        print "Error!:" . $e->getMessage() . "<br/>";
        die();
    }

    // Подготовленный запрос:
    $addLog = $db->prepare("INSERT INTO logs(clientIp, date, requestType, requestURL, requestVersion, answerCode, answerLength, refererURL, userAgent) VALUES (:clientIp, :date, :requestType, :requestURL, :requestVersion, :answerCode, :answerLength, :refererURL, :userAgent);");
    $addLog->bindParam(':clientIp'          , $clientIp);
    $addLog->bindParam(':date'              , $date);
    $addLog->bindParam(':requestType'       , $requestType);
    $addLog->bindParam(':requestURL'        , $requestURL);
    $addLog->bindParam(':requestVersion'    , $requestVersion);
    $addLog->bindParam(':answerCode'        , $answerCode);
    $addLog->bindParam(':answerLength'      , $answerLength);
    $addLog->bindParam(':refererURL'        , $refererURL);
    $addLog->bindParam(':userAgent'         , $userAgent);
    // Конец подготовки БД

    $logfileFilename = $logfileName . "." . $logfileExtention;
    $tempLogfileFilename = $logfileName . "." . $logfileExtention . "temp";

    $logfile = fopen($logfileFilename, 'r+');
    $tempLogfile = fopen($tempLogfileFilename, 'w');

    // Количество строк для чтения:
    // Если не указано - defaultRowsPerQuery.
    // Если указано - не более maxRowsPerQuery.
    //$_REQUEST['rows'] = htmlspecialchars($_REQUEST['rows']);
    $rowsToRead = isset($_REQUEST['rows']) ? ($_REQUEST['rows'] <= $maxRowsPerQuery ? $_REQUEST['rows'] : $maxRowsPerQuery ) : $defaultRowsPerQuery;
    session_start();
    $_SESSION['running'] = true;
    $_SESSION['rowsToRead'] = $rowsToRead;
    session_commit();
    $currentRow = 0;

    set_time_limit(300);  // 5 минут на 1 запрос.

    // Эксклюзивная блокировка: только один процесс может работать с этим файлом.
    if (flock($logfile, LOCK_EX)){

        $allQueries = "";
        $db->setAttribute(PDO::ATTR_EMULATE_PREPARES, 0);
        // Обрабатываем нужные строки...
        while (!feof($logfile) && $currentRow < $rowsToRead){
            // Текущая строка:
            $currow = fgets($logfile, 4096);

            // Запись строк в БД:
            if(preg_match($logRegex, $currow, $matches)){
                // Обработка даты:
                $dateobj = DateTime::createFromFormat('d/M/Y:H:i:s e', $matches[5]);
                $date = $dateobj->format('Y-m-d H:i:s');

                $queryStr = "INSERT INTO logs(clientIp, date, requestType, requestURL, requestVersion, answerCode, answerLength, refererURL, userAgent)
                VALUES (
                '". $matches[1] ."',
                '". $date ."',
                '". $matches[12] ."',
                '". $matches[14] ."',
                '". $matches[16] ."',
                '". $matches[17] ."',
                '". $matches[18] ."',
                '". ($matches[20] !== "-" ? $matches[20] : NULL) ."',
                '". $matches[22] ."'
                );";
                $allQueries .= $queryStr;
                
                // $clientIp =         $matches[1];
                // $requestType =      $matches[12];
                // $requestURL =       $matches[14];
                // $requestVersion =   $matches[16];
                // $answerCode =       $matches[17];
                // $answerLength =     $matches[18];
                // $refererURL =       $matches[20] !== "-" ? $matches[20] : NULL;
                // $userAgent =        $matches[22];

                

                // $addLog->execute();
            }
            
            $currentRow++;
            if ($currentRow % 100 == 0 || $currentRow == $rowsToRead){
                // $temp = $db->prepare($allQueries);
                // $temp->execute();
                $db->exec($allQueries);
                $allQueries = "";
                // Сохраняем в сессию количество уже загруженных строк каждые 100 строк:
                session_start();
                $_SESSION['rowsReady'] = $currentRow;
                session_commit();
            }
        }
        $db->setAttribute(PDO::ATTR_EMULATE_PREPARES, 1);
        // Записываем все остальные в TEMP:
        while (!feof($logfile)){
            $buffer = fgets($logfile, 4096);
            fwrite($tempLogfile, $buffer);
        }
        
        // Закрываем файлы:
        fclose($logfile);
        fclose($tempLogfile);        

        // Переименовываем файл, перезаписывая его
        rename($tempLogfileFilename, $logfileFilename);
        $logfile = fopen($logfileFilename, 'r+');

        // Снимаем блокировку с файлов:
        flock($logfile, LOCK_UN);
        $message["rowsLoaded"] = $rowsToRead;
    }
}
else{
    $message["load"] = "false";
}

session_start();
$_SESSION['running'] = false;
session_commit();

// Разрешаем запросы с других страниц сайта?
header("access-control-allow-origin: *");

echo json_encode($message);
?>