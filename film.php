<?php

session_start();

include "includes/db.php";



$id = isset($_GET["id"]) ? (int)$_GET["id"] : 0;



$stmt = $conn->prepare("SELECT * FROM films WHERE id = ?");

$stmt->bind_param("i", $id);

$stmt->execute();

$film = $stmt->get_result()->fetch_assoc();

$stmt->close();



if (!$film) {

    header("Location: catalog.php");

    exit;

}



$title = htmlspecialchars($film["title"] ?? "Фильм", ENT_QUOTES, "UTF-8");



$isUser = isset($_SESSION["user_id"]) &&

          ($_SESSION["user_role"] ?? "") === "user";



$userId = $isUser ? (int)$_SESSION["user_id"] : 0;



if (empty($_SESSION["review_token"])) {

    $_SESSION["review_token"] = bin2hex(random_bytes(32));

}



if (empty($_SESSION["watched_token"])) {

    $_SESSION["watched_token"] = bin2hex(random_bytes(32));

}



if (empty($_SESSION["watchlist_token"])) {

    $_SESSION["watchlist_token"] = bin2hex(random_bytes(32));

}



$reviewMessage = "";

$reviewError = "";

$watchedMessage = "";

$watchedError = "";

$watchlistMessage = "";

$watchlistError = "";



/* ДОБАВЛЕНИЕ / УДАЛЕНИЕ ИЗ СПИСКА «БУДУ СМОТРЕТЬ» */



if ($_SERVER["REQUEST_METHOD"] === "POST" &&

    isset($_POST["toggle_watchlist"])) {



    if (!$isUser) {

        $watchlistError = "Войдите в аккаунт, чтобы сохранить фильм в список «Буду смотреть».";

    } elseif (

        !isset($_POST["watchlist_token"]) ||

        !hash_equals($_SESSION["watchlist_token"], $_POST["watchlist_token"])

    ) {

        $watchlistError = "Ошибка проверки формы. Обновите страницу.";

    } else {

        $action = $_POST["toggle_watchlist"];



        if ($action === "add") {

            $stmt = $conn->prepare(

                "INSERT IGNORE INTO watchlist (user_id, film_id) VALUES (?, ?)"

            );

            $stmt->bind_param("ii", $userId, $id);



            if ($stmt->execute()) {

                $stmt->close();

                header("Location: film.php?id=" . $id . "#film-actions");

                exit;

            }



            $watchlistError = "Не удалось добавить фильм в список.";

            $stmt->close();



        } elseif ($action === "remove") {

            $stmt = $conn->prepare(

                "DELETE FROM watchlist WHERE user_id = ? AND film_id = ?"

            );

            $stmt->bind_param("ii", $userId, $id);



            if ($stmt->execute()) {

                $stmt->close();

                header("Location: film.php?id=" . $id . "#film-actions");

                exit;

            }



            $watchlistError = "Не удалось убрать фильм из списка.";

            $stmt->close();

        }

    }

}



/* ОТМЕТКА ПРОСМОТРЕННОГО ФИЛЬМА */



if ($_SERVER["REQUEST_METHOD"] === "POST" &&

    isset($_POST["mark_watched"])) {



    if (!$isUser) {

        $watchedError = "Войдите в аккаунт, чтобы отметить фильм просмотренным.";

    } elseif (

        !isset($_POST["watched_token"]) ||

        !hash_equals($_SESSION["watched_token"], $_POST["watched_token"])

    ) {

        $watchedError = "Ошибка проверки формы. Обновите страницу.";

    } else {

        $stmt = $conn->prepare(

            "INSERT INTO watched_films (user_id, film_id)

             VALUES (?, ?)

             ON DUPLICATE KEY UPDATE watched_at = CURRENT_TIMESTAMP"

        );



        $stmt->bind_param("ii", $userId, $id);



        if ($stmt->execute()) {

            $stmt->close();



            // После отметки «Просмотрен» убираем фильм из списка «Буду смотреть».

            $removeStmt = $conn->prepare(

                "DELETE FROM watchlist WHERE user_id = ? AND film_id = ?"

            );

            $removeStmt->bind_param("ii", $userId, $id);

            $removeStmt->execute();

            $removeStmt->close();



            header("Location: film.php?id=" . $id . "#film-actions");

            exit;

        }



        $watchedError = "Не удалось отметить фильм просмотренным.";

        $stmt->close();

    }

}



/* ПРОВЕРКА: ПРОСМОТРЕН ЛИ ФИЛЬМ */



$isWatched = false;



if ($isUser) {

    $stmt = $conn->prepare(

        "SELECT id FROM watched_films

         WHERE user_id = ? AND film_id = ?"

    );



    $stmt->bind_param("ii", $userId, $id);

    $stmt->execute();



    $isWatched = $stmt->get_result()->num_rows > 0;

    $stmt->close();

}



/* ПРОВЕРКА: ЕСТЬ ЛИ ФИЛЬМ В СПИСКЕ «БУДУ СМОТРЕТЬ» */



$isInWatchlist = false;



if ($isUser) {

    $stmt = $conn->prepare(

        "SELECT id FROM watchlist WHERE user_id = ? AND film_id = ?"

    );

    $stmt->bind_param("ii", $userId, $id);

    $stmt->execute();



    $isInWatchlist = $stmt->get_result()->num_rows > 0;

    $stmt->close();

}



/* ОБРАБОТКА ОТЗЫВА */



if ($_SERVER["REQUEST_METHOD"] === "POST" &&

    isset($_POST["submit_review"])) {



    if (!$isUser) {

        $reviewError = "Чтобы оставить отзыв, необходимо войти в аккаунт.";

    } elseif (

        !isset($_POST["review_token"]) ||

        !hash_equals($_SESSION["review_token"], $_POST["review_token"])

    ) {

        $reviewError = "Ошибка проверки формы. Обновите страницу и попробуйте снова.";

    } else {

        $rating = isset($_POST["rating"]) ? (int)$_POST["rating"] : 0;

        $reviewText = trim($_POST["review_text"] ?? "");



        if ($rating < 1 || $rating > 5) {

            $reviewError = "Выберите оценку от 1 до 5 звёзд.";

        } elseif ($reviewText === "") {

            $reviewError = "Напишите текст отзыва.";

        } elseif (mb_strlen($reviewText) > 2000) {

            $reviewError = "Отзыв не должен превышать 2000 символов.";

        } else {

            $stmt = $conn->prepare(

                "INSERT INTO reviews (film_id, user_id, rating, review_text)

                 VALUES (?, ?, ?, ?)

                 ON DUPLICATE KEY UPDATE

                    rating = VALUES(rating),

                    review_text = VALUES(review_text),

                    updated_at = CURRENT_TIMESTAMP"

            );



            $stmt->bind_param("iiis", $id, $userId, $rating, $reviewText);



            if ($stmt->execute()) {

                $stmt->close();

                header("Location: film.php?id=" . $id . "#reviews");

                exit;

            }



            $reviewError = "Не удалось сохранить отзыв. Попробуйте ещё раз.";

            $stmt->close();

        }

    }

}



/* ОТЗЫВ ТЕКУЩЕГО ПОЛЬЗОВАТЕЛЯ */



$myReview = null;



if ($isUser) {

    $stmt = $conn->prepare(

        "SELECT rating, review_text

         FROM reviews

         WHERE film_id = ? AND user_id = ?"

    );



    $stmt->bind_param("ii", $id, $userId);

    $stmt->execute();

    $myReview = $stmt->get_result()->fetch_assoc();

    $stmt->close();

}



/* ОБЩАЯ ОЦЕНКА */



$stmt = $conn->prepare(

    "SELECT COUNT(*) AS total_reviews, AVG(rating) AS average_rating

     FROM reviews WHERE film_id = ?"

);



$stmt->bind_param("i", $id);

$stmt->execute();

$reviewStats = $stmt->get_result()->fetch_assoc();

$stmt->close();



$totalReviews = (int)$reviewStats["total_reviews"];

$averageRating = $totalReviews > 0

    ? round((float)$reviewStats["average_rating"], 1)

    : 0;



/* ВСЕ ОТЗЫВЫ */



$stmt = $conn->prepare(

    "SELECT reviews.*, users.login

     FROM reviews

     INNER JOIN users ON reviews.user_id = users.id

     WHERE reviews.film_id = ?

     ORDER BY reviews.created_at DESC"

);



$stmt->bind_param("i", $id);

$stmt->execute();



$reviewsResult = $stmt->get_result();

$reviews = [];



while ($review = $reviewsResult->fetch_assoc()) {

    $reviews[] = $review;

}



$stmt->close();



include "includes/header.php";



/* ПОСТЕР */



$poster = trim($film["poster"] ?? "");



if ($poster !== "" && !preg_match('~^(https?://|/)~i', $poster)) {

    if (!file_exists(__DIR__ . "/" . $poster)) {

        $poster = "images/" . $poster;

    }

}



if ($poster !== "" &&

    !preg_match('~^https?://~i', $poster) &&

    !file_exists(__DIR__ . "/" . ltrim($poster, "/"))) {

    $poster = "";

}



/* ТРЕЙЛЕР */



$trailer = trim($film["trailer_url"] ?? "");

$trailerEmbed = "";



if ($trailer !== "" && preg_match(

    '~(?:youtube\\.com/watch\\?v=|youtu\\.be/|youtube\\.com/embed/)([a-zA-Z0-9_-]{11})~',

    $trailer,

    $matches

)) {

    $trailerEmbed = "https://www.youtube.com/embed/" . $matches[1];

}



$posterStyle = $poster !== ""

    ? "background-image: linear-gradient(90deg, rgba(15,15,20,.97) 0%, rgba(15,15,20,.88) 48%, rgba(15,15,20,.72) 100%), linear-gradient(0deg, #101014 0%, transparent 55%), url('" . htmlspecialchars($poster, ENT_QUOTES, "UTF-8") . "');"

    : "";

?>



<style>

.film-actions {

    margin-top: 28px;

    padding: 20px;

    max-width: 650px;

    background: rgba(18, 18, 25, 0.78);

    border: 1px solid rgba(255, 204, 77, 0.18);

    border-radius: 18px;

    backdrop-filter: blur(10px);

}



.film-action-buttons {

    display: flex;

    flex-wrap: wrap;

    align-items: center;

    gap: 12px;

}



.film-action-button {

    display: inline-flex;

    align-items: center;

    justify-content: center;

    gap: 9px;

    min-height: 48px;

    padding: 12px 19px;

    border: 1px solid transparent;

    border-radius: 999px;

    font: inherit;

    font-size: 15px;

    font-weight: 700;

    text-decoration: none;

    cursor: pointer;

    transition: *background* .2s ease, border-color .2s ease, transform .2s ease;

}



.film-action-button:hover:not(:disabled) {

    transform: translateY(-2px);

}



.film-action-button span {

    font-size: 19px;

    line-height: 1;

}



.watchlist-button {

    color: #171717;

    background: #ffcc4d;

    border-color: #ffcc4d;

}



.watchlist-button:hover:not(:disabled) {

    background: #ffdc7b;

    border-color: #ffdc7b;

}



.watchlist-button.active {

    color: #ffdc7b;

    background: rgba(255, 204, 77, .12);

    border-color: rgba(255, 204, 77, .48);

}



.watchlist-button.inactive {

    color: #b8b8c0;

    background: #292932;

    border-color: #383842;

    cursor: default;

}



.watched-button {

    color: #f5f5f5;

    background: #292932;

    border-color: #454550;

}



.watched-button:hover:not(:disabled) {

    background: #383844;

    border-color: #ffcc4d;

}



.watched-button.done {

    color: #ffdc7b;

    background: rgba(255, 204, 77, .12);

    border-color: rgba(255, 204, 77, .42);

    cursor: default;

}



.film-action-button:disabled {

    opacity: .9;

}



.film-actions-hint {

    margin: 13px 2px 0;

    color: #aaaab4;

    font-size: 13px;

    line-height: 1.5;

}



.film-action-error {

    margin-bottom: 12px;

    color: #ff8585;

    font-size: 14px;

}



@media (max-width: 600px) {

    .film-actions {

        padding: 15px;

    }



    .film-action-buttons {

        align-items: stretch;

        flex-direction: column;

    }



    .film-action-buttons form,

    .film-action-button {

        width: 100%;

    }

}

</style>



<section class="film-page">



    <a href="catalog.php" class="film-back">

        <span aria-hidden="true">←</span> Вернуться в каталог

    </a>



    <div class="film-hero" style="<?php echo $posterStyle; ?>">
        <?php if ($trailerEmbed !== ""): ?>
            <div class="film-hero-video" aria-hidden="true"
                 style="position:absolute;inset:0;width:100%;height:100%;overflow:hidden;z-index:0;pointer-events:none;">
                <iframe
                    style="position:absolute;top:50%;left:50%;width:177.78%;height:177.78%;min-width:100%;min-height:100%;border:0;transform:translate(-50%,-50%);pointer-events:none;"
                    src="<?php echo htmlspecialchars(
                        $trailerEmbed . '?autoplay=1&mute=1&controls=0&loop=1&playlist=' . $matches[1] . '&playsinline=1&rel=0&modestbranding=1&iv_load_policy=3',
                        ENT_QUOTES,
                        "UTF-8"
                    ); ?>"
                    title="Фоновый трейлер фильма"
                    tabindex="-1"
                    allow="autoplay; encrypted-media; picture-in-picture"
                    referrerpolicy="strict-origin-when-cross-origin"
                ></iframe>
            </div>
        <?php endif; ?>

        <div class="film-hero-inner" style="position:relative;z-index:2;">



            <div class="film-poster-large">

                <?php if ($poster !== ""): ?>

                    <img

                        src="<?php echo htmlspecialchars($poster, ENT_QUOTES, "UTF-8"); ?>"

                        alt="Постер фильма «<?php echo $title; ?>»"

                    >

                <?php else: ?>

                    <div class="film-poster-placeholder">▶</div>

                <?php endif; ?>

            </div>



            <div class="film-hero-content">



                <span class="film-kicker">МИР КИНО · КАРТОЧКА ФИЛЬМА</span>



                <h1><?php echo $title; ?></h1>



                <div class="film-meta">

                    <?php if (!empty($film["year"])): ?>

                        <span><?php echo (int)$film["year"]; ?> год</span>

                    <?php endif; ?>



                    <?php if (!empty($film["genre"])): ?>

                        <span><?php echo htmlspecialchars($film["genre"], ENT_QUOTES, "UTF-8"); ?></span>

                    <?php endif; ?>



                    <?php if (!empty($film["runtime"])): ?>

                        <span><?php echo htmlspecialchars($film["runtime"], ENT_QUOTES, "UTF-8"); ?></span>

                    <?php endif; ?>

                </div>



                <?php if (!empty($film["rating"])): ?>

                    <div class="film-rating">

                        <span>★</span>

                        <?php echo number_format((float)$film["rating"], 1); ?>

                        <small>/ 10</small>

                    </div>

                <?php endif; ?>



                <div class="film-user-rating">

                    <span>★</span>



                    <?php if ($totalReviews > 0): ?>

                        Оценка зрителей:

                        <strong><?php echo number_format($averageRating, 1); ?>/5</strong>

                        <small>(<?php echo $totalReviews; ?> отзывов)</small>

                    <?php else: ?>

                        Пока нет оценок зрителей

                    <?php endif; ?>

                </div>



                <p class="film-hero-note">

                    Узнайте больше о фильме: история создания,

                    актёрский состав и подробное описание.

                </p>



                <a href="#film-description" class="film-read-more">

                    Читать описание <span>↓</span>

                </a>



                <!-- СПИСКИ ФИЛЬМОВ: БУДУ СМОТРЕТЬ И ПРОСМОТРЕННЫЕ -->

                <div class="film-actions" id="film-actions">



                    <?php if ($watchlistError !== ""): ?>

                        <div class="film-action-error">

                            <?php echo htmlspecialchars($watchlistError, ENT_QUOTES, "UTF-8"); ?>

                        </div>

                    <?php endif; ?>



                    <?php if ($watchedError !== ""): ?>

                        <div class="film-action-error">

                            <?php echo htmlspecialchars($watchedError, ENT_QUOTES, "UTF-8"); ?>

                        </div>

                    <?php endif; ?>



                    <?php if ($isUser): ?>



                        <div class="film-action-buttons">



                            <form method="POST" action="film.php?id=<?php echo $id; ?>#film-actions">

                                <input

                                    type="hidden"

                                    name="watchlist_token"

                                    value="<?php echo htmlspecialchars($_SESSION["watchlist_token"], ENT_QUOTES, "UTF-8"); ?>"

                                >



                                <?php if ($isInWatchlist): ?>

                                    <button

                                        type="submit"

                                        name="toggle_watchlist"

                                        value="remove"

                                        class="film-action-button watchlist-button active"

                                    >

                                        <span>✓</span> В списке «Буду смотреть»

                                    </button>

                                <?php elseif (!$isWatched): ?>

                                    <button

                                        type="submit"

                                        name="toggle_watchlist"

                                        value="add"

                                        class="film-action-button watchlist-button"

                                    >

                                        <span>＋</span> Буду смотреть

                                    </button>

                                <?php else: ?>

                                    <button

                                        type="button"

                                        class="film-action-button watchlist-button inactive"

                                        disabled

                                    >

                                        <span>✓</span> Уже просмотрен

                                    </button>

                                <?php endif; ?>

                            </form>



                            <?php if ($isWatched): ?>



                                <button class="film-action-button watched-button done" type="button" disabled>

                                    <span>✓</span> Просмотрен

                                </button>



                            <?php else: ?>



                                <form method="POST" action="film.php?id=<?php echo $id; ?>#film-actions">

                                    <input

                                        type="hidden"

                                        name="watched_token"

                                        value="<?php echo htmlspecialchars($_SESSION["watched_token"], ENT_QUOTES, "UTF-8"); ?>"

                                    >



                                    <button

                                        type="submit"

                                        name="mark_watched"

                                        class="film-action-button watched-button"

                                    >

                                        <span>◉</span> Просмотрен

                                    </button>

                                </form>



                            <?php endif; ?>



                        </div>



                        <p class="film-actions-hint">

                            Сохраните фильм в список планов или отметьте, если уже посмотрели.

                        </p>



                    <?php else: ?>



                        <div class="film-action-buttons">

                            <a href="login.php" class="film-action-button watchlist-button">

                                <span>＋</span> Буду смотреть

                            </a>

                            <a href="login.php" class="film-action-button watched-button">

                                <span>◉</span> Просмотрен

                            </a>

                        </div>



                        <p class="film-actions-hint">

                            Войдите в аккаунт, чтобы сохранять фильмы в свои списки.

                        </p>



                    <?php endif; ?>



                </div>



            </div>

        </div>



        <div class="film-hero-bottom-line"></div>

    </div>



    <div class="film-information">



        <div class="film-section-heading">

            <span class="film-kicker">ПОДРОБНОСТИ</span>

            <h2>О фильме</h2>

            <p>Основная информация и интересные детали</p>

        </div>



        <div class="film-extra">



            <?php

            $details = [

                "director" => ["🎬", "Режиссёр"],

                "actors" => ["✦", "В главных ролях"],

                "country" => ["🌍", "Страна производства"],

                "runtime" => ["◷", "Продолжительность"],

                "year" => ["▣", "Год выпуска"],

                "genre" => ["✧", "Жанр"]

            ];

            ?>



            <?php foreach ($details as $field => $detail): ?>

                <?php if (!empty($film[$field])): ?>

                    <div class="film-extra-card">

                        <span class="film-extra-icon"><?php echo $detail[0]; ?></span>

                        <div>

                            <span class="film-extra-label">

                                <?php echo $detail[1]; ?>

                            </span>

                            <strong>

                                <?php echo htmlspecialchars((string)$film[$field], ENT_QUOTES, "UTF-8"); ?>

                            </strong>

                        </div>

                    </div>

                <?php endif; ?>

            <?php endforeach; ?>



        </div>



        <section class="film-description" id="film-description">

            <div class="film-description-heading">

                <span class="film-kicker">СЮЖЕТ</span>

                <h2>Описание фильма</h2>

            </div>



            <p>

                <?php echo nl2br(htmlspecialchars(

                    $film["description"] ?? "Описание пока не добавлено.",

                    ENT_QUOTES,

                    "UTF-8"

                )); ?>

            </p>

        </section>



        <?php if ($trailerEmbed !== ""): ?>



            <section class="film-trailer">

                <div class="film-section-heading">

                    <span class="film-kicker">ВИДЕО</span>

                    <h2>Трейлер фильма</h2>

                </div>



                <div class="film-video">

                    <iframe

                        src="<?php echo htmlspecialchars($trailerEmbed, ENT_QUOTES, "UTF-8"); ?>"

                        title="Трейлер фильма «<?php echo $title; ?>»"

                        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"

                        allowfullscreen

                        loading="lazy">

                    </iframe>

                </div>

            </section>



        <?php elseif ($trailer !== ""): ?>



            <a

                href="<?php echo htmlspecialchars($trailer, ENT_QUOTES, "UTF-8"); ?>"

                target="_blank"

                rel="noopener noreferrer"

                class="film-trailer-button">

                ▶ Смотреть трейлер

            </a>



        <?php endif; ?>



        <!-- ОТЗЫВЫ -->

        <section class="reviews-section" id="reviews">



            <div class="film-section-heading">

                <span class="film-kicker">МНЕНИЕ ЗРИТЕЛЕЙ</span>

                <h2>Отзывы о фильме</h2>

                <p>Поделитесь впечатлениями и узнайте мнение других зрителей</p>

            </div>



            <div class="reviews-summary">



                <div class="reviews-summary-number">

                    <?php echo $totalReviews > 0 ? number_format($averageRating, 1) : "—"; ?>

                    <span>/ 5</span>

                </div>



                <div class="reviews-summary-info">



                    <div class="reviews-stars">

                        <?php

                        $roundedRating = (int)round($averageRating);

                        for ($i = 1; $i <= 5; $i++):

                        ?>

                            <span class="<?php echo $i <= $roundedRating ? "active" : ""; ?>">★</span>

                        <?php endfor; ?>

                    </div>



                    <p><?php echo $totalReviews; ?> отзывов</p>



                </div>



            </div>



            <?php if ($isUser): ?>



                <div class="review-form-card">



                    <h3>

                        <?php echo $myReview ? "Изменить свой отзыв" : "Оставить отзыв"; ?>

                    </h3>



                    <?php if ($reviewError !== ""): ?>

                        <div class="review-message error">

                            <?php echo htmlspecialchars($reviewError, ENT_QUOTES, "UTF-8"); ?>

                        </div>

                    <?php endif; ?>



                    <form method="POST" action="film.php?id=<?php echo $id; ?>#reviews">



                        <input

                            type="hidden"

                            name="review_token"

                            value="<?php echo htmlspecialchars($_SESSION["review_token"], ENT_QUOTES, "UTF-8"); ?>"

                        >



                        <label class="review-label">Ваша оценка</label>



                        <div class="review-rating-input">



                            <?php $selectedRating = (int)($myReview["rating"] ?? 5); ?>



                            <?php for ($i = 5; $i >= 1; $i--): ?>



                                <input

                                    type="radio"

                                    id="rating-<?php echo $i; ?>"

                                    name="rating"

                                    value="<?php echo $i; ?>"

                                    <?php echo $selectedRating === $i ? "checked" : ""; ?>

                                    required

                                >



                                <label for="rating-<?php echo $i; ?>">★</label>



                            <?php endfor; ?>



                        </div>



                        <label for="review_text" class="review-label">

                            Ваш отзыв

                        </label>



                        <textarea

                            id="review_text"

                            name="review_text"

                            rows="5"

                            maxlength="2000"

                            placeholder="Расскажите, что вам понравилось или не понравилось..."

                            required

                        ><?php echo htmlspecialchars($myReview["review_text"] ?? "", ENT_QUOTES, "UTF-8"); ?></textarea>



                        <button type="submit" name="submit_review" class="review-submit">

                            <?php echo $myReview ? "Сохранить изменения" : "Опубликовать отзыв"; ?>

                        </button>



                    </form>



                </div>



            <?php else: ?>



                <div class="review-login-notice">

                    <p>Хотите поделиться своим мнением о фильме?</p>

                    <a href="login.php">Войдите в аккаунт</a>, чтобы оставить отзыв и оценку.

                </div>



            <?php endif; ?>



            <div class="reviews-list">



                <h3>Мнения зрителей (<?php echo $totalReviews; ?>)</h3>



                <?php if (count($reviews) > 0): ?>



                    <?php foreach ($reviews as $review): ?>



                        <article class="review-card">



                            <div class="review-card-top">



                                <div class="review-author">



                                    <div class="review-avatar">

                                        <?php

                                        $login = $review["login"] ?? "Пользователь";

                                        echo htmlspecialchars(

                                            mb_strtoupper(mb_substr($login, 0, 1)),

                                            ENT_QUOTES,

                                            "UTF-8"

                                        );

                                        ?>

                                    </div>



                                    <div>

                                        <strong>

                                            <?php echo htmlspecialchars($login, ENT_QUOTES, "UTF-8"); ?>

                                        </strong>



                                        <span>

                                            <?php

                                            echo date(

                                                "d.m.Y",

                                                strtotime($review["updated_at"] ?? $review["created_at"])

                                            );

                                            ?>

                                        </span>

                                    </div>



                                </div>



                                <div class="review-card-stars">

                                    <?php for ($i = 1; $i <= 5; $i++): ?>

                                        <span class="<?php echo $i <= (int)$review["rating"] ? "active" : ""; ?>">★</span>

                                    <?php endfor; ?>

                                </div>



                            </div>



                            <p class="review-card-text">

                                <?php echo nl2br(htmlspecialchars($review["review_text"], ENT_QUOTES, "UTF-8")); ?>

                            </p>



                            <?php if (

                                $isUser &&

                                $userId === (int)$review["user_id"]

                            ): ?>

                                <span class="review-own-label">Ваш отзыв</span>

                            <?php endif; ?>



                        </article>



                    <?php endforeach; ?>



                <?php else: ?>



                    <div class="reviews-empty">

                        <span>✦</span>

                        <h3>Пока нет отзывов</h3>

                        <p>Станьте первым, кто поделится впечатлениями об этом фильме!</p>

                    </div>



                <?php endif; ?>



            </div>



        </section>



    </div>



</section>



<?php include "includes/footer.php"; ?>