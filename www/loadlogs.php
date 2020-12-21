<?php

// Настройки:
$defaultRowsPerQuery = 1;
$maxRowsPerQuery = 100000;
$logfileName = 'logs';
$logfileExtention = 'log';  // Расширение файла с логами

$message = array();

// Regex для работы со строками из файла
$logRegex = '/((\d{1,3}\.){3}(\d{1,3})) - (-|"") \[((\d+)\/(\w+)\/(\d+):(\d+):(\d+):(\d+) .*?)\] "(.*?) ((.*?)(\?.*?)?) (.*?)" (\d+) (\d+|-) "((.*?)(\?.*?)?)" "(.*?)"/';

// Если дана команда на загрузку:
// Используется $_REQUEST для дебага, чтобы можно было работать и через браузер и через формы
if (isset($_REQUEST['load'])){
    $message["load"] = true;

    // Начало подготовки БД
    // Подклчение к БД:
    $db = null;
    try{
        $db = new PDO('mysql:host=localhost;dbname=lab5DB', 'root', '');
    }
    catch (PDOException $e){
        print "Error!:" . $e->getMessage() . "<br/>";
        die();
    }// Конец подготовки БД

    $logfileFilename = $logfileName . "." . $logfileExtention;
    $tempLogfileFilename = $logfileName . "." . $logfileExtention . "temp";

    $logfile = fopen($logfileFilename, 'r+');
    $tempLogfile = fopen($tempLogfileFilename, 'w');

    // Количество строк для чтения:
    // Если не указано - defaultRowsPerQuery.
    // Если указано - не более maxRowsPerQuery.
    // TODO: добавить htmlspecialchars!
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

        // Строка для хранения всех запросов
        $allQueries = "";
        // Устанавливаем атрибут в состяние для работы без prepare():
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
            }
            
            $currentRow++;
            // Производим загрузку в БД и сохраняем в сессию количество уже загруженных строк каждые 100 строк (или на последней строке):
            if ($currentRow % 100 == 0 || $currentRow == $rowsToRead){
                $db->exec($allQueries);
                $allQueries = "";
                session_start();
                $_SESSION['rowsReady'] = $currentRow;
                session_commit();
            }
        }
        // Устанавливаем атрибут в состяние по умолчанию:
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
    $message["load"] = false;
}

// После окончания закрузки выставляем переменную сессии:
session_start();
$_SESSION['running'] = false;
session_commit();

// Разрешаем запросы с других страниц сайта?
header("access-control-allow-origin: *");

echo json_encode($message);
?>