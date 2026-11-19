<?php

$host = "localhost";
$user = "root";
$password = "";
$database = "movie_catalog";

$conn = new mysqli(
    $host,
    $user,
    $password,
    $database
);

if ($conn->connect_error) {
    die("Ошибка подключения к базе данных");
}

$conn->set_charset("utf8mb4");

?>