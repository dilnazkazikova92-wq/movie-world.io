<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Удаляем все данные сессии
$_SESSION = [];

// Удаляем cookie сессии
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();

    setcookie(
        session_name(),
        "",
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// Завершаем сессию
session_destroy();

// Возвращаем пользователя на главную
header("Location: /movie_catalog/index.php");
exit;

?>