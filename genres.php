<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include "includes/db.php";

/* Безопасный вывод текста */
function genreEscape($value): string
{
    return htmlspecialchars((string)($value ?? ""), ENT_QUOTES, "UTF-8");
}

/* Правильное склонение слова «фильм» */
function genreFilmWord(int $count): string
{
    $lastTwo = $count % 100;
    $lastOne = $count % 10;

    if ($lastTwo >= 11 && $lastTwo <= 14) {
        return "фильмов";
    }

    if ($lastOne === 1) {
        return "фильм";
    }

    if ($lastOne >= 2 && $lastOne <= 4) {
        return "фильма";
    }

    return "фильмов";
}

/* Подготовка пути к постеру */
function genrePosterUrl($poster): string
{
    $poster = trim((string)($poster ?? ""));

    if ($poster === "") {
        return "";
    }

    // Внешние изображения
    if (preg_match('~^https?://~i', $poster)) {
        return $poster;
    }

    $poster = str_replace("\\", "/", $poster);
    $projectRoot = realpath(__DIR__);

    if ($projectRoot === false) {
        return "";
    }

    // Убираем префикс папки проекта, если он уже сохранён в базе.
    $relativePath = preg_replace('~^/?movie_catalog/~i', '', $poster);
    $relativePath = ltrim($relativePath, "/");

    // Не разрешаем переходы к файлам за пределами папки проекта.
    if ($relativePath === "" || preg_match('~(^|/)\.\.(/|$)~', $relativePath)) {
        return "";
    }

    $candidates = [$relativePath];

    // Если в базе записано только имя файла, дополнительно ищем его в images/.
    if (strpos($relativePath, "/") === false) {
        $candidates[] = "images/" . $relativePath;
    }

    foreach ($candidates as $candidate) {
        $fullPath = realpath($projectRoot . DIRECTORY_SEPARATOR . $candidate);

        if (
            $fullPath !== false &&
            is_file($fullPath) &&
            strpos($fullPath, $projectRoot . DIRECTORY_SEPARATOR) === 0
        ) {
            return $candidate;
        }
    }

    return "";
}

// Получаем выбранный жанр.
$selectedGenre = isset($_GET["genre"])
    ? trim((string)$_GET["genre"])
    : "";

// Описания жанров.
$genreDescriptions = [
    "Фантастика" => "Необычные миры, космические путешествия и технологии будущего.",
    "Драма" => "Эмоциональные истории о жизни, отношениях и человеческих судьбах.",
    "Приключения" => "Захватывающие путешествия, открытия и опасные испытания.",
    "Фэнтези" => "Волшебство, мифические существа и сказочные вселенные.",
    "Комедия" => "Лёгкие и забавные истории для хорошего настроения.",
    "Боевик" => "Динамичные сцены, погони и напряжённые противостояния.",
    "Ужасы" => "Таинственные события, страх и неожиданные повороты.",
    "Мультфильм" => "Яркие истории и приключения для зрителей всех возрастов."
];

$genreIcons = [
    "Фантастика" => "✦",
    "Драма" => "♡",
    "Приключения" => "➤",
    "Фэнтези" => "✧",
    "Комедия" => "☼",
    "Боевик" => "⚡",
    "Ужасы" => "☠",
    "Мультфильм" => "★"
];

// Получаем все жанры и количество фильмов в каждом.
$genresResult = $conn->query(
    "SELECT genre, COUNT(*) AS total
     FROM films
     WHERE genre IS NOT NULL AND TRIM(genre) <> ''
     GROUP BY genre
     ORDER BY genre"
);

$genres = [];

if ($genresResult) {
    while ($genreRow = $genresResult->fetch_assoc()) {
        $genres[] = $genreRow;
    }
    $genresResult->free();
}

// Если выбран конкретный жанр, получаем фильмы только этого жанра.
$films = null;
$selectedGenreExists = false;

if ($selectedGenre !== "") {
    foreach ($genres as $genreRow) {
        if ($genreRow["genre"] === $selectedGenre) {
            $selectedGenreExists = true;
            break;
        }
    }

    if ($selectedGenreExists) {
        $stmt = $conn->prepare(
            "SELECT * FROM films
             WHERE genre = ?
             ORDER BY year DESC, title ASC"
        );

        if ($stmt) {
            $stmt->bind_param("s", $selectedGenre);
            $stmt->execute();
            $films = $stmt->get_result();
            $stmt->close();
        }
    }
}

include "includes/header.php";
?>

<style>
/* Общие подписи */
.genres-page .section-label {
    font-size: 15px !important;
}

/* Главный заголовок жанров */
.genres-heading h2 {
    font-size: 36px !important;
}

.genres-heading p {
    font-size: 18px !important;
    line-height: 1.7 !important;
}

/* Карточки жанров */
.genre-card-content h3 {
    font-size: 21px !important;
}

.genre-card-content p {
    font-size: 18px !important;
    line-height: 1.8 !important;
}

.genre-film-count {
    font-size: 15px !important;
}

.genre-card-icon {
    font-size: 26px !important;
}

.genre-arrow {
    font-size: 22px !important;
}

/* Заголовок выбранного жанра */
.genre-results-heading h2 {
    font-size: 36px !important;
}

.genre-results-heading p {
    font-size: 18px !important;
    line-height: 1.7 !important;
}

.back-to-genres {
    font-size: 16px !important;
}

/* Карточки фильмов */
.catalog-grid .movie-info h3 {
    font-size: 19px !important;
}

.catalog-grid .movie-info p {
    font-size: 16px !important;
}

.empty-message {
    font-size: 17px !important;
    line-height: 1.7 !important;
}
</style>

<section class="genres-page">

    <?php if ($selectedGenre === ""): ?>

        <div class="genres-heading">
            <span class="section-label">ВЫБИРАЙ НАСТРОЕНИЕ</span>
            <h2>Жанры фильмов</h2>
            <p>
                Каждый жанр — это отдельный мир и особенная история.
                Выбери направление и найди фильм по своему вкусу.
            </p>
        </div>

        <?php if (count($genres) > 0): ?>

            <div class="genres-catalog">

                <?php foreach ($genres as $genreRow): ?>

                    <?php
                    $name = (string)$genreRow["genre"];
                    $count = (int)$genreRow["total"];

                    $description = $genreDescriptions[$name]
                        ?? "Посмотри фильмы этого жанра и выбери интересную историю.";

                    $icon = $genreIcons[$name] ?? "▶";
                    ?>

                    <a
                        href="genres.php?genre=<?php echo urlencode($name); ?>"
                        class="genre-detail-card"
                    >
                        <div class="genre-card-icon">
                            <?php echo genreEscape($icon); ?>
                        </div>

                        <div class="genre-card-content">
                            <h3><?php echo genreEscape($name); ?></h3>

                            <p><?php echo genreEscape($description); ?></p>

                            <span class="genre-film-count">
                                <?php echo $count; ?>
                                <?php echo genreFilmWord($count); ?>
                            </span>
                        </div>

                        <span class="genre-arrow">→</span>
                    </a>

                <?php endforeach; ?>

            </div>

        <?php else: ?>

            <div class="empty-message">
                <h3>Жанры пока не добавлены</h3>
                <p>Когда в каталоге появятся фильмы, здесь отобразятся их жанры.</p>
                <a href="catalog.php" class="btn-primary">Перейти в каталог</a>
            </div>

        <?php endif; ?>

    <?php else: ?>

        <div class="genre-results-heading">

            <span class="section-label">КОЛЛЕКЦИЯ ФИЛЬМОВ</span>

            <h2><?php echo genreEscape($selectedGenre); ?></h2>

            <p>
                Фильмы в жанре «<?php echo genreEscape($selectedGenre); ?>».
                Выбери понравившийся фильм, чтобы узнать о нём больше.
            </p>

            <a href="genres.php" class="back-to-genres">
                ← Все жанры
            </a>

        </div>

        <?php if ($selectedGenreExists && $films && $films->num_rows > 0): ?>

            <div class="catalog-grid">

                <?php while ($film = $films->fetch_assoc()): ?>

                    <?php
                    $posterUrl = genrePosterUrl($film["poster"] ?? "");
                    $filmTitle = $film["title"] ?? "Фильм";
                    ?>

                    <a
                        href="film.php?id=<?php echo (int)$film["id"]; ?>"
                        class="movie-card"
                    >

                        <div class="movie-poster">

                            <?php if ($posterUrl !== ""): ?>

                                <img
                                    src="<?php echo genreEscape($posterUrl); ?>"
                                    alt="<?php echo genreEscape($filmTitle); ?>"
                                    loading="lazy"
                                >

                            <?php else: ?>

                                <span class="poster-placeholder">▶</span>

                            <?php endif; ?>

                            <span class="poster-year">
                                <?php echo (int)$film["year"]; ?>
                            </span>

                        </div>

                        <div class="movie-info">
                            <h3><?php echo genreEscape($filmTitle); ?></h3>
                            <p><?php echo genreEscape($film["genre"]); ?></p>
                        </div>

                    </a>

                <?php endwhile; ?>

            </div>

        <?php else: ?>

            <div class="empty-message">
                <h3>Фильмы этого жанра не найдены</h3>
                <p>
                    Возможно, этот жанр больше не содержит фильмов.
                    Выбери другой жанр или вернись в каталог.
                </p>
                <a href="genres.php" class="btn-primary">Вернуться к жанрам</a>
            </div>

        <?php endif; ?>

        <?php if ($films instanceof mysqli_result): ?>
            <?php $films->free(); ?>
        <?php endif; ?>

    <?php endif; ?>

</section>

<?php include "includes/footer.php"; ?>
