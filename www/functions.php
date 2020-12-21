<?php

// Применяет функцию htmlspecialchars для каждого элемента массива и возвращает его.
// Меняет исходный массив!
function makeArrayValuesSafe(&$array){
    foreach(array_keys($array) as $key) {
        $array[$key] = htmlspecialchars($array[$key]);
    }
    return $array;
}
?>