<?php
include "includes/db.php";

// Укажи здесь новый пароль администратора
$newPassword = "Admin2026!";

// Создаём безопасный хеш пароля
$hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

// Меняем пароль только у администратора dilnaz
$stmt = $conn->prepare(
    "UPDATE users SET password = ? WHERE login = ? AND role = 'admin'"
);

$login = "dilnaz";

$stmt->bind_param("ss", $hashedPassword, $login);
$stmt->execute();

if ($stmt->affected_rows > 0) {
    echo "Пароль администратора успешно изменён!";
} else {
    echo "Пароль не изменён. Проверь логин и роль администратора.";
}

$stmt->close();
$conn->close();
?>