<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include "includes/db.php";

/* =========================================
   ПОЛЬЗОВАТЕЛЬ И ИЗБРАННОЕ
========================================= */

$isLoggedIn = isset($_SESSION["user_id"]);
$userId = $isLoggedIn ? (int)$_SESSION["user_id"] : 0;

$favoriteIds = [];

if ($isLoggedIn) {
    $favoriteStmt = $conn->prepare(
        "SELECT film_id FROM favorites WHERE user_id = ?"
    );
    $favoriteStmt->bind_param("i", $userId);
    $favoriteStmt->execute();
    $favoriteResult = $favoriteStmt->get_result();

    while ($favorite = $favoriteResult->fetch_assoc()) {
        $favoriteIds[] = (int)$favorite["film_id"];
    }

    $favoriteStmt->close();
}

/* =========================================
   ПАРАМЕТРЫ ПОИСКА, ФИЛЬТРАЦИИ И СОРТИРОВКИ
========================================= */

$search = trim($_GET["search"] ?? "");
$genre = trim($_GET["genre"] ?? "");
$sort = $_GET["sort"] ?? "newest";

$yearFrom = isset($_GET["year_from"]) && $_GET["year_from"] !== ""
    ? (int)$_GET["year_from"]
    : 0;

$yearTo = isset($_GET["year_to"]) && $_GET["year_to"] !== ""
    ? (int)$_GET["year_to"]
    : 0;

// Защита от некорректных значений года.
if ($yearFrom < 1900 || $yearFrom > 2100) {
    $yearFrom = 0;
}

if ($yearTo < 1900 || $yearTo > 2100) {
    $yearTo = 0;
}

// Если годы выбраны в обратном порядке, меняем их местами.
if ($yearFrom > 0 && $yearTo > 0 && $yearFrom > $yearTo) {
    [$yearFrom, $yearTo] = [$yearTo, $yearFrom];
}

$allowedSorts = [
    "newest" => "year DESC, title ASC",
    "oldest" => "year ASC, title ASC",
    "name"   => "title ASC"
];

$orderBy = $allowedSorts[$sort] ?? $allowedSorts["newest"];
$sort = isset($allowedSorts[$sort]) ? $sort : "newest";

/* =========================================
   СПИСОК ЖАНРОВ И ДОСТУПНЫХ ГОДОВ
========================================= */

$genresResult = $conn->query(
    "SELECT DISTINCT genre FROM films
     WHERE genre IS NOT NULL AND genre <> ''
     ORDER BY genre"
);

$yearRangeResult = $conn->query(
    "SELECT MIN(year) AS min_year, MAX(year) AS max_year FROM films"
);
$yearRange = $yearRangeResult ? $yearRangeResult->fetch_assoc() : [];

$minAvailableYear = !empty($yearRange["min_year"])
    ? (int)$yearRange["min_year"]
    : 1900;
$maxAvailableYear = !empty($yearRange["max_year"])
    ? (int)$yearRange["max_year"]
    : (int)date("Y");

/* =========================================
   ФОРМИРУЕМ ОБЩИЕ УСЛОВИЯ ЗАПРОСА
========================================= */

$conditions = [];
$params = [];
$types = "";

if ($search !== "") {
    $conditions[] = "title LIKE ?";
    $params[] = "%" . $search . "%";
    $types .= "s";
}

if ($genre !== "") {
    $conditions[] = "genre = ?";
    $params[] = $genre;
    $types .= "s";
}

if ($yearFrom > 0) {
    $conditions[] = "year >= ?";
    $params[] = $yearFrom;
    $types .= "i";
}

if ($yearTo > 0) {
    $conditions[] = "year <= ?";
    $params[] = $yearTo;
    $types .= "i";
}

$whereSql = !empty($conditions)
    ? " WHERE " . implode(" AND ", $conditions)
    : "";

/* =========================================
   КОЛИЧЕСТВО ФИЛЬМОВ И ПАГИНАЦИЯ
========================================= */

$perPage = 9;

$countSql = "SELECT COUNT(*) AS total FROM films" . $whereSql;
$countStmt = $conn->prepare($countSql);

if (!empty($params)) {
    $countStmt->bind_param($types, ...$params);
}

$countStmt->execute();
$totalFilms = (int)$countStmt->get_result()->fetch_assoc()["total"];
$countStmt->close();

$totalPages = max(1, (int)ceil($totalFilms / $perPage));
$page = isset($_GET["page"]) ? max(1, (int)$_GET["page"]) : 1;
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

/* =========================================
   ПОЛУЧАЕМ ФИЛЬМЫ
========================================= */

$sql = "SELECT * FROM films" . $whereSql . " ORDER BY " . $orderBy . " LIMIT ? OFFSET ?";

$filmParams = $params;
$filmTypes = $types . "ii";
$filmParams[] = $perPage;
$filmParams[] = $offset;

$stmt = $conn->prepare($sql);
$stmt->bind_param($filmTypes, ...$filmParams);
$stmt->execute();
$result = $stmt->get_result();

/*
 * Формирует ссылку на каталог, сохраняя активные параметры.
 * Это нужно, чтобы жанр, год, поиск и сортировка не сбрасывались
 * при переходе между страницами каталога.
 */
function buildCatalogUrl(array $overrides = []): string
{
    $params = [
        "search" => $_GET["search"] ?? "",
        "genre" => $_GET["genre"] ?? "",
        "sort" => $_GET["sort"] ?? "newest",
        "year_from" => $_GET["year_from"] ?? "",
        "year_to" => $_GET["year_to"] ?? ""
    ];

    $params = array_merge($params, $overrides);

    foreach ($params as $key => $value) {
        if ($value === "" || $value === null) {
            unset($params[$key]);
        }
    }

    return "catalog.php" . (!empty($params) ? "?" . http_build_query($params) : "");
}

include "includes/header.php";
?>

<style>
/* Компактная сетка фильмов */
.catalog-grid {
    display: grid !important;
    grid-template-columns: repeat(4, minmax(0, 1fr)) !important;
    gap: 20px !important;
    width: 100% !important;
    max-width: 1000px !important;
    margin: 25px auto !important;
}

.catalog-grid .movie-card {
    width: 100% !important;
    min-width: 0 !important;
    overflow: hidden !important;
}

.catalog-grid .movie-poster {
    width: 100% !important;
    height: auto !important;
    aspect-ratio: 2 / 3 !important;
    overflow: hidden !important;
}

.catalog-grid .movie-poster img {
    display: block !important;
    width: 100% !important;
    height: 100% !important;
    object-fit: cover !important;
}

.catalog-grid .movie-info {
    padding: 14px !important;
}

.catalog-grid .movie-info h3 {
    font-size: 18px !important;
}

.catalog-grid .movie-info p {
    font-size: 15px !important;
}

/* Новый фильтр по году выпуска */
.catalog-year-filter {
    display: flex;
    align-items: end;
    flex-wrap: wrap;
    gap: 14px;
    margin: 22px 0 25px;
    padding: 20px 22px;
    background: #191920;
    border: 1px solid #30303b;
    border-radius: 14px;
}

.catalog-year-filter-title {
    width: 100%;
    margin-bottom: 2px;
    color: #f5f5f7;
    font-size: 17px;
    font-weight: 700;
}

.catalog-year-field {
    display: flex;
    flex-direction: column;
    gap: 7px;
    min-width: 155px;
}

.catalog-year-field label {
    color: #aaaab5;
    font-size: 14px;
}

.catalog-year-field select {
    min-width: 155px;
    padding: 11px 13px;
    border: 1px solid #41414d;
    border-radius: 8px;
    outline: none;
    background: #111116;
    color: #f5f5f7;
    font-size: 15px;
    cursor: pointer;
}

.catalog-year-field select:focus {
    border-color: #aaa18b;
}

.catalog-year-submit,
.catalog-year-reset {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 42px;
    padding: 10px 18px;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 700;
    text-decoration: none;
    cursor: pointer;
    transition: .2s ease;
}

.catalog-year-submit {
    border: 1px solid #d1c6a8;
    background: #d1c6a8;
    color: #17171d;
}

.catalog-year-submit:hover {
    background: #e0d8c5;
    border-color: #e0d8c5;
}

.catalog-year-reset {
    border: 1px solid #41414d;
    background: transparent;
    color: #d0d0d8;
}

.catalog-year-reset:hover {
    background: #292932;
}

.catalog-active-filter {
    margin: -8px 0 18px;
    color: #aaaab5;
    font-size: 14px;
}

.catalog-active-filter strong {
    color: #e6ddc8;
}

@media (max-width: 850px) {
    .catalog-grid {
        grid-template-columns: repeat(3, minmax(0, 1fr)) !important;
    }
}

@media (max-width: 550px) {
    .catalog-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
    }

    .catalog-year-filter {
        align-items: stretch;
        flex-direction: column;
        padding: 17px;
    }

    .catalog-year-field,
    .catalog-year-field select,
    .catalog-year-submit,
    .catalog-year-reset {
        width: 100%;
        box-sizing: border-box;
    }
}


/* Переключатель: сетка / список */
.catalog-toolbar {
    flex-wrap: wrap;
}

.catalog-view-switch {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 4px;
    border: 1px solid #303039;
    border-radius: 10px;
    background: #191920;
}

.catalog-view-button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    min-width: 44px;
    height: 38px;
    padding: 0 12px;
    border: 0;
    border-radius: 7px;
    background: transparent;
    color: #a6a6b2;
    font-size: 14px;
    cursor: pointer;
    transition: .2s ease;
}

.catalog-view-button:hover {
    color: #fff;
    background: #25252d;
}

.catalog-view-button.active {
    color: #fff;
    background: #34343d;
    box-shadow: inset 0 0 0 1px #454550;
}

.catalog-view-button svg {
    width: 19px;
    height: 19px;
    fill: none;
    stroke: currentColor;
    stroke-width: 1.5;
    stroke-linecap: round;
    stroke-linejoin: round;
}

/* Режим списка */
.catalog-grid.list-view {
    display: flex !important;
    flex-direction: column;
    gap: 14px !important;
    max-width: 1000px !important;
}

.catalog-grid.list-view .movie-card {
    position: relative;
    display: flex;
    align-items: stretch;
    min-height: 165px;
    border-radius: 13px;
}

.catalog-grid.list-view .movie-card-link {
    display: flex;
    flex: 1;
    min-width: 0;
    align-items: stretch;
}

.catalog-grid.list-view .movie-poster {
    flex: 0 0 112px;
    width: 112px;
    height: 165px !important;
    aspect-ratio: auto !important;
}

.catalog-grid.list-view .movie-info {
    display: flex;
    flex: 1;
    flex-direction: column;
    justify-content: center;
    min-width: 0;
    padding: 20px 24px !important;
}

.catalog-grid.list-view .movie-info h3 {
    margin-bottom: 8px;
    font-size: 21px !important;
}

.catalog-grid.list-view .movie-info p {
    font-size: 15px !important;
}

.catalog-grid.list-view .favorite-form {
    z-index: 3;
}

.catalog-grid.list-view .poster-year {
    top: 8px;
    left: 8px;
    padding: 4px 7px;
    font-size: 11px;
}

@media (max-width: 700px) {
    .catalog-toolbar {
        align-items: stretch;
    }

    .catalog-view-switch {
        align-self: flex-start;
    }

    .catalog-grid.list-view .movie-poster {
        flex-basis: 92px;
        width: 92px;
        height: 138px !important;
    }

    .catalog-grid.list-view .movie-card {
        min-height: 138px;
    }

    .catalog-grid.list-view .movie-info {
        padding: 14px 15px !important;
    }

    .catalog-grid.list-view .movie-info h3 {
        font-size: 17px !important;
    }
}

@media (max-width: 420px) {
    .catalog-view-button .view-label {
        display: none;
    }

    .catalog-grid.list-view .movie-poster {
        flex-basis: 82px;
        width: 82px;
        height: 123px !important;
    }

    .catalog-grid.list-view .movie-card {
        min-height: 123px;
    }
}

</style>

<section class="catalog-page">

    <div class="catalog-heading">
        <span class="section-label">КОЛЛЕКЦИЯ ФИЛЬМОВ</span>
        <h2>Каталог фильмов</h2>

        <p class="catalog-description">
            Найди интересный фильм для своего настроения.
            Выбирай жанр, ищи любимые истории и открывай новое.
        </p>

        <div class="catalog-count">
            Найдено фильмов:
            <strong><?php echo $totalFilms; ?></strong>
        </div>
    </div>

    <!-- ПОИСК -->
    <form method="GET" action="catalog.php" class="catalog-controls">
        <div class="search-form">
            <input
                type="text"
                name="search"
                placeholder="Введите название фильма..."
                value="<?php echo htmlspecialchars($search, ENT_QUOTES, "UTF-8"); ?>"
            >

            <input type="hidden" name="genre" value="<?php echo htmlspecialchars($genre, ENT_QUOTES, "UTF-8"); ?>">
            <input type="hidden" name="sort" value="<?php echo htmlspecialchars($sort, ENT_QUOTES, "UTF-8"); ?>">
            <input type="hidden" name="year_from" value="<?php echo $yearFrom ?: ""; ?>">
            <input type="hidden" name="year_to" value="<?php echo $yearTo ?: ""; ?>">

            <button type="submit">Найти</button>
        </div>
    </form>

    <!-- ЖАНРЫ -->
    <div class="genre-filters">
        <a
            href="catalog.php"
            class="genre-filter <?php echo $genre === "" ? "active" : ""; ?>"
        >
            Все фильмы
        </a>

        <?php while ($g = $genresResult->fetch_assoc()): ?>
            <a
                href="<?php echo htmlspecialchars(buildCatalogUrl(["genre" => $g["genre"], "page" => 1]), ENT_QUOTES, "UTF-8"); ?>"
                class="genre-filter <?php echo $genre === $g["genre"] ? "active" : ""; ?>"
            >
                <?php echo htmlspecialchars($g["genre"], ENT_QUOTES, "UTF-8"); ?>
            </a>
        <?php endwhile; ?>
    </div>

    <!-- ФИЛЬТР ПО ГОДУ ВЫПУСКА -->
    <form method="GET" action="catalog.php" class="catalog-year-filter">
        <div class="catalog-year-filter-title">Фильтр по году выпуска</div>

        <input type="hidden" name="search" value="<?php echo htmlspecialchars($search, ENT_QUOTES, "UTF-8"); ?>">
        <input type="hidden" name="genre" value="<?php echo htmlspecialchars($genre, ENT_QUOTES, "UTF-8"); ?>">
        <input type="hidden" name="sort" value="<?php echo htmlspecialchars($sort, ENT_QUOTES, "UTF-8"); ?>">

        <div class="catalog-year-field">
            <label for="year_from">С какого года</label>
            <select name="year_from" id="year_from">
                <option value="">Любой год</option>
                <?php for ($year = $maxAvailableYear; $year >= $minAvailableYear; $year--): ?>
                    <option value="<?php echo $year; ?>" <?php echo $yearFrom === $year ? "selected" : ""; ?>>
                        <?php echo $year; ?>
                    </option>
                <?php endfor; ?>
            </select>
        </div>

        <div class="catalog-year-field">
            <label for="year_to">По какой год</label>
            <select name="year_to" id="year_to">
                <option value="">Любой год</option>
                <?php for ($year = $maxAvailableYear; $year >= $minAvailableYear; $year--): ?>
                    <option value="<?php echo $year; ?>" <?php echo $yearTo === $year ? "selected" : ""; ?>>
                        <?php echo $year; ?>
                    </option>
                <?php endfor; ?>
            </select>
        </div>

        <button type="submit" class="catalog-year-submit">Применить</button>
        <a href="<?php echo htmlspecialchars(buildCatalogUrl(["year_from" => "", "year_to" => "", "page" => 1]), ENT_QUOTES, "UTF-8"); ?>" class="catalog-year-reset">Сбросить годы</a>
    </form>

    <?php if ($yearFrom > 0 || $yearTo > 0): ?>
        <p class="catalog-active-filter">
            Выбран период:
            <strong>
                <?php echo $yearFrom > 0 ? $yearFrom : "с начала"; ?> —
                <?php echo $yearTo > 0 ? $yearTo : "по настоящее время"; ?>
            </strong>
        </p>
    <?php endif; ?>

    <!-- СОРТИРОВКА -->
    <div class="catalog-toolbar">
        <span>Найдено фильмов: <?php echo $totalFilms; ?></span>

        <form method="GET" action="catalog.php">
            <input type="hidden" name="search" value="<?php echo htmlspecialchars($search, ENT_QUOTES, "UTF-8"); ?>">
            <input type="hidden" name="genre" value="<?php echo htmlspecialchars($genre, ENT_QUOTES, "UTF-8"); ?>">
            <input type="hidden" name="year_from" value="<?php echo $yearFrom ?: ""; ?>">
            <input type="hidden" name="year_to" value="<?php echo $yearTo ?: ""; ?>">

            <label for="sort">Сортировка:</label>
            <select name="sort" id="sort" onchange="this.form.submit()">
                <option value="newest" <?php echo $sort === "newest" ? "selected" : ""; ?>>Сначала новые</option>
                <option value="oldest" <?php echo $sort === "oldest" ? "selected" : ""; ?>>Сначала старые</option>
                <option value="name" <?php echo $sort === "name" ? "selected" : ""; ?>>По названию</option>
            </select>
        </form>

        <div class="catalog-view-switch" role="group" aria-label="Вид отображения фильмов">
            <button type="button" class="catalog-view-button" data-view="grid" aria-label="Показать сеткой" aria-pressed="false" title="Сетка">
                <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="3" width="8" height="8" rx="1.5"/><rect x="13" y="3" width="8" height="8" rx="1.5"/><rect x="3" y="13" width="8" height="8" rx="1.5"/><rect x="13" y="13" width="8" height="8" rx="1.5"/></svg>
                <span class="view-label">Сетка</span>
            </button>
            <button type="button" class="catalog-view-button" data-view="list" aria-label="Показать списком" aria-pressed="false" title="Список">
                <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="3" width="18" height="14" rx="1.5"/><path d="M2 20h20M5 23h14"/></svg>
                <span class="view-label">Список</span>
            </button>
        </div>
    </div>

    <!-- КАРТОЧКИ ФИЛЬМОВ -->
    <div class="catalog-grid">
        <?php if ($result->num_rows > 0): ?>
            <?php while ($film = $result->fetch_assoc()): ?>
                <?php
                $filmId = (int)$film["id"];
                $isFavorite = in_array($filmId, $favoriteIds, true);
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

                <div class="movie-card">
                    <!-- СЕРДЕЧКО ИЗБРАННОГО ОСТАЁТСЯ БЕЗ ИЗМЕНЕНИЙ -->
                    <form method="POST" action="favorite.php" class="favorite-form">
                        <input type="hidden" name="film_id" value="<?php echo $filmId; ?>">
                        <input type="hidden" name="return" value="<?php echo htmlspecialchars(buildCatalogUrl(), ENT_QUOTES, "UTF-8"); ?>">

                        <button
                            type="submit"
                            class="favorite-button <?php echo $isFavorite ? "active" : ""; ?>"
                            title="<?php echo $isFavorite ? "Удалить из избранного" : "Добавить в избранное"; ?>"
                        >
                            <?php echo $isFavorite ? "♥" : "♡"; ?>
                        </button>
                    </form>

                    <a href="film.php?id=<?php echo $filmId; ?>" class="movie-card-link">
                        <div class="movie-poster">
                            <?php if ($posterExists): ?>
                                <img
                                    src="<?php echo htmlspecialchars($posterPath, ENT_QUOTES, "UTF-8"); ?>"
                                    alt="<?php echo htmlspecialchars($film["title"], ENT_QUOTES, "UTF-8"); ?>"
                                    loading="lazy"
                                >
                            <?php else: ?>
                                <span class="poster-icon">▶</span>
                            <?php endif; ?>

                            <span class="poster-year"><?php echo (int)$film["year"]; ?></span>
                        </div>

                        <div class="movie-info">
                            <h3><?php echo htmlspecialchars($film["title"], ENT_QUOTES, "UTF-8"); ?></h3>
                            <p><?php echo htmlspecialchars($film["genre"], ENT_QUOTES, "UTF-8"); ?></p>
                        </div>
                    </a>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="empty-message">
                <h3>Фильмы не найдены</h3>
                <p>Попробуйте изменить поисковый запрос или выбрать другой год выпуска.</p>
                <a href="catalog.php" class="btn-primary">Сбросить фильтры</a>
            </div>
        <?php endif; ?>
    </div>

    <script>
    (function () {
        const grid = document.querySelector('.catalog-grid');
        const buttons = document.querySelectorAll('.catalog-view-button');
        if (!grid || !buttons.length) return;

        function setCatalogView(view) {
            const isList = view === 'list';
            grid.classList.toggle('list-view', isList);

            buttons.forEach(function (button) {
                const active = button.dataset.view === view;
                button.classList.toggle('active', active);
                button.setAttribute('aria-pressed', active ? 'true' : 'false');
            });

            try {
                localStorage.setItem('kinomirCatalogView', view);
            } catch (error) {
                // Если хранилище браузера недоступно, переключение всё равно работает.
            }
        }

        buttons.forEach(function (button) {
            button.addEventListener('click', function () {
                setCatalogView(button.dataset.view);
            });
        });

        let savedView = 'grid';
        try {
            savedView = localStorage.getItem('kinomirCatalogView') || 'grid';
        } catch (error) {
            savedView = 'grid';
        }
        setCatalogView(savedView === 'list' ? 'list' : 'grid');
    })();
    </script>

    <!-- ПАГИНАЦИЯ: сохраняет выбранные фильтры -->
    <?php if ($totalPages > 1): ?>
        <div class="pagination">
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <a
                    href="<?php echo htmlspecialchars(buildCatalogUrl(["page" => $i]), ENT_QUOTES, "UTF-8"); ?>"
                    class="<?php echo $page === $i ? "active" : ""; ?>"
                >
                    <?php echo $i; ?>
                </a>
            <?php endfor; ?>
        </div>
    <?php endif; ?>

</section>

<?php
$stmt->close();
include "includes/footer.php";
?>
