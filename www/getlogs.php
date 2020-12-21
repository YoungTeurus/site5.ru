<?php
include_once("functions.php");

$defaultOrderBy = "id";  // Стандратно сортируем по id
$descendGroupOrder = true;  // Стандартно группируем по убыванию
$sortGroupByColumn = "count";  // Стандартно сортируем группировку по количеству
$sortGroupByColumnVariants = array("count", "value");
$descendSortOrder = false;  // Стандартно сортируем по возрастанию
$minCount = 0;
$maxCount = 100;
$minOffset = 0;

$message = array();

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
// Возвращает таблицу, содержащую информцию о таблице, в том числе, названия колонок
$getColumnsNames = $db->prepare("SHOW COLUMNS FROM logsextended WHERE Field != 'id';");
// Конец подготовки БД

$count = 5;
$offset = 0;

// Опции выборки из POST запроса:
if (isset($_POST["getColumnsNames"])){
    $columnsNames = array();
    // Запрос названий столбцов
    if ($getColumnsNames->execute()){
        while ($row = $getColumnsNames->fetch(PDO::FETCH_ASSOC)){
            $columnsNames[] = $row['Field'];
        }
    }
    $message["columnsNames"] = $columnsNames;
} else {
    // Обычное выполнение:
    if (isset($_POST["form"])){
        makeArrayValuesSafe($_POST["form"]);
        $count = (int)$_POST["form"]["logsCount"];
        $offset = (int)$_POST["form"]["logsOffset"];
        
        $groupBy = $_POST["form"]["groupByColumn"];

        $orderBy = $_POST["form"]["sortByColumn"];
        $descendSortOrder = $_POST["form"]["descendSortOrder"] === "true" ? true : false;
        $descendGroupOrder = $_POST["form"]["descendGroupOrder"] === "true" ? true : false;
        $sortGroupByColumn = in_array($_POST["form"]["sortGroupByColumn"], $sortGroupByColumnVariants) ? $_POST["form"]["sortGroupByColumn"] : $sortGroupByColumn;
    }

    // Проверка на разрешённые значения:
    $count = $count < $minCount         ?       $minCount   :   $count;
    $count = $count > $maxCount         ?       $maxCount   :   $count;
    $groupBy = $groupBy === ""          ?       null        :   $groupBy;
    $offset = $offset < $minOffset      ?       $minOffset  :   $offset;
    $orderBy = ($orderBy === null || $orderBy === "") ? $defaultOrderBy : $orderBy;

    $columnsNames = null;
    $rows = array();
    
    // Подготовленная функция:
    // ORDER BY и GROUP BY вшивается вручную, так как его нельзя передать bindParam-у.
    if ($groupBy !== null){
        $getLogs = $db->prepare(
            "SELECT cnt as 'Количество записей', " . $groupBy ."
            FROM (SELECT " . $groupBy .", COUNT(*) as cnt FROM logsextended GROUP BY " . $groupBy .") as t
            ORDER BY ". ($sortGroupByColumn === "count" ? "cnt" : $groupBy) ." " . (!$descendGroupOrder ? "ASC":"DESC") ." LIMIT :count OFFSET :offset;");
        $getLogs->bindParam(':count', $_count);
        $getLogs->bindParam(':offset', $_offset);
    
        $_count = $count;
        $_offset = $offset;
    } else {
        $getLogs = $db->prepare("SELECT clientIp, date, requestType, requestURL, requestVersion, answerCode, answerLength, refererURL, userAgent, day, month, dayOfWeek, hour FROM logsextended ORDER BY ". $orderBy . " " . (!$descendSortOrder ? "ASC":"DESC") . " LIMIT :count OFFSET :offset;");
        $getLogs->bindParam(':count', $_count);
        $getLogs->bindParam(':offset', $_offset);
    
        $_count = $count;
        $_offset = $offset;
    }

    if ($getLogs->execute()){
        while ($row = $getLogs->fetch(PDO::FETCH_ASSOC)){
            if (is_null($columnsNames)){
                $columnsNames = array_keys($row);
            }
            $rows[] = $row;
        }
        $message["columnsNames"] = $columnsNames;
        $message["rows"] = $rows;
    } else {
        $message["error"] = $db->errorInfo();
    }
}

echo json_encode($message);
?>