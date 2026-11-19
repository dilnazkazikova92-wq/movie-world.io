<?php
session_start();
include "../includes/db.php";

// Проверка прав администратора
if (
    !isset($_SESSION["user_id"]) ||
    !isset($_SESSION["user_role"]) ||
    $_SESSION["user_role"] !== "admin"
) {
    header("Location: ../login.php");
    exit;
}

$adminLogin = $_SESSION["user_login"] ?? "Администратор";
$message = "";
$error = "";

// CSRF-защита
if (empty($_SESSION["admin_token"])) {
    $_SESSION["admin_token"] = bin2hex(random_bytes(32));
}

$token = $_SESSION["admin_token"];

// Функция безопасного вывода
function e($value) {
    return htmlspecialchars((string)($value ?? ""), ENT_QUOTES, "UTF-8");
}

// Проверка обязательных полей
function validateFilm($title, $genre, $year, $director, $description) {
    return (
        $title !== "" &&
        $genre !== "" &&
        $year >= 1900 &&
        $year <= 2100 &&
        $director !== "" &&
        $description !== ""
    );
}

// Обработка POST-запросов
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    if (!hash_equals($token, $_POST["token"] ?? "")) {
        $error = "Ошибка проверки безопасности. Обновите страницу.";
    } else {

        $action = $_POST["action"] ?? "";

        // =====================================
        // ДОБАВЛЕНИЕ ФИЛЬМА
        // =====================================

        if ($action === "add_film") {

            $title = trim($_POST["title"] ?? "");
            $genre = trim($_POST["genre"] ?? "");
            $year = (int)($_POST["year"] ?? 0);
            $director = trim($_POST["director"] ?? "");
            $description = trim($_POST["description"] ?? "");
            $poster = trim($_POST["poster"] ?? "");

            $actors = trim($_POST["actors"] ?? "");
            $runtime = trim($_POST["runtime"] ?? "");
            $country = trim($_POST["country"] ?? "");
            $rating = trim($_POST["rating"] ?? "");
            $trailer = trim($_POST["trailer_url"] ?? "");

            if (!validateFilm($title, $genre, $year, $director, $description)) {

                $error = "Заполните все обязательные поля. Проверьте год выпуска.";

            } elseif ($rating !== "" && (!is_numeric($rating) || $rating < 0 || $rating > 10)) {

                $error = "Рейтинг должен быть числом от 0 до 10.";

            } else {

                $rating = $rating === "" ? null : $rating;

                $stmt = $conn->prepare(
                    "INSERT INTO films
                    (title, genre, year, director, description, poster,
                     actors, runtime, country, rating, trailer_url)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
                );

                $stmt->bind_param(
                    "ssissssssss",
                    $title,
                    $genre,
                    $year,
                    $director,
                    $description,
                    $poster,
                    $actors,
                    $runtime,
                    $country,
                    $rating,
                    $trailer
                );

                if ($stmt->execute()) {
                    $message = "Фильм успешно добавлен!";
                } else {
                    $error = "Не удалось добавить фильм: " . $stmt->error;
                }

                $stmt->close();
            }
        }

        // =====================================
        // РЕДАКТИРОВАНИЕ ФИЛЬМА
        // =====================================

        if ($action === "update_film") {

            $filmId = (int)($_POST["film_id"] ?? 0);

            $title = trim($_POST["title"] ?? "");
            $genre = trim($_POST["genre"] ?? "");
            $year = (int)($_POST["year"] ?? 0);
            $director = trim($_POST["director"] ?? "");
            $description = trim($_POST["description"] ?? "");
            $poster = trim($_POST["poster"] ?? "");

            $actors = trim($_POST["actors"] ?? "");
            $runtime = trim($_POST["runtime"] ?? "");
            $country = trim($_POST["country"] ?? "");
            $rating = trim($_POST["rating"] ?? "");
            $trailer = trim($_POST["trailer_url"] ?? "");

            if ($filmId <= 0) {

                $error = "Некорректный ID фильма.";

            } elseif (!validateFilm($title, $genre, $year, $director, $description)) {

                $error = "Заполните все обязательные поля. Проверьте год выпуска.";

            } elseif ($rating !== "" && (!is_numeric($rating) || $rating < 0 || $rating > 10)) {

                $error = "Рейтинг должен быть числом от 0 до 10.";

            } else {

                $rating = $rating === "" ? null : $rating;

                $stmt = $conn->prepare(
                    "UPDATE films SET
                        title = ?,
                        genre = ?,
                        year = ?,
                        director = ?,
                        description = ?,
                        poster = ?,
                        actors = ?,
                        runtime = ?,
                        country = ?,
                        rating = ?,
                        trailer_url = ?
                    WHERE id = ?"
                );

                $stmt->bind_param(
                    "ssissssssssi",
                    $title,
                    $genre,
                    $year,
                    $director,
                    $description,
                    $poster,
                    $actors,
                    $runtime,
                    $country,
                    $rating,
                    $trailer,
                    $filmId
                );

                if ($stmt->execute()) {
                    $message = "Информация о фильме успешно обновлена!";
                } else {
                    $error = "Не удалось обновить фильм: " . $stmt->error;
                }

                $stmt->close();
            }
        }

        // =====================================
        // УДАЛЕНИЕ ФИЛЬМА
        // =====================================

        if ($action === "delete_film") {

            $filmId = (int)($_POST["film_id"] ?? 0);

            if ($filmId > 0) {

                $stmt = $conn->prepare(
                    "DELETE FROM favorites WHERE film_id = ?"
                );
                $stmt->bind_param("i", $filmId);
                $stmt->execute();
                $stmt->close();

                $stmt = $conn->prepare(
                    "DELETE FROM notifications WHERE film_id = ?"
                );
                $stmt->bind_param("i", $filmId);
                $stmt->execute();
                $stmt->close();

                $stmt = $conn->prepare(
                    "DELETE FROM films WHERE id = ?"
                );
                $stmt->bind_param("i", $filmId);

                if ($stmt->execute() && $stmt->affected_rows > 0) {
                    $message = "Фильм успешно удалён.";
                } else {
                    $error = "Фильм не найден или не удалось удалить.";
                }

                $stmt->close();

            } else {
                $error = "Некорректный ID фильма.";
            }
        }
    }
}

// =====================================
// ПОЛУЧЕНИЕ ФИЛЬМА ДЛЯ РЕДАКТИРОВАНИЯ
// =====================================

$editFilm = null;

if (isset($_GET["edit"])) {

    $editId = (int)$_GET["edit"];

    if ($editId > 0) {

        $stmt = $conn->prepare(
            "SELECT * FROM films WHERE id = ?"
        );

        $stmt->bind_param("i", $editId);
        $stmt->execute();

        $result = $stmt->get_result();
        $editFilm = $result->fetch_assoc();

        $stmt->close();

        if (!$editFilm) {
            $error = "Фильм для редактирования не найден.";
        }
    }
}

// =====================================
// СТАТИСТИКА
// =====================================

$totalFilms = (int)$conn->query(
    "SELECT COUNT(*) AS total FROM films"
)->fetch_assoc()["total"];

$totalUsers = (int)$conn->query(
    "SELECT COUNT(*) AS total FROM users WHERE role = 'user'"
)->fetch_assoc()["total"];

$totalAdmins = (int)$conn->query(
    "SELECT COUNT(*) AS total FROM users WHERE role = 'admin'"
)->fetch_assoc()["total"];

// =====================================
// ПОИСК И СПИСОК ФИЛЬМОВ
// =====================================

$filmSearch = trim($_GET["film_search"] ?? "");
$filmSearchLike = "%" . $filmSearch . "%";

$stmt = $conn->prepare(
    "SELECT id, title, genre, year, director, rating
     FROM films
     WHERE title LIKE ? OR genre LIKE ? OR director LIKE ?
     ORDER BY id DESC"
);
$stmt->bind_param("sss", $filmSearchLike, $filmSearchLike, $filmSearchLike);
$stmt->execute();
$films = $stmt->get_result();
$stmt->close();

// =====================================
// ПОИСК И СПИСОК ПОЛЬЗОВАТЕЛЕЙ
// =====================================

$userSearch = trim($_GET["user_search"] ?? "");
$userSearchLike = "%" . $userSearch . "%";

$stmt = $conn->prepare(
    "SELECT id, login, email, role
     FROM users
     WHERE login LIKE ? OR email LIKE ?
     ORDER BY id DESC"
);
$stmt->bind_param("ss", $userSearchLike, $userSearchLike);
$stmt->execute();
$users = $stmt->get_result();
$stmt->close();

include "../includes/header.php";
?>

<style>
.admin-page {
    max-width: 1350px;
    margin: 0 auto;
    padding: 40px 20px 70px;
    color: #f5f5f7;
}

.admin-welcome {
    padding: 35px 40px;
    margin-bottom: 28px;
    background: linear-gradient(120deg, #292735, #191920);
    border: 1px solid #393744;
    border-radius: 18px;
}

.admin-label {
    color: #ffcc4d;
    font-size: 14px;
    font-weight: 700;
    letter-spacing: 2px;
}

.admin-welcome h1 {
    margin: 13px 0;
    color: #fff;
    font-size: 34px;
}

.admin-welcome p {
    margin: 0;
    color: #c1c1cc;
    font-size: 17px;
    line-height: 1.7;
}

.admin-stats {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 20px;
    margin-bottom: 35px;
}

.admin-stat {
    padding: 27px;
    background: #1b1b24;
    border: 1px solid #343440;
    border-radius: 14px;
    transition: .3s;
}

.admin-stat:hover {
    transform: translateY(-3px);
    border-color: #ffcc4d;
}

.admin-stat span {
    color: #b8b8c5;
    font-size: 16px;
}

.admin-stat strong {
    display: block;
    margin-top: 12px;
    color: #ffcc4d;
    font-size: 36px;
}

.admin-section {
    margin-top: 30px;
    padding: 30px;
    background: #1b1b24;
    border: 1px solid #343440;
    border-radius: 15px;
}

.admin-section h2 {
    margin: 0 0 25px;
    color: #fff;
    font-size: 27px;
}

.admin-message,
.admin-error {
    padding: 15px 18px;
    margin-bottom: 20px;
    border-radius: 9px;
    font-size: 16px;
}

.admin-message {
    background: rgba(65, 160, 100, .13);
    border: 1px solid #397b50;
    color: #8ee0a7;
}

.admin-error {
    background: rgba(180, 55, 65, .12);
    border: 1px solid #793c43;
    color: #ff929b;
}

.admin-form {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 19px;
}

.admin-form label {
    display: block;
    margin-bottom: 9px;
    color: #e5e5ec;
    font-size: 15px;
    font-weight: 600;
}

.admin-form input,
.admin-form textarea {
    width: 100%;
    box-sizing: border-box;
    padding: 14px 15px;
    border: 1px solid #41414d;
    border-radius: 8px;
    outline: none;
    background: #111116;
    color: #fff;
    font-size: 15px;
    font-family: inherit;
}

.admin-form input:focus,
.admin-form textarea:focus {
    border-color: #ffcc4d;
}

.admin-form textarea {
    min-height: 125px;
    resize: vertical;
    line-height: 1.6;
}

.admin-form .full {
    grid-column: 1 / -1;
}

.admin-button {
    display: inline-block;
    padding: 14px 24px;
    border: none;
    border-radius: 8px;
    background: #ffcc4d;
    color: #191919;
    font-size: 16px;
    font-weight: 700;
    cursor: pointer;
    transition: .3s;
}

.admin-button:hover {
    background: #ffdc7b;
    transform: translateY(-2px);
}

.admin-cancel {
    display: inline-block;
    margin-left: 12px;
    padding: 13px 20px;
    border: 1px solid #555563;
    border-radius: 8px;
    color: #ddd;
    text-decoration: none;
    font-size: 15px;
}

.admin-cancel:hover {
    background: #343440;
}

.admin-hint {
    margin-top: 8px;
    color: #9999a8;
    font-size: 13px;
}

.admin-table-wrap {
    overflow-x: auto;
}

.admin-table {
    width: 100%;
    border-collapse: collapse;
    min-width: 750px;
}

.admin-table th {
    padding: 16px 13px;
    background: #292833;
    color: #ffcc4d;
    text-align: left;
    font-size: 15px;
    white-space: nowrap;
}

.admin-table td {
    padding: 15px 13px;
    border-bottom: 1px solid #353540;
    color: #e0e0e8;
    font-size: 15px;
}

.admin-table tr:hover td {
    background: #23232d;
}

.admin-actions {
    display: flex;
    align-items: center;
    gap: 8px;
}

.admin-edit {
    display: inline-block;
    padding: 9px 13px;
    border: 1px solid #b18c2d;
    border-radius: 7px;
    background: rgba(255, 204, 77, .1);
    color: #ffcc4d;
    text-decoration: none;
    font-size: 14px;
    cursor: pointer;
}

.admin-edit:hover {
    background: #ffcc4d;
    color: #191919;
}

.admin-delete {
    padding: 9px 13px;
    border: 1px solid #9c4149;
    border-radius: 7px;
    background: rgba(180, 55, 65, .12);
    color: #ff929b;
    font-size: 14px;
    cursor: pointer;
}

.admin-delete:hover {
    background: #9c4149;
    color: white;
}

.admin-role {
    color: #ffcc4d !important;
    font-weight: 700;
}

.admin-rating {
    color: #ffcc4d !important;
    font-weight: 700;
}

.admin-logout {
    display: inline-block;
    margin-top: 30px;
    padding: 13px 23px;
    border: 1px solid #9c4149;
    border-radius: 8px;
    color: #ff929b;
    text-decoration: none;
    font-size: 16px;
    transition: .3s;
}

.admin-logout:hover {
    background: #9c4149;
    color: #fff;
}

.admin-section-title {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 15px;
    flex-wrap: wrap;
}

.admin-section-title h2 {
    margin-bottom: 25px;
}

.admin-search-form {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
    margin: 0 0 22px;
}

.admin-search-form input {
    flex: 1 1 260px;
    min-width: 0;
    box-sizing: border-box;
    padding: 12px 15px;
    border: 1px solid #41414d;
    border-radius: 8px;
    outline: none;
    background: #111116;
    color: #fff;
    font-size: 15px;
}

.admin-search-form input:focus {
    border-color: #ffcc4d;
}

.admin-search-button {
    padding: 12px 19px;
    border: none;
    border-radius: 8px;
    background: #ffcc4d;
    color: #191919;
    font-size: 15px;
    font-weight: 700;
    cursor: pointer;
}

.admin-search-reset {
    color: #c5c5d0;
    font-size: 14px;
    text-decoration: none;
}

.admin-search-reset:hover {
    color: #ffcc4d;
}

@media (max-width: 700px) {
    .admin-stats {
        grid-template-columns: 1fr;
    }

    .admin-form {
        grid-template-columns: 1fr;
    }

    .admin-form .full {
        grid-column: auto;
    }

    .admin-welcome {
        padding: 27px 22px;
    }

    .admin-welcome h1 {
        font-size: 26px;
    }

    .admin-section {
        padding: 22px 15px;
    }

    .admin-section h2 {
        font-size: 22px;
    }

    .admin-cancel {
        margin: 10px 0 0;
    }
}
</style>

<section class="admin-page">

    <!-- ПРИВЕТСТВИЕ -->

    <div class="admin-welcome">

        <span class="admin-label">ПАНЕЛЬ УПРАВЛЕНИЯ</span>

        <h1>
            Добро пожаловать, <?= e($adminLogin) ?>!
        </h1>

        <p>
            Здесь можно управлять каталогом фильмов, добавлять новые
            произведения, изменять информацию о них, удалять записи
            и просматривать зарегистрированных пользователей.
        </p>

    </div>

    <!-- СООБЩЕНИЯ -->

    <?php if ($message !== ""): ?>
        <div class="admin-message">
            <?= e($message) ?>
        </div>
    <?php endif; ?>

    <?php if ($error !== ""): ?>
        <div class="admin-error">
            <?= e($error) ?>
        </div>
    <?php endif; ?>

    <!-- СТАТИСТИКА -->

    <div class="admin-stats">

        <div class="admin-stat">
            <span>Всего фильмов</span>
            <strong><?= $totalFilms ?></strong>
        </div>

        <div class="admin-stat">
            <span>Пользователей</span>
            <strong><?= $totalUsers ?></strong>
        </div>

        <div class="admin-stat">
            <span>Администраторов</span>
            <strong><?= $totalAdmins ?></strong>
        </div>

    </div>

    <!-- ДОБАВЛЕНИЕ И РЕДАКТИРОВАНИЕ -->

    <div class="admin-section" id="film-form">

        <h2>
            <?= $editFilm ? "Редактировать фильм" : "Добавить новый фильм" ?>
        </h2>

        <form method="POST" class="admin-form">

            <input type="hidden" name="token" value="<?= e($token) ?>">

            <input
                type="hidden"
                name="action"
                value="<?= $editFilm ? "update_film" : "add_film" ?>"
            >

            <?php if ($editFilm): ?>
                <input
                    type="hidden"
                    name="film_id"
                    value="<?= (int)$editFilm["id"] ?>"
                >
            <?php endif; ?>

            <div>
                <label>Название фильма *</label>
                <input
                    type="text"
                    name="title"
                    value="<?= e($editFilm["title"] ?? "") ?>"
                    placeholder="Например: Интерстеллар"
                    required
                >
            </div>

            <div>
                <label>Жанр *</label>
                <input
                    type="text"
                    name="genre"
                    value="<?= e($editFilm["genre"] ?? "") ?>"
                    placeholder="Например: Фантастика"
                    required
                >
            </div>

            <div>
                <label>Год выпуска *</label>
                <input
                    type="number"
                    name="year"
                    min="1900"
                    max="2100"
                    value="<?= e($editFilm["year"] ?? "") ?>"
                    placeholder="Например: 2014"
                    required
                >
            </div>

            <div>
                <label>Режиссёр *</label>
                <input
                    type="text"
                    name="director"
                    value="<?= e($editFilm["director"] ?? "") ?>"
                    placeholder="Имя режиссёра"
                    required
                >
            </div>

            <div>
                <label>Актёры</label>
                <input
                    type="text"
                    name="actors"
                    value="<?= e($editFilm["actors"] ?? "") ?>"
                    placeholder="Например: Мэттью Макконахи, Энн Хэтэуэй"
                >
            </div>

            <div>
                <label>Продолжительность</label>
                <input
                    type="text"
                    name="runtime"
                    value="<?= e($editFilm["runtime"] ?? "") ?>"
                    placeholder="Например: 169 минут"
                >
            </div>

            <div>
                <label>Страна</label>
                <input
                    type="text"
                    name="country"
                    value="<?= e($editFilm["country"] ?? "") ?>"
                    placeholder="Например: США, Великобритания"
                >
            </div>

            <div>
                <label>Рейтинг (от 0 до 10)</label>
                <input
                    type="number"
                    name="rating"
                    min="0"
                    max="10"
                    step="0.1"
                    value="<?= e($editFilm["rating"] ?? "") ?>"
                    placeholder="Например: 8.7"
                >
            </div>

            <div class="full">
                <label>Описание фильма *</label>
                <textarea
                    name="description"
                    placeholder="Подробное описание фильма..."
                    required
                ><?= e($editFilm["description"] ?? "") ?></textarea>
            </div>

            <div class="full">
                <label>Путь к постеру</label>
                <input
                    type="text"
                    name="poster"
                    value="<?= e($editFilm["poster"] ?? "") ?>"
                    placeholder="Например: images/interstellar.jpg"
                >
                <div class="admin-hint">
                    Укажите путь к изображению относительно корневой папки сайта.
                </div>
            </div>

            <div class="full">
                <label>Ссылка на трейлер YouTube</label>
                <input
                    type="text"
                    name="trailer_url"
                    value="<?= e($editFilm["trailer_url"] ?? "") ?>"
                    placeholder="Например: https://www.youtube.com/watch?v=..."
                >
            </div>

            <div class="full">

                <button type="submit" class="admin-button">
                    <?= $editFilm ? "Сохранить изменения" : "+ Добавить фильм" ?>
                </button>

                <?php if ($editFilm): ?>
                    <a href="index.php" class="admin-cancel">
                        Отмена
                    </a>
                <?php endif; ?>

            </div>

        </form>

    </div>

    <!-- УПРАВЛЕНИЕ ФИЛЬМАМИ -->

    <div class="admin-section">

        <div class="admin-section-title">
            <h2>Управление фильмами</h2>
        </div>

        <form method="GET" class="admin-search-form">
            <input
                type="text"
                name="film_search"
                value="<?= e($filmSearch) ?>"
                placeholder="Поиск по названию, жанру или режиссёру..."
            >
            <?php if ($userSearch !== ""): ?>
                <input type="hidden" name="user_search" value="<?= e($userSearch) ?>">
            <?php endif; ?>
            <button type="submit" class="admin-search-button">Найти фильм</button>
            <a
                class="admin-search-reset"
                href="index.php<?= $userSearch !== "" ? "?user_search=" . urlencode($userSearch) : "" ?>"
            >Сбросить</a>
        </form>

        <div class="admin-table-wrap">

            <table class="admin-table">

                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Название</th>
                        <th>Жанр</th>
                        <th>Год</th>
                        <th>Режиссёр</th>
                        <th>Рейтинг</th>
                        <th>Действия</th>
                    </tr>
                </thead>

                <tbody>

                <?php if ($films && $films->num_rows > 0): ?>

                    <?php while ($film = $films->fetch_assoc()): ?>

                        <tr>

                            <td><?= (int)$film["id"] ?></td>

                            <td><?= e($film["title"]) ?></td>

                            <td><?= e($film["genre"]) ?></td>

                            <td><?= (int)$film["year"] ?></td>

                            <td><?= e($film["director"]) ?></td>

                            <td class="admin-rating">
                                <?= $film["rating"] !== null && $film["rating"] !== ""
                                    ? e($film["rating"]) . " / 10"
                                    : "—" ?>
                            </td>

                            <td>

                                <div class="admin-actions">

                                    <a
                                        href="index.php?edit=<?= (int)$film["id"] ?>&film_search=<?= urlencode($filmSearch) ?>&user_search=<?= urlencode($userSearch) ?>#film-form"
                                        class="admin-edit"
                                    >
                                        Изменить
                                    </a>

                                    <form
                                        method="POST"
                                        onsubmit="return confirm('Вы действительно хотите удалить этот фильм?');"
                                    >

                                        <input
                                            type="hidden"
                                            name="token"
                                            value="<?= e($token) ?>"
                                        >

                                        <input
                                            type="hidden"
                                            name="action"
                                            value="delete_film"
                                        >

                                        <input
                                            type="hidden"
                                            name="film_id"
                                            value="<?= (int)$film["id"] ?>"
                                        >

                                        <button
                                            type="submit"
                                            class="admin-delete"
                                        >
                                            Удалить
                                        </button>

                                    </form>

                                </div>

                            </td>

                        </tr>

                    <?php endwhile; ?>

                <?php else: ?>

                    <tr>
                        <td colspan="7">Фильмов пока нет.</td>
                    </tr>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

    </div>

    <!-- ПОЛЬЗОВАТЕЛИ -->

    <div class="admin-section">

        <h2>Зарегистрированные пользователи</h2>

        <form method="GET" class="admin-search-form">
            <input
                type="text"
                name="user_search"
                value="<?= e($userSearch) ?>"
                placeholder="Поиск по логину или электронной почте..."
            >
            <?php if ($filmSearch !== ""): ?>
                <input type="hidden" name="film_search" value="<?= e($filmSearch) ?>">
            <?php endif; ?>
            <button type="submit" class="admin-search-button">Найти пользователя</button>
            <a
                class="admin-search-reset"
                href="index.php<?= $filmSearch !== "" ? "?film_search=" . urlencode($filmSearch) : "" ?>"
            >Сбросить</a>
        </form>

        <div class="admin-table-wrap">

            <table class="admin-table">

                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Логин</th>
                        <th>Email</th>
                        <th>Роль</th>
                    </tr>
                </thead>

                <tbody>

                <?php if ($users && $users->num_rows > 0): ?>

                    <?php while ($user = $users->fetch_assoc()): ?>

                        <tr>

                            <td><?= (int)$user["id"] ?></td>

                            <td><?= e($user["login"]) ?></td>

                            <td><?= e($user["email"]) ?></td>

                            <td class="admin-role">

                                <?= $user["role"] === "admin"
                                    ? "Администратор"
                                    : "Пользователь" ?>

                            </td>

                        </tr>

                    <?php endwhile; ?>

                <?php else: ?>

                    <tr>
                        <td colspan="4">Пользователей пока нет.</td>
                    </tr>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

    </div>

    <a href="../logout.php" class="admin-logout">
        Выйти из аккаунта
    </a>

</section>

<?php include "../includes/footer.php"; ?>