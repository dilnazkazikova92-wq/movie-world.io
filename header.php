<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$isLoggedIn = isset($_SESSION['user_id']);
$userRole = $_SESSION['user_role'] ?? '';
$userLogin = $_SESSION['user_login'] ?? '';
$currentPage = basename(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '');

$navItems = [
    ['label' => 'Главная', 'href' => '/movie_catalog/index.php', 'pages' => ['index.php']],
    ['label' => 'Каталог', 'href' => '/movie_catalog/catalog.php', 'pages' => ['catalog.php', 'film.php']],
    ['label' => 'Жанры', 'href' => '/movie_catalog/genres.php', 'pages' => ['genres.php']],
    ['label' => 'О проекте', 'href' => '/movie_catalog/about.php', 'pages' => ['about.php']],
    ['label' => 'Контакты', 'href' => '/movie_catalog/contacts.php', 'pages' => ['contacts.php']],
];
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>КиноМир</title>
    <link rel="stylesheet" href="/movie_catalog/css/style.css?v=19">

    <style>
        /* ======================================
           ШАПКА КИНОМИР — ЦЕНТРАЛЬНАЯ НАВИГАЦИЯ
        ====================================== */
        .site-header {
            position: sticky;
            top: 0;
            z-index: 1000;
            width: 100%;
            background: rgba(17, 17, 22, .94);
            border-bottom: 1px solid rgba(255, 204, 77, .13);
            box-shadow: 0 8px 28px rgba(0, 0, 0, .20);
            backdrop-filter: blur(18px);
            -webkit-backdrop-filter: blur(18px);
        }

        .km-header-inner {
            width: min(1540px, calc(100% - 80px));
            min-height: 100px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: 190px max-content 270px auto;
            align-items: center;
            justify-content: space-between;
            column-gap: clamp(30px, 3vw, 52px);
            row-gap: 0;
        }

        .km-brand {
            display: inline-flex;
            align-items: center;
            justify-self: start;
            gap: 16px;
            color: #fff;
            text-decoration: none;
            white-space: nowrap;
        }

        .km-brand-mark {
            width: 47px;
            height: 47px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 1px solid rgba(255, 204, 77, .55);
            border-radius: 13px;
            background: linear-gradient(145deg, rgba(255, 204, 77, .20), rgba(255, 204, 77, .06));
            color: #ffcc4d;
            font-size: 19px;
            box-shadow: 0 0 22px rgba(255, 204, 77, .09);
        }

        .km-brand-name {
            display: block;
            color: #fff;
            font-size: 27px;
            font-weight: 800;
            letter-spacing: .2px;
            line-height: 1.05;
        }

        .km-brand-caption {
            display: block;
            margin-top: 5px;
            color: #a7a7b2;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 2.1px;
            text-transform: uppercase;
        }

        .km-nav {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            padding: 9px 11px;
            border: 1px solid rgba(255, 255, 255, .07);
            border-radius: 999px;
            background: rgba(255, 255, 255, .035);
        }

        .km-nav-link {
            position: relative;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 46px;
            padding: 0 21px;
            border-radius: 999px;
            color: #c7c7d0;
            font-size: 15px;
            font-weight: 600;
            text-decoration: none;
            white-space: nowrap;
            transition: color .2s ease, background .2s ease, transform .2s ease;
        }

        .km-nav-link:hover {
            color: #fff;
            background: rgba(255, 255, 255, .07);
        }

        /* Текущая страница выделена спокойно: без яркой жёлтой заливки */
        .km-nav-link.active {
            color: #fff;
            background: rgba(255, 255, 255, .10);
            border: 1px solid rgba(255, 255, 255, .10);
            box-shadow: none;
        }

        .km-nav-link.active::after {
            content: "";
            position: absolute;
            left: 50%;
            bottom: 5px;
            width: 15px;
            height: 2px;
            border-radius: 2px;
            background: #c7ad75;
            transform: translateX(-50%);
        }

        .km-nav-link.active:hover {
            color: #fff;
            background: rgba(255, 255, 255, .13);
        }

        .km-header-actions {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 10px;
        }

        .km-account-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            min-height: 44px;
            padding: 0 17px;
            border: 1px solid rgba(255, 204, 77, .35);
            border-radius: 999px;
            color: #ffcc4d;
            background: rgba(255, 204, 77, .07);
            font-size: 14px;
            font-weight: 700;
            text-decoration: none;
            white-space: nowrap;
            transition: .2s ease;
        }

        .km-account-link:hover {
            color: #17171d;
            background: #ffcc4d;
            border-color: #ffcc4d;
        }

        .km-login-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 46px;
            padding: 0 22px;
            border-radius: 999px;
            color: #17171d;
            background: #ffcc4d;
            font-size: 14px;
            font-weight: 800;
            text-decoration: none;
            white-space: nowrap;
            transition: .2s ease;
        }

        .km-login-link:hover {
            background: #ffdc7b;
            transform: translateY(-1px);
        }

        /* Поиск в шапке */
        .km-header-search {
            display: flex;
            align-items: center;
            width: 100%;
            max-width: 270px;
            min-width: 0;
            height: 50px;
            padding: 0 5px 0 13px;
            border: 1px solid rgba(255, 255, 255, .10);
            border-radius: 999px;
            background: rgba(255, 255, 255, .045);
            transition: border-color .2s ease, background .2s ease, box-shadow .2s ease;
        }

        .km-header-search:focus-within {
            border-color: rgba(255, 204, 77, .65);
            background: rgba(255, 255, 255, .07);
            box-shadow: 0 0 0 3px rgba(255, 204, 77, .08);
        }

        .km-search-icon {
            display: inline-flex;
            flex: 0 0 auto;
            width: 18px;
            height: 18px;
            margin-right: 9px;
            color: #a9a9b4;
        }

        .km-search-icon svg,
        .km-header-search button svg {
            width: 100%;
            height: 100%;
            fill: none;
            stroke: currentColor;
            stroke-width: 1.8;
            stroke-linecap: round;
            stroke-linejoin: round;
        }

        .km-header-search input {
            width: 100%;
            min-width: 0;
            height: 100%;
            padding: 0;
            border: 0;
            outline: 0;
            background: transparent;
            color: #f5f5f5;
            font: inherit;
            font-size: 13px;
        }

        .km-header-search input::placeholder {
            color: #92929e;
        }

        .km-header-search input::-webkit-search-cancel-button {
            cursor: pointer;
        }

        .km-header-search button {
            display: inline-flex;
            flex: 0 0 auto;
            align-items: center;
            justify-content: center;
            width: 34px;
            height: 34px;
            margin-left: 7px;
            border: 0;
            border-radius: 50%;
            background: rgba(255, 204, 77, .13);
            color: #ffcc4d;
            cursor: pointer;
            transition: .2s ease;
        }

        .km-header-search button:hover {
            background: #ffcc4d;
            color: #17171d;
        }

        .km-header-search button svg {
            width: 16px;
            height: 16px;
        }

        .km-mobile-search {
            display: none;
        }

        .km-mobile-toggle {
            display: none;
            width: 45px;
            height: 45px;
            align-items: center;
            justify-content: center;
            border: 1px solid rgba(255, 204, 77, .35);
            border-radius: 13px;
            background: rgba(255, 204, 77, .08);
            color: #ffcc4d;
            font-size: 25px;
            cursor: pointer;
        }

        @media (max-width: 1180px) {
            .km-header-inner {
                width: min(100% - 36px, 1500px);
                grid-template-columns: 175px max-content minmax(230px, 270px) auto;
                column-gap: 22px;
            }

            .km-nav-link {
                padding: 0 10px;
                font-size: 13px;
            }

            .km-header-search {
                max-width: 270px;
            }

            .km-brand-name {
                font-size: 23px;
            }
        }

        @media (max-width: 940px) {
            .km-header-inner {
                position: relative;
                min-height: 76px;
                width: calc(100% - 34px);
                grid-template-columns: 1fr auto;
                gap: 12px;
            }

            .km-mobile-toggle {
                display: inline-flex;
            }

            .km-header-search {
                display: none;
            }

            .km-nav {
                position: absolute;
                top: calc(100% - 1px);
                left: 0;
                right: 0;
                display: none;
                align-items: stretch;
                flex-direction: column;
                gap: 5px;
                padding: 12px;
                border: 1px solid rgba(255, 204, 77, .20);
                border-radius: 0 0 18px 18px;
                background: #191920;
                box-shadow: 0 18px 30px rgba(0, 0, 0, .35);
            }

            .km-nav.is-open {
                display: flex;
            }

            .km-mobile-search {
                display: flex;
                align-items: center;
                gap: 8px;
                width: 100%;
                min-height: 46px;
                margin-bottom: 5px;
                padding: 5px 6px 5px 14px;
                border: 1px solid rgba(255, 204, 77, .25);
                border-radius: 12px;
                background: rgba(255, 255, 255, .045);
            }

            .km-mobile-search input {
                flex: 1;
                min-width: 0;
                height: 34px;
                border: 0;
                outline: 0;
                background: transparent;
                color: #fff;
                font: inherit;
                font-size: 14px;
            }

            .km-mobile-search input::placeholder {
                color: #92929e;
            }

            .km-mobile-search button {
                width: 36px;
                height: 36px;
                border: 0;
                border-radius: 9px;
                background: #ffcc4d;
                color: #17171d;
                font-size: 23px;
                line-height: 1;
                cursor: pointer;
            }

            .km-nav-link {
                justify-content: flex-start;
                min-height: 46px;
                padding: 0 17px;
                border-radius: 10px;
                font-size: 15px;
            }

            .km-header-actions {
                display: none;
            }

            .km-nav-mobile-account {
                display: flex;
                margin-top: 5px;
                padding-top: 10px;
                border-top: 1px solid rgba(255, 255, 255, .09);
            }
        }

        @media (min-width: 941px) {
            .km-nav-mobile-account {
                display: none;
            }
        }

        @media (max-width: 480px) {
            .km-header-inner {
                min-height: 70px;
                width: calc(100% - 28px);
            }

            .km-brand-mark {
                width: 39px;
                height: 39px;
                border-radius: 11px;
            }

            .km-brand-name {
                font-size: 21px;
            }

            .km-brand-caption {
                font-size: 9px;
                letter-spacing: 1.6px;
            }
        }
    </style>
</head>
<body>

<header class="site-header">
    <div class="km-header-inner">

        <a href="/movie_catalog/index.php" class="km-brand" aria-label="КиноМир — главная">
            <span class="km-brand-mark" aria-hidden="true">▶</span>
            <span>
                <span class="km-brand-name">КиноМир</span>
                <span class="km-brand-caption">Твой мир кино</span>
            </span>
        </a>

        <nav class="km-nav" id="kmNavigation" aria-label="Основная навигация">
            <form class="km-mobile-search" action="/movie_catalog/catalog.php" method="GET" role="search">
                <input
                    type="search"
                    name="search"
                    placeholder="Найти фильм..."
                    aria-label="Поиск фильмов"
                    value="<?php echo htmlspecialchars($_GET['search'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                >
                <button type="submit" aria-label="Найти">⌕</button>
            </form>

            <?php foreach ($navItems as $item): ?>
                <?php $isActive = in_array($currentPage, $item['pages'], true); ?>
                <a
                    href="<?php echo htmlspecialchars($item['href'], ENT_QUOTES, 'UTF-8'); ?>"
                    class="km-nav-link<?php echo $isActive ? ' active' : ''; ?>"
                    <?php echo $isActive ? 'aria-current="page"' : ''; ?>
                >
                    <?php echo htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8'); ?>
                </a>
            <?php endforeach; ?>

            <div class="km-nav-mobile-account">
                <?php if ($isLoggedIn && $userRole === 'admin'): ?>
                    <a href="/movie_catalog/admin/index.php" class="km-nav-link">Панель администратора</a>
                <?php elseif ($isLoggedIn && $userRole === 'user'): ?>
                    <a href="/movie_catalog/account.php" class="km-nav-link">Личный кабинет</a>
                <?php else: ?>
                    <a href="/movie_catalog/login.php" class="km-nav-link">Войти в аккаунт</a>
                <?php endif; ?>
            </div>
        </nav>

        <form class="km-header-search" action="/movie_catalog/catalog.php" method="GET" role="search">
            <span class="km-search-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" focusable="false">
                    <circle cx="10.8" cy="10.8" r="6.8"></circle>
                    <path d="m16 16 5 5"></path>
                </svg>
            </span>
            <input
                type="search"
                name="search"
                placeholder="Найти фильм..."
                aria-label="Поиск фильмов"
                value="<?php echo htmlspecialchars($_GET['search'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
            >
            <button type="submit" aria-label="Найти">
                <svg viewBox="0 0 24 24" focusable="false">
                    <path d="M5 12h14M13 6l6 6-6 6"></path>
                </svg>
            </button>
        </form>

        <div class="km-header-actions">
            <?php if ($isLoggedIn && $userRole === 'admin'): ?>
                <a href="/movie_catalog/admin/index.php" class="km-account-link">⚙ Админ-панель</a>
            <?php elseif ($isLoggedIn && $userRole === 'user'): ?>
                <a href="/movie_catalog/account.php" class="km-account-link">♙ Личный кабинет</a>
            <?php else: ?>
                <a href="/movie_catalog/login.php" class="km-login-link">Войти</a>
            <?php endif; ?>
        </div>

        <button
            class="km-mobile-toggle"
            id="kmMobileToggle"
            type="button"
            aria-label="Открыть меню"
            aria-controls="kmNavigation"
            aria-expanded="false"
        >
            <span aria-hidden="true">☰</span>
        </button>

    </div>
</header>

<main>

<script>
(function () {
    const toggle = document.getElementById('kmMobileToggle');
    const navigation = document.getElementById('kmNavigation');

    if (!toggle || !navigation) return;

    toggle.addEventListener('click', function () {
        const isOpen = navigation.classList.toggle('is-open');
        toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        toggle.setAttribute('aria-label', isOpen ? 'Закрыть меню' : 'Открыть меню');
        toggle.querySelector('span').textContent = isOpen ? '×' : '☰';
    });

    navigation.addEventListener('click', function (event) {
        if (event.target.closest('a')) {
            navigation.classList.remove('is-open');
            toggle.setAttribute('aria-expanded', 'false');
            toggle.setAttribute('aria-label', 'Открыть меню');
            toggle.querySelector('span').textContent = '☰';
        }
    });
})();
</script>
