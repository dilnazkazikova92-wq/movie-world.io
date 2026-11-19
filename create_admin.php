<?php
include "includes/db.php";

// Данные администратора
$adminLogin = "admin";
$adminPassword = "12345678";

// Создаём хеш пароля
$hashedPassword = password_hash($adminPassword, PASSWORD_DEFAULT);

// Обновляем существующую запись администратора
$stmt = $conn->prepare(
    "UPDATE users
     SET login = ?, password = ?
     WHERE role = 'admin'
     LIMIT 1"
);

$stmt->bind_param("ss", $adminLogin, $hashedPassword);
$stmt->execute();

if ($stmt->affected_rows >= 0 && $stmt->error === "") {
    echo "Аккаунт администратора настроен!<br>";
    echo "Логин: " . htmlspecialchars($adminLogin) . "<br>";
    echo "Пароль: " . htmlspecialchars($adminPassword);
} else {
    echo "Ошибка: " . htmlspecialchars($stmt->error);
}

$stmt->close();
$conn->close();
?>