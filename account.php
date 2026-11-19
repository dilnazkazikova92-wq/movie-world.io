
<?php
session_start();
include "includes/db.php";

/* ДОСТУП ТОЛЬКО ДЛЯ ОБЫЧНЫХ ПОЛЬЗОВАТЕЛЕЙ */
if (
    !isset($_SESSION["user_id"]) ||
    ($_SESSION["user_role"] ?? "") !== "user"
) {
    header("Location: login.php");
    exit;
}

$userId = (int)$_SESSION["user_id"];

/* ТОКЕН ЗАЩИТЫ ФОРМ */
if (empty($_SESSION["account_csrf"])) {
    $_SESSION["account_csrf"] = bin2hex(random_bytes(32));
}
$accountCsrf = $_SESSION["account_csrf"];

/* УДАЛЕНИЕ ФИЛЬМА ИЗ ЛИЧНОГО СПИСКА БЕЗ ПЕРЕЗАГРУЗКИ */
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["account_action"] ?? "") === "remove_movie") {
    header("Content-Type: application/json; charset=UTF-8");

    $sentToken = (string)($_POST["csrf_token"] ?? "");
    if (!hash_equals($accountCsrf, $sentToken)) {
        http_response_code(403);
        echo json_encode(["success" => false, "message" => "Проверка безопасности не пройдена."], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $filmId = (int)($_POST["film_id"] ?? 0);
    $listType = (string)($_POST["list_type"] ?? "");

    if ($filmId < 1 || !in_array($listType, ["favorites", "watchlist"], true)) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Некорректный запрос."], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $table = $listType === "favorites" ? "favorites" : "watchlist";
    $deleteStmt = $conn->prepare("DELETE FROM {$table} WHERE user_id = ? AND film_id = ?");
    if (!$deleteStmt) {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Не удалось выполнить удаление."], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $deleteStmt->bind_param("ii", $userId, $filmId);
    $deleteStmt->execute();
    $removed = $deleteStmt->affected_rows > 0;
    $deleteStmt->close();

    echo json_encode([
        "success" => $removed,
        "message" => $removed ? "Фильм удалён из списка." : "Фильм уже отсутствует в этом списке."
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$profileUpdated = isset($_GET["profile_updated"]) && $_GET["profile_updated"] === "1";

/* БЕЗОПАСНЫЙ ВЫВОД */
function accountEscape($value): string
{
    return htmlspecialchars((string)($value ?? ""), ENT_QUOTES, "UTF-8");
}

/* ПОЛЬЗОВАТЕЛЬ */
$stmt = $conn->prepare(
    "SELECT login, email, notify_new_films
     FROM users
     WHERE id = ?"
);
$stmt->bind_param("i", $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    session_destroy();
    header("Location: login.php");
    exit;
}

/* УВЕДОМЛЕНИЯ */
$stmt = $conn->prepare(
    "SELECT * FROM notifications
     WHERE user_id = ?
     ORDER BY created_at DESC"
);
$stmt->bind_param("i", $userId);
$stmt->execute();
$notifications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$notificationCount = count($notifications);
$stmt->close();

/* ИЗБРАННОЕ */
$stmt = $conn->prepare(
    "SELECT films.*
     FROM favorites
     INNER JOIN films ON favorites.film_id = films.id
     WHERE favorites.user_id = ?
     ORDER BY favorites.id DESC"
);
$stmt->bind_param("i", $userId);
$stmt->execute();
$favorites = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$favoriteCount = count($favorites);
$stmt->close();

/* БУДУ СМОТРЕТЬ */
$stmt = $conn->prepare(
    "SELECT films.*, watchlist.added_at
     FROM watchlist
     INNER JOIN films ON watchlist.film_id = films.id
     WHERE watchlist.user_id = ?
     ORDER BY watchlist.added_at DESC"
);
if (!$stmt) {
    die("Не удалось получить список «Буду смотреть». Проверьте таблицу watchlist.");
}
$stmt->bind_param("i", $userId);
$stmt->execute();
$watchlist = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$watchlistCount = count($watchlist);
$stmt->close();

/* ПРОСМОТРЕННЫЕ */
$stmt = $conn->prepare(
    "SELECT films.*, watched_films.watched_at
     FROM watched_films
     INNER JOIN films ON watched_films.film_id = films.id
     WHERE watched_films.user_id = ?
     ORDER BY watched_films.watched_at DESC"
);
$stmt->bind_param("i", $userId);
$stmt->execute();
$watchedFilms = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$watchedCount = count($watchedFilms);
$stmt->close();

/* РЕКОМЕНДАЦИИ */
$stmt = $conn->prepare(
    "SELECT films.*
     FROM films
     WHERE films.id NOT IN (
         SELECT film_id FROM favorites WHERE user_id = ?
     )
     AND films.id NOT IN (
         SELECT film_id FROM watched_films WHERE user_id = ?
     )
     AND films.id NOT IN (
         SELECT film_id FROM watchlist WHERE user_id = ?
     )
     AND films.genre IN (
         SELECT DISTINCT f.genre
         FROM favorites fav
         INNER JOIN films f ON fav.film_id = f.id
         WHERE fav.user_id = ?
     )
     ORDER BY films.year DESC, films.title ASC
     LIMIT 8"
);
$stmt->bind_param("iiii", $userId, $userId, $userId, $userId);
$stmt->execute();
$recommendations = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$recommendationCount = count($recommendations);
$stmt->close();

/* ОТОБРАЖЕНИЕ КАРТОЧЕК ФИЛЬМОВ */
function accountRenderMovies($movies, $listType = "")
{
    if (empty($movies)) {
        return;
    }

    foreach ($movies as $film) {
        $posterPath = trim($film["poster"] ?? "");

        if (
            $posterPath !== "" &&
            !preg_match('~^(https?://|/)~i', $posterPath) &&
            !file_exists(__DIR__ . "/" . $posterPath)
        ) {
            $posterPath = "images/" . $posterPath;
        }

        $posterExists = $posterPath !== "" && (
            preg_match('~^https?://~i', $posterPath) ||
            file_exists(__DIR__ . "/" . ltrim($posterPath, "/"))
        );
        ?>
        <article class="account-movie-card" data-film-card="<?php echo (int)$film["id"]; ?>">
            <a href="film.php?id=<?php echo (int)$film["id"]; ?>" class="account-movie">
                <div class="account-movie-poster">
                    <?php if ($posterExists): ?>
                        <img
                            src="<?php echo accountEscape($posterPath); ?>"
                            alt="<?php echo accountEscape($film["title"]); ?>"
                            loading="lazy"
                        >
                    <?php else: ?>
                        <div class="account-poster-placeholder">▶</div>
                    <?php endif; ?>

                    <span class="account-movie-year">
                        <?php echo (int)$film["year"]; ?>
                    </span>
                </div>

                <div class="account-movie-info">
                    <h3><?php echo accountEscape($film["title"]); ?></h3>
                    <p><?php echo accountEscape($film["genre"]); ?></p>
                </div>
            </a>

            <?php if (in_array($listType, ["favorites", "watchlist"], true)): ?>
                <button
                    type="button"
                    class="account-remove-button"
                    data-remove-movie
                    data-film-id="<?php echo (int)$film["id"]; ?>"
                    data-list-type="<?php echo accountEscape($listType); ?>"
                >
                    <span aria-hidden="true">×</span>
                    <?php echo $listType === "favorites" ? "Убрать из избранного" : "Удалить из списка"; ?>
                </button>
            <?php endif; ?>
        </article>
        <?php
    }
}

include "includes/header.php";
?>

<style>
/* =========================================
   ЛИЧНЫЙ КАБИНЕТ
========================================= */

.account-page {
    width: 100%;
    max-width: 1380px;
    margin: 0 auto;
    padding: 45px 30px 75px;
    box-sizing: border-box;
    color: #f5f5f7;
}

/* ПРОФИЛЬ */

.account-profile-card {
    display: flex;
    align-items: center;
    gap: 27px;
    padding: 30px 34px;
    margin-bottom: 27px;
    border: 1px solid #383743;
    border-radius: 19px;
    background:
        radial-gradient(circle at 5% 50%, rgba(255,204,77,.07), transparent 35%),
        linear-gradient(120deg, #20202a, #191920);
}

.account-avatar {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 112px;
    height: 112px;
    flex: 0 0 112px;
    border: 1px solid rgba(255,204,77,.3);
    border-radius: 50%;
    background: linear-gradient(145deg, #3b342b, #29252a);
    color: #ffcc4d;
    font-size: 46px;
    font-weight: 800;
    box-shadow: 0 0 25px rgba(255,204,77,.07);
}

.account-profile-info {
    min-width: 0;
    flex: 1;
}

.account-profile-info h1 {
    margin: 0 0 8px;
    color: #fff;
    font-size: 30px;
    line-height: 1.3;
    overflow-wrap: anywhere;
}

.account-profile-info p {
    margin: 0;
    color: #b9b9c5;
    font-size: 16px;
    overflow-wrap: anywhere;
}

.account-profile-label {
    display: inline-block;
    margin-bottom: 8px;
    color: #ffcc4d;
    font-size: 12px;
    font-weight: 800;
    letter-spacing: 1.6px;
}

.account-edit-button {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 51px;
    height: 51px;
    flex: 0 0 51px;
    border: 1px solid #44414c;
    border-radius: 50%;
    background: #292832;
    color: #ffcc4d;
    font-size: 21px;
    text-decoration: none;
    transition: .2s ease;
}

.account-edit-button:hover {
    border-color: #ffcc4d;
    background: rgba(255,204,77,.13);
    transform: translateY(-2px);
}

.profile-message {
    margin-bottom: 20px;
    padding: 14px 18px;
    border: 1px solid rgba(65,170,100,.35);
    border-radius: 11px;
    background: rgba(65,170,100,.12);
    color: #8de0a7;
    font-size: 16px;
}

/* СТАТИСТИКА */

.account-statistics {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 17px;
    margin-bottom: 28px;
}

.account-stat {
    padding: 21px 23px;
    border: 1px solid #383743;
    border-radius: 14px;
    background: linear-gradient(145deg, #22212c, #1a1a22);
    transition: .2s ease;
}

.account-stat:hover {
    border-color: rgba(255,204,77,.4);
    transform: translateY(-2px);
}

.account-stat span {
    display: block;
    color: #c0c0cc;
    font-size: 16px;
    line-height: 1.5;
}

.account-stat strong {
    display: block;
    margin-top: 8px;
    color: #ffcc4d;
    font-size: 33px;
    line-height: 1;
}

/* ВКЛАДКИ */

.account-tabs-wrap {
    position: sticky;
    top: 12px;
    z-index: 20;
    padding: 9px;
    margin-bottom: 23px;
    border: 1px solid #383743;
    border-radius: 15px;
    background: rgba(25,25,32,.96);
    box-shadow: 0 10px 30px rgba(0,0,0,.18);
    backdrop-filter: blur(12px);
}

.account-tabs {
    display: flex;
    align-items: center;
    gap: 8px;
    overflow-x: auto;
    scrollbar-width: thin;
    scrollbar-color: #55515e transparent;
}

.account-tab {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    flex: 0 0 auto;
    min-height: 46px;
    padding: 11px 17px;
    border: 1px solid transparent;
    border-radius: 10px;
    background: transparent;
    color: #c4c3cc;
    font-family: inherit;
    font-size: 15px;
    font-weight: 700;
    white-space: nowrap;
    cursor: pointer;
    transition: .2s ease;
}

.account-tab:hover {
    background: #2a2933;
    color: #fff;
}

.account-tab.active {
    border-color: rgba(255,204,77,.35);
    background: rgba(255,204,77,.13);
    color: #ffcc4d;
}

.account-tab-count {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 23px;
    height: 23px;
    padding: 0 6px;
    border-radius: 20px;
    background: rgba(255,255,255,.09);
    color: inherit;
    font-size: 12px;
}

/* ПАНЕЛИ */

.account-panel {
    padding: 30px;
    border: 1px solid #383743;
    border-radius: 18px;
    background: #191920;
    animation: accountPanelIn .22s ease;
}

.account-panel[hidden] {
    display: none !important;
}

@keyframes accountPanelIn {
    from {
        opacity: 0;
        transform: translateY(5px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.account-section-heading {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 13px;
    margin-bottom: 25px;
}

.account-section-heading h2 {
    margin: 0;
    color: #fff;
    font-size: 28px;
    line-height: 1.3;
}

.account-section-heading p {
    margin: 8px 0 0;
    color: #b9b9c5;
    font-size: 16px;
    line-height: 1.6;
}

.account-section-count {
    display: inline-flex;
    align-items: center;
    padding: 8px 14px;
    border: 1px solid rgba(255,204,77,.25);
    border-radius: 20px;
    background: rgba(255,204,77,.1);
    color: #ffcc4d;
    font-size: 14px;
    font-weight: 800;
    white-space: nowrap;
}

/* ОБЗОР */

.account-overview-note {
    padding: 25px 27px;
    border: 1px solid #383743;
    border-radius: 14px;
    background: #20202a;
}

.account-overview-note h3 {
    margin: 0 0 11px;
    color: #fff;
    font-size: 21px;
}

.account-overview-note p {
    margin: 0;
    color: #c0c0cc;
    font-size: 16px;
    line-height: 1.8;
}

.account-quick-links {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 15px;
    margin-top: 19px;
}

.account-quick-link {
    display: flex;
    align-items: center;
    gap: 14px;
    min-width: 0;
    padding: 18px;
    border: 1px solid #383743;
    border-radius: 13px;
    background: #20202a;
    color: #f4f4f6;
    text-align: left;
    cursor: pointer;
    transition: .2s ease;
}

.account-quick-link:hover {
    border-color: rgba(255,204,77,.6);
    background: #262530;
    transform: translateY(-2px);
}

.account-quick-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 44px;
    height: 44px;
    flex: 0 0 44px;
    border-radius: 12px;
    background: rgba(255,204,77,.12);
    color: #ffcc4d;
    font-size: 22px;
}

.account-quick-link strong {
    display: block;
    font-size: 16px;
    line-height: 1.4;
}

.account-quick-link small {
    display: block;
    margin-top: 4px;
    color: #b0afbb;
    font-size: 14px;
}

/* КАРТОЧКИ ФИЛЬМОВ */

.account-movies {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 20px;
}

.account-movie-card {
    min-width: 0;
    overflow: hidden;
    border: 1px solid #383743;
    border-radius: 13px;
    background: #20202a;
    transition: .23s ease;
}

.account-movie-card:hover {
    border-color: rgba(255,204,77,.55);
    box-shadow: 0 12px 25px rgba(0,0,0,.2);
    transform: translateY(-3px);
}

.account-movie-card .account-movie {
    border: 0;
    border-radius: 0;
    background: transparent;
    transition: none;
}

.account-movie-card .account-movie:hover {
    border-color: transparent;
    box-shadow: none;
    transform: none;
}

.account-remove-button {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 7px;
    width: calc(100% - 24px);
    min-height: 38px;
    margin: 0 12px 12px;
    padding: 8px 10px;
    border: 1px solid #51404a;
    border-radius: 8px;
    background: rgba(150,55,65,.10);
    color: #ffadb3;
    font: inherit;
    font-size: 13px;
    font-weight: 700;
    cursor: pointer;
    transition: .2s ease;
}

.account-remove-button span {
    font-size: 19px;
    line-height:  .8;
}

.account-remove-button:hover {
    border-color: #b85b65;
    background: rgba(150,55,65,.24);
    color: #fff;
}

.account-remove-button:disabled {
    opacity: .55;
    cursor: wait;
}

.account-movie {
    display: block;
    overflow: hidden;
    min-width: 0;
    border: 1px solid #383743;
    border-radius: 13px;
    background: #20202a;
    color: #fff;
    text-decoration: none;
    transition: .23s ease;
}

.account-movie:hover {
    transform: translateY(-4px);
    border-color: rgba(255,204,77,.7);
    box-shadow: 0 12px 25px rgba(0,0,0,.2);
}

.account-movie-poster {
    position: relative;
    height: 255px;
    overflow: hidden;
    background: #292833;
}

.account-movie-poster img {
    display: block;
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform .35s ease;
}

.account-movie:hover .account-movie-poster img {
    transform: scale(1.045);
}

.account-poster-placeholder {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 100%;
    height: 100%;
    color: #ffcc4d;
    font-size: 48px;
}

.account-movie-year {
    position: absolute;
    top: 11px;
    right: 11px;
    padding: 7px 10px;
    border: 1px solid rgba(255,204,77,.25);
    border-radius: 7px;
    background: rgba(15,15,20,.88);
    color: #ffcc4d;
    font-size: 13px;
    font-weight: 700;
}

.account-movie-info {
    padding: 16px 17px 18px;
}

.account-movie-info h3 {
    margin: 0 0 8px;
    color: #fff;
    font-size: 18px;
    line-height: 1.4;
    overflow-wrap: anywhere;
}

.account-movie-info p {
    margin: 0;
    color: #b9b9c5;
    font-size: 15px;
    line-height: 1.5;
}

/* ПУСТЫЕ СПИСКИ */

.account-empty {
    padding: 48px 25px;
    border: 1px dashed #484653;
    border-radius: 14px;
    background: #20202a;
    color: #bdbdc8;
    text-align: center;
}

.account-empty-icon {
    margin-bottom: 13px;
    color: #ffcc4d;
    font-size: 39px;
}

.account-empty strong {
    display: block;
    color: #f5f5f7;
    font-size: 20px;
}

.account-empty p {
    max-width: 550px;
    margin: 10px auto 0;
    font-size: 16px;
    line-height: 1.7;
}

.account-empty a {
    display: inline-block;
    margin-top: 17px;
    color: #ffcc4d;
    font-size: 16px;
    font-weight: 800;
    text-decoration: none;
}

/* УВЕДОМЛЕНИЯ */

.notification-list {
    display: flex;
    flex-direction: column;
    gap: 14px;
}

.notification-card {
    padding: 22px 24px;
    border: 1px solid #383743;
    border-left: 4px solid #ffcc4d;
    border-radius: 12px;
    background: #20202a;
}

.notification-card h3 {
    margin: 0 0 10px;
    color: #fff;
    font-size: 20px;
}

.notification-card p {
    margin: 0;
    color: #c1c1cc;
    font-size: 16px;
    line-height: 1.7;
}

.notification-date {
    display: block;
    margin-top: 13px;
    color: #a2a2ae;
    font-size: 14px;
}

.notification-link {
    display: inline-block;
    margin-top: 13px;
    color: #ffcc4d;
    font-size: 15px;
    font-weight: 800;
    text-decoration: none;
}

/* ВЫХОД */

.account-bottom {
    display: flex;
    justify-content: flex-end;
    margin-top: 25px;
}

.account-logout {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 14px 23px;
    border: 1px solid #8d4148;
    border-radius: 9px;
    background: rgba(150,55,65,.12);
    color: #ff9ca3;
    font-size: 15px;
    font-weight: 800;
    text-decoration: none;
    transition: .2s ease;
}

.account-logout:hover {
    background: #8d4148;
    color: #fff;
}

/* АДАПТИВНОСТЬ */

@media (max-width: 1050px) {
    .account-movies {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }
}

@media (max-width: 800px) {
    .account-profile-card {
        gap: 20px;
        padding: 24px;
    }

    .account-avatar {
        width: 90px;
        height: 90px;
        flex-basis: 90px;
        font-size: 38px;
    }

    .account-profile-info h1 {
        font-size: 25px;
    }

    .account-statistics {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .account-quick-links {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 600px) {
    .account-page {
        padding: 28px 14px 50px;
    }

    .account-profile-card {
        gap: 15px;
        padding: 20px 16px;
    }

    .account-avatar {
        width: 72px;
        height: 72px;
        flex-basis: 72px;
        font-size: 31px;
    }

    .account-profile-info h1 {
        font-size: 21px;
    }

    .account-profile-info p {
        font-size: 14px;
    }

    .account-profile-label {
        font-size: 10px;
    }

    .account-edit-button {
        width: 43px;
        height: 43px;
        flex-basis: 43px;
        font-size: 18px;
    }

    .account-stat {
        padding: 16px;
    }

    .account-stat span {
        font-size: 14px;
    }

    .account-stat strong {
        font-size: 28px;
    }

    .account-panel {
        padding: 21px 15px;
    }

    .account-section-heading h2 {
        font-size: 23px;
    }

    .account-section-heading p {
        font-size: 14px;
    }

    .account-movies {
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 12px;
    }

    .account-movie-poster {
        height: 210px;
    }

    .account-movie-info {
        padding: 12px;
    }

    .account-movie-info h3 {
        font-size: 15px;
    }

    .account-movie-info p {
        font-size: 13px;
    }

    .account-tabs-wrap {
        top: 5px;
    }

    .account-tab {
        min-height: 41px;
        padding: 9px 12px;
        font-size: 13px;
    }
}

@media (max-width: 390px) {
    .account-profile-card {
        flex-wrap: wrap;
    }

    .account-edit-button {
        margin-left: auto;
    }

    .account-statistics {
        gap: 10px;
    }

    .account-movie-poster {
        height: 185px;
    }
}
</style>

<section class="account-page" data-csrf-token="<?php echo accountEscape($accountCsrf); ?>">

    <!-- ПРОФИЛЬ -->
    <div class="account-profile-card">

        <div class="account-avatar">
            <?php
            echo accountEscape(
                mb_strtoupper(
                    mb_substr($user["login"], 0, 1, "UTF-8"),
                    "UTF-8"
                )
            );
            ?>
        </div>

        <div class="account-profile-info">
            <span class="account-profile-label">ТВОЙ ПРОФИЛЬ</span>
            <h1><?php echo accountEscape($user["login"]); ?></h1>
            <p><?php echo accountEscape($user["email"]); ?></p>
        </div>

        <a
            href="edit_profile.php"
            class="account-edit-button"
            title="Настройки профиля"
            aria-label="Настройки профиля"
        >✎</a>

    </div>

    <?php if ($profileUpdated): ?>
        <div class="profile-message">
            Данные профиля успешно обновлены!
        </div>
    <?php endif; ?>

    <!-- СТАТИСТИКА -->
    <div class="account-statistics">

        <div class="account-stat">
            <span>В избранном</span>
            <strong data-count="favorites"><?php echo $favoriteCount; ?></strong>
        </div>

        <div class="account-stat">
            <span>Буду смотреть</span>
            <strong data-count="watchlist"><?php echo $watchlistCount; ?></strong>
        </div>

        <div class="account-stat">
            <span>Просмотрено</span>
            <strong><?php echo $watchedCount; ?></strong>
        </div>

        <div class="account-stat">
            <span>Уведомления</span>
            <strong><?php echo $notificationCount; ?></strong>
        </div>

    </div>

    <!-- ВКЛАДКИ -->
    <div class="account-tabs-wrap">
        <div class="account-tabs" role="tablist" aria-label="Разделы личного кабинета">

            <button type="button" class="account-tab active"
                    role="tab" aria-selected="true"
                    aria-controls="panel-overview"
                    data-tab="overview">
                Обзор
            </button>

            <button type="button" class="account-tab"
                    role="tab" aria-selected="false"
                    aria-controls="panel-favorites"
                    data-tab="favorites">
                ♡ Избранное
                <span class="account-tab-count" data-count="favorites"><?php echo $favoriteCount; ?></span>
            </button>

            <button type="button" class="account-tab"
                    role="tab" aria-selected="false"
                    aria-controls="panel-watchlist"
                    data-tab="watchlist">
                ＋ Буду смотреть
                <span class="account-tab-count" data-count="watchlist"><?php echo $watchlistCount; ?></span>
            </button>

            <button type="button" class="account-tab"
                    role="tab" aria-selected="false"
                    aria-controls="panel-watched"
                    data-tab="watched">
                ✓ Просмотренные
                <span class="account-tab-count"><?php echo $watchedCount; ?></span>
            </button>

            <button type="button" class="account-tab"
                    role="tab" aria-selected="false"
                    aria-controls="panel-notifications"
                    data-tab="notifications">
                Уведомления
                <span class="account-tab-count"><?php echo $notificationCount; ?></span>
            </button>

            <button type="button" class="account-tab"
                    role="tab" aria-selected="false"
                    aria-controls="panel-recommendations"
                    data-tab="recommendations">
                ✦ Рекомендации
                <span class="account-tab-count"><?php echo $recommendationCount; ?></span>
            </button>

        </div>
    </div>

    <!-- ОБЗОР -->
    <section class="account-panel" id="panel-overview"
             role="tabpanel" data-panel="overview">

        <div class="account-section-heading">
            <div>
                <h2>Моя коллекция</h2>
                <p>Все твои фильмы и планы на просмотр в одном месте.</p>
            </div>
        </div>

        <div class="account-overview-note">
            <h3>Собирай свою киноколлекцию</h3>
            <p>
                Сохраняй любимые фильмы, добавляй картины в список будущего просмотра
                и отмечай те, которые уже посмотрел(а). А в рекомендациях можно найти
                что-нибудь интересное по любимым жанрам.
            </p>
        </div>

        <div class="account-quick-links">

            <button type="button" class="account-quick-link"
                    data-open-tab="favorites">
                <span class="account-quick-icon">♡</span>
                <span>
                    <strong>Избранное</strong>
                    <small><span data-count="favorites"><?php echo $favoriteCount; ?></span> фильмов</small>
                </span>
            </button>

            <button type="button" class="account-quick-link"
                    data-open-tab="watchlist">
                <span class="account-quick-icon">＋</span>
                <span>
                    <strong>Буду смотреть</strong>
                    <small><span data-count="watchlist"><?php echo $watchlistCount; ?></span> фильмов</small>
                </span>
            </button>

            <button type="button" class="account-quick-link"
                    data-open-tab="watched">
                <span class="account-quick-icon">✓</span>
                <span>
                    <strong>Просмотренные</strong>
                    <small><?php echo $watchedCount; ?> фильмов</small>
                </span>
            </button>

        </div>
    </section>

    <!-- ИЗБРАННОЕ -->
    <section class="account-panel" id="panel-favorites"
             role="tabpanel" data-panel="favorites" hidden>

        <div class="account-section-heading">
            <div>
                <h2>Избранные фильмы</h2>
                <p>Картины, которые ты сохранил(а) сердечком.</p>
            </div>
            <span class="account-section-count"><span data-count="favorites"><?php echo $favoriteCount; ?></span> фильмов</span>
        </div>

        <?php if ($favoriteCount > 0): ?>
            <div class="account-movies">
                <?php accountRenderMovies($favorites, "favorites"); ?>
            </div>
        <?php else: ?>
            <div class="account-empty">
                <div class="account-empty-icon">♡</div>
                <strong>Избранное пока пусто</strong>
                <p>Нажми на сердечко рядом с понравившимся фильмом, и он появится здесь.</p>
                <a href="catalog.php">Перейти в каталог →</a>
            </div>
        <?php endif; ?>

    </section>

    <!-- БУДУ СМОТРЕТЬ -->
    <section class="account-panel" id="panel-watchlist"
             role="tabpanel" data-panel="watchlist" hidden>

        <div class="account-section-heading">
            <div>
                <h2>Буду смотреть</h2>
                <p>Фильмы, которые ты планируешь посмотреть позже.</p>
            </div>
            <span class="account-section-count"><span data-count="watchlist"><?php echo $watchlistCount; ?></span> фильмов</span>
        </div>

        <?php if ($watchlistCount > 0): ?>
            <div class="account-movies">
                <?php accountRenderMovies($watchlist, "watchlist"); ?>
            </div>
        <?php else: ?>
            <div class="account-empty">
                <div class="account-empty-icon">＋</div>
                <strong>Пока нет фильмов в этом списке</strong>
                <p>На странице фильма нажми «Буду смотреть», чтобы сохранить его на потом.</p>
                <a href="catalog.php">Выбрать фильм →</a>
            </div>
        <?php endif; ?>

    </section>

    <!-- ПРОСМОТРЕННЫЕ -->
    <section class="account-panel" id="panel-watched"
             role="tabpanel" data-panel="watched" hidden>

        <div class="account-section-heading">
            <div>
                <h2>Просмотренные фильмы</h2>
                <p>Твоя история уже просмотренных картин.</p>
            </div>
            <span class="account-section-count"><?php echo $watchedCount; ?> фильмов</span>
        </div>

        <?php if ($watchedCount > 0): ?>
            <div class="account-movies">
                <?php accountRenderMovies($watchedFilms); ?>
            </div>
        <?php else: ?>
            <div class="account-empty">
                <div class="account-empty-icon">✓</div>
                <strong>Пока нет просмотренных фильмов</strong>
                <p>На странице любого фильма отметь его как просмотренный — он появится здесь.</p>
                <a href="catalog.php">Перейти в каталог →</a>
            </div>
        <?php endif; ?>

    </section>

    <!-- УВЕДОМЛЕНИЯ -->
    <section class="account-panel" id="panel-notifications"
             role="tabpanel" data-panel="notifications" hidden>

        <div class="account-section-heading">
            <div>
                <h2>Мои уведомления</h2>
                <p>Новости и сообщения о фильмах.</p>
            </div>
            <span class="account-section-count"><?php echo $notificationCount; ?> уведомлений</span>
        </div>

        <?php if ($notificationCount > 0): ?>

            <div class="notification-list">

                <?php foreach ($notifications as $notification): ?>

                    <article class="notification-card">

                        <h3>
                            <?php echo accountEscape($notification["title"] ?? "Уведомление"); ?>
                        </h3>

                        <p>
                            <?php echo accountEscape($notification["message"] ?? ""); ?>
                        </p>

                        <span class="notification-date">
                            <?php
                            echo !empty($notification["created_at"])
                                ? accountEscape(date("d.m.Y H:i", strtotime($notification["created_at"])))
                                : "";
                            ?>
                        </span>

                        <?php if (!empty($notification["film_id"])): ?>
                            <a href="film.php?id=<?php echo (int)$notification["film_id"]; ?>"
                               class="notification-link">
                                Посмотреть фильм →
                            </a>
                        <?php endif; ?>

                    </article>

                <?php endforeach; ?>

            </div>

        <?php else: ?>

            <div class="account-empty">
                <div class="account-empty-icon">✉</div>
                <strong>Пока уведомлений нет</strong>
                <p>Здесь будут появляться новости о новых фильмах.</p>
            </div>

        <?php endif; ?>

    </section>

    <!-- РЕКОМЕНДАЦИИ -->
    <section class="account-panel" id="panel-recommendations"
             role="tabpanel" data-panel="recommendations" hidden>

        <div class="account-section-heading">
            <div>
                <h2>Рекомендации для тебя</h2>
                <p>Фильмы тех жанров, которые тебе уже понравились.</p>
            </div>
            <span class="account-section-count"><?php echo $recommendationCount; ?> фильмов</span>
        </div>

        <?php if ($recommendationCount > 0): ?>
            <div class="account-movies">
                <?php accountRenderMovies($recommendations); ?>
            </div>
        <?php else: ?>
            <div class="account-empty">
                <div class="account-empty-icon">✦</div>
                <strong>Пока нет рекомендаций</strong>
                <p>Добавь несколько фильмов в избранное, чтобы рекомендации подбирались по любимым жанрам.</p>
                <a href="catalog.php">Выбрать фильмы →</a>
            </div>
        <?php endif; ?>

    </section>

    <!-- ВЫХОД -->
    <div class="account-bottom">
        <a href="logout.php" class="account-logout">
            Выйти из аккаунта
        </a>
    </div>

</section>

<script>
document.addEventListener("DOMContentLoaded", function () {
    const tabs = Array.from(
        document.querySelectorAll(".account-tab[data-tab]")
    );

    const panels = Array.from(
        document.querySelectorAll(".account-panel[data-panel]")
    );

    function openAccountTab(name) {
        tabs.forEach(function (tab) {
            const isActive = tab.dataset.tab === name;

            tab.classList.toggle("active", isActive);
            tab.setAttribute(
                "aria-selected",
                isActive ? "true" : "false"
            );
        });

        panels.forEach(function (panel) {
            panel.hidden = panel.dataset.panel !== name;
        });

        if (history.replaceState) {
            history.replaceState(null, "", "#" + name);
        }
    }

    tabs.forEach(function (tab) {
        tab.addEventListener("click", function () {
            openAccountTab(tab.dataset.tab);
        });
    });

    document.querySelectorAll("[data-open-tab]").forEach(function (button) {
        button.addEventListener("click", function () {
            openAccountTab(button.dataset.openTab);

            document.querySelector(".account-tabs-wrap").scrollIntoView({
                behavior: "smooth",
                block: "nearest"
            });
        });
    });

    const requestedTab = window.location.hash.replace("#", "");
    const availableTabs = tabs.map(function (tab) {
        return tab.dataset.tab;
    });

    if (availableTabs.includes(requestedTab)) {
        openAccountTab(requestedTab);
    }

    /* УДАЛЕНИЕ ИЗ ИЗБРАННОГО И СПИСКА «БУДУ СМОТРЕТЬ» */
    const accountPage = document.querySelector(".account-page");
    const csrfToken = accountPage ? accountPage.dataset.csrfToken : "";

    function updateListCounts(listType, newCount) {
        document.querySelectorAll('[data-count="' + listType + '"]').forEach(function (element) {
            element.textContent = newCount;
        });

        const sectionCount = document.querySelector(
            '#panel-' + listType + ' .account-section-count'
        );
        if (sectionCount) {
            sectionCount.innerHTML = '<span data-count="' + listType + '">' +
                newCount + '</span> фильмов';
        }
    }

    document.addEventListener("click", async function (event) {
        const button = event.target.closest("[data-remove-movie]");
        if (!button) return;

        const filmId = button.dataset.filmId;
        const listType = button.dataset.listType;
        const card = button.closest("[data-film-card]");

        if (!filmId || !["favorites", "watchlist"].includes(listType) || !card) return;

        button.disabled = true;
        button.textContent = "Удаляем...";

        const formData = new FormData();
        formData.append("account_action", "remove_movie");
        formData.append("film_id", filmId);
        formData.append("list_type", listType);
        formData.append("csrf_token", csrfToken);

        try {
            const response = await fetch(window.location.href.split("#")[0], {
                method: "POST",
                body: formData,
                credentials: "same-origin",
                headers: { "X-Requested-With": "XMLHttpRequest" }
            });

            const result = await response.json();
            if (!response.ok || !result.success) {
                throw new Error(result.message || "Не удалось удалить фильм.");
            }

            const grid = card.parentElement;
            card.remove();

            const countElement = document.querySelector(
                '#panel-' + listType + ' [data-count="' + listType + '"]'
            );
            const currentCount = countElement ? parseInt(countElement.textContent, 10) || 0 : 1;
            const newCount = Math.max(0, currentCount - 1);
            updateListCounts(listType, newCount);

            if (newCount === 0 && grid && grid.classList.contains("account-movies")) {
                const emptyIcon = listType === "favorites" ? "♡" : "＋";
                const emptyTitle = listType === "favorites"
                    ? "Избранное пока пусто"
                    : "Пока нет фильмов в этом списке";
                const emptyText = listType === "favorites"
                    ? "Нажми на сердечко рядом с понравившимся фильмом, и он появится здесь."
                    : "На странице фильма нажми «Буду смотреть», чтобы сохранить его на потом.";
                const linkText = listType === "favorites" ? "Перейти в каталог →" : "Выбрать фильм →";

                grid.outerHTML =
                    '<div class="account-empty">' +
                        '<div class="account-empty-icon">' + emptyIcon + '</div>' +
                        '<strong>' + emptyTitle + '</strong>' +
                        '<p>' + emptyText + '</p>' +
                        '<a href="catalog.php">' + linkText + '</a>' +
                    '</div>';
            }
        } catch (error) {
            alert(error.message || "Не удалось удалить фильм. Попробуй ещё раз.");
            button.disabled = false;
            button.innerHTML = '<span aria-hidden="true">×</span> ' +
                (listType === "favorites" ? "Убрать из избранного" : "Удалить из списка");
        }
    });
});
</script>

<?php include "includes/footer.php"; ?>