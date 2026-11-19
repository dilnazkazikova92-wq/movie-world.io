<?php
session_start();
require_once "includes/db.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

$userId = (int)$_SESSION["user_id"];
$filmId = isset($_POST["film_id"]) ? (int)$_POST["film_id"] : 0;

$return = $_POST["return"] ?? "catalog.php";

if (!in_array($return, ["catalog.php", "favorites.php"])) {
    $return = "catalog.php";
}

if ($filmId > 0) {

    $check = $conn->prepare(
        "SELECT id FROM favorites WHERE user_id = ? AND film_id = ?"
    );

    $check->bind_param("ii", $userId, $filmId);
    $check->execute();

    $exists = $check->get_result()->num_rows > 0;

    if ($exists) {
        $stmt = $conn->prepare(
            "DELETE FROM favorites WHERE user_id = ? AND film_id = ?"
        );
    } else {
        $stmt = $conn->prepare(
            "INSERT INTO favorites (user_id, film_id) VALUES (?, ?)"
        );
    }

    $stmt->bind_param("ii", $userId, $filmId);
    $stmt->execute();
}

header("Location: " . $return);
exit;