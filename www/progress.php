<?php

$message = array();

session_start();
if (isset($_SESSION["running"])){
    $message["running"] = $_SESSION["running"];
    $message["rowsReady"] = $_SESSION["rowsReady"];
    $message["rowsToRead"] = $_SESSION["rowsToRead"];
}
else{
    $message["running"] = false;
}


// Разрешаем запросы с других страниц сайта?
header("access-control-allow-origin: *");

echo json_encode($message);
?>