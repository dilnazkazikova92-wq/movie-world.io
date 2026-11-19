
<?php include "includes/header.php"; ?>

<style>

/* ==========================================
   СТРАНИЦА «О ПРОЕКТЕ»
========================================== */

.about-page {
    width: min(1380px, calc(100% - 64px));
    margin: 42px auto 84px;
}

/* ==========================================
   ГЛАВНЫЙ БАННЕР
========================================== */

.about-hero {
    position: relative;
    isolation: isolate;
    display: flex;
    align-items: center;

    min-height: 440px;
    padding: clamp(30px, 3.5vw, 52px);

    overflow: hidden;
    border: 1px solid rgba(210, 174, 82, 0.16);
    border-radius: 28px;

    background:
        radial-gradient(
            ellipse at 78% 48%,
            rgba(105, 49, 151, 0.19),
            transparent 48%
        ),
        linear-gradient(
            125deg,
            #1d1a2b 0%,
            #17151e 48%,
            #111116 100%
        );

    box-shadow: 0 24px 65px rgba(0, 0, 0, 0.23);
}

/* Фоновое свечение */

.about-hero::before {
    content: "";
    position: absolute;
    z-index: -1;

    width: 480px;
    height: 480px;

    right: 8%;
    top: 50%;

    transform: translateY(-50%);
    border-radius: 50%;

    background: rgba(126, 67, 180, 0.13);
    filter: blur(95px);

    pointer-events: none;
}

/* ==========================================
   ТЕКСТ БАННЕРА
========================================== */

.about-hero-content {
    position: relative;
    z-index: 4;

    width: 66%;
    min-width: 0;
    max-width: 850px;
}

.about-hero-content .section-label {
    display: inline-flex;
    align-items: center;
    gap: 13px;

    margin-bottom: 20px;

    color: #ffcc4d;
    font-size: 14px !important;
    font-weight: 800;
    letter-spacing: 3px;
}

.about-hero-content .section-label::before {
    content: "";

    width: 42px;
    height: 2px;

    background: #ffcc4d;
    box-shadow: 0 0 12px rgba(255, 204, 77, 0.4);
}

.about-hero-content h2 {
    max-width: 650px;
    margin: 0 0 20px;

    color: #f7f6fb;

    font-size: clamp(36px, 3.7vw, 55px) !important;
    font-weight: 800;
    line-height: 1.12 !important;
    letter-spacing: -1.5px;
}

.about-hero-content h2 span {
    color: #ffcc4d;
}

.about-hero-content p {
    max-width: 720px;
    margin: 0 0 25px;

    color: #c8c5d2;
    font-size: 18px !important;
    line-height: 1.7 !important;
}

/* Кнопка */

.about-hero-content .btn-primary {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 12px;

    min-height: 54px;
    padding: 0 27px;

    border: none;
    border-radius: 15px;

    background: linear-gradient(135deg, #ffda70, #ffbd35);
    color: #17130b;

    font-size: 16px !important;
    font-weight: 800;

    box-shadow: 0 10px 27px rgba(255, 190, 53, 0.17);

    transition: transform .25s ease, box-shadow .25s ease;
}

.about-hero-content .btn-primary:hover {
    transform: translateY(-3px);
    box-shadow: 0 14px 32px rgba(255, 190, 53, 0.27);
}

.about-hero-content .btn-primary::after {
    content: "→";
    font-size: 21px;
}

/* Подписи */

.about-hero-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 9px;
    margin-top: 23px;
}

.about-hero-tags span {
    display: inline-flex;
    align-items: center;
    gap: 7px;

    padding: 8px 12px;

    border: 1px solid rgba(255, 255, 255, 0.09);
    border-radius: 999px;

    background: rgba(255, 255, 255, 0.045);
    color: #d8d5df;

    font-size: 12px;
    font-weight: 600;
}

.about-hero-tags span::before {
    content: "✦";
    color: #ffcc4d;
    font-size: 11px;
}

/* ==========================================
   ВЕРТИКАЛЬНАЯ КИНОПЛЁНКА
========================================== */

.about-film-art {
    position: absolute;
    z-index: 2;

    width: 320px;
    height: 440px;

    right: 7%;
    top: 50%;

    transform: translateY(-50%);

    display: flex;
    align-items: center;
    justify-content: center;

    pointer-events: none;
}

/* Свечение позади плёнки */

.about-film-glow {
    position: absolute;

    width: 280px;
    height: 340px;

    border-radius: 50%;

    background: rgba(126, 67, 180, 0.25);
    filter: blur(85px);

    animation: filmGlow 6s ease-in-out infinite alternate;
}

/* Корпус киноплёнки */

.about-film {
    position: relative;

    width: 230px;
    height: 390px;

    padding: 19px 29px;

    display: flex;
    flex-direction: column;
    justify-content: space-between;
    gap: 13px;

    border: 1px solid rgba(255, 204, 77, 0.42);
    border-radius: 13px;

    background:
        linear-gradient(
            110deg,
            rgba(48, 31, 65, 0.98),
            rgba(103, 58, 132, 0.96) 48%,
            rgba(48, 31, 65, 0.98)
        );

    box-shadow:
        0 0 30px rgba(133, 75, 177, 0.22),
        0 0 65px rgba(133, 75, 177, 0.13),
        inset 0 0 25px rgba(255, 255, 255, 0.04);

    transform: rotate(-9deg);

    animation: filmFloat 6s ease-in-out infinite;
}

/* Отверстия по бокам */

.about-film::before,
.about-film::after {
    content: "";

    position: absolute;
    top: 12px;
    bottom: 12px;

    width: 13px;

    background:
        repeating-linear-gradient(
            to bottom,
            #17141e 0 13px,
            transparent 13px 30px
        );

    pointer-events: none;
}

.about-film::before {
    left: 8px;
}

.about-film::after {
    right: 8px;
}

/* Кадры */

.about-film-frame {
    position: relative;

    width: 100%;
    height: 100%;

    min-height: 0;

    overflow: hidden;

    border: 1px solid rgba(255, 204, 77, 0.24);
    border-radius: 5px;

    background: linear-gradient(
        145deg,
        #403052,
        #211b2d
    );

    box-shadow: inset 0 0 18px rgba(0, 0, 0, 0.22);
}

/* Первый кадр — горный пейзаж */

.about-film-frame:nth-child(1) {
    background:
        radial-gradient(
            circle at 72% 28%,
            rgba(255, 204, 77, 0.65) 0 8px,
            transparent 9px
        ),
        linear-gradient(
            145deg,
            #61467d,
            #292036
        );
}

.about-film-frame:nth-child(1)::before {
    content: "";

    position: absolute;
    bottom: 0;
    left: 0;

    width: 100%;
    height: 70%;

    background: linear-gradient(
        145deg,
        transparent 0 30%,
        #211b30 31% 55%,
        #39284b 56% 100%
    );

    clip-path: polygon(
        0 75%,
        24% 25%,
        43% 58%,
        65% 8%,
        100% 65%,
        100% 100%,
        0 100%
    );
}

/* Второй кадр — силуэт города */

.about-film-frame:nth-child(2) {
    background:
        linear-gradient(
            180deg,
            #503968,
            #211a30
        );
}

.about-film-frame:nth-child(2)::before {
    content: "";

    position: absolute;
    bottom: 0;
    left: 0;

    width: 100%;
    height: 75%;

    background:
        linear-gradient(
            90deg,
            #211a30 0 15%,
            transparent 15% 18%,
            #2c203a 18% 37%,
            transparent 37% 41%,
            #211a30 41% 65%,
            transparent 65% 69%,
            #30213e 69% 100%
        );

    clip-path: polygon(
        0 35%,
        12% 35%,
        12% 10%,
        26% 10%,
        26% 45%,
        40% 45%,
        40% 0,
        55% 0,
        55% 40%,
        72% 40%,
        72% 15%,
        87% 15%,
        87% 30%,
        100% 30%,
        100% 100%,
        0 100%
    );
}

/* Третий кадр — свет в кинозале */

.about-film-frame:nth-child(3) {
    background:
        radial-gradient(
            ellipse at 50% 40%,
            rgba(255, 204, 77, 0.5),
            rgba(155, 91, 174, 0.22) 25%,
            transparent 65%
        ),
        linear-gradient(
            135deg,
            #392746,
            #191522
        );
}

.about-film-frame:nth-child(3)::before {
    content: "";

    position: absolute;

    width: 70%;
    height: 70%;

    left: 15%;
    top: 20%;

    border-radius: 50%;

    background: rgba(255, 204, 77, 0.12);
    filter: blur(18px);
}

/* Световая линия на плёнке */

.about-film-shine {
    position: absolute;
    z-index: 3;

    top: -30%;
    left: -70%;

    width: 45%;
    height: 160%;

    transform: rotate(18deg);

    background: linear-gradient(
        90deg,
        transparent,
        rgba(255, 255, 255, 0.18),
        transparent
    );

    filter: blur(8px);

    animation: filmShine 8s ease-in-out infinite;
}

/* Маленькие декоративные огоньки */

.about-film-star {
    position: absolute;

    width: 7px;
    height: 7px;

    border-radius: 50%;

    background: #ffcc4d;
    box-shadow: 0 0 15px rgba(255, 204, 77, 0.8);

    animation: filmStar 4s ease-in-out infinite alternate;
}

.about-film-star.one {
    top: 17%;
    right: 8%;
}

.about-film-star.two {
    bottom: 17%;
    left: 12%;
    width: 5px;
    height: 5px;
    animation-delay: 1.5s;
}

.about-film-star.three {
    top: 52%;
    right: 1%;
    width: 6px;
    height: 6px;
    animation-delay: 2s;
}

/* Общий световой блик */

.about-light-sweep {
    position: absolute;
    z-index: 3;

    top: -20%;
    left: 48%;

    width: 18%;
    height: 140%;

    transform: skewX(-19deg);

    background: linear-gradient(
        90deg,
        transparent,
        rgba(255, 255, 255, 0.018) 25%,
        rgba(255, 218, 135, 0.10) 50%,
        rgba(255, 255, 255, 0.025) 75%,
        transparent
    );

    filter: blur(7px);
    pointer-events: none;

    animation: aboutLightSweep 10s ease-in-out infinite;
    animation-delay: 1.5s;
}

/* ==========================================
   АНИМАЦИИ
========================================== */

@keyframes filmFloat {
    0%, 100% {
        transform: rotate(-9deg) translateY(0);
    }

    50% {
        transform: rotate(-6deg) translateY(-12px);
    }
}

@keyframes filmGlow {
    from {
        opacity: 0.55;
        transform: scale(0.9);
    }

    to {
        opacity: 1;
        transform: scale(1.12);
    }
}

@keyframes filmShine {
    0%, 20% {
        left: -70%;
        opacity: 0;
    }

    35% {
        opacity: 0.8;
    }

    65% {
        left: 130%;
        opacity: 0.35;
    }

    66%, 100% {
        left: 130%;
        opacity: 0;
    }
}

@keyframes filmStar {
    from {
        opacity: 0.35;
        transform: scale(0.7);
    }

    to {
        opacity: 1;
        transform: scale(1.3);
    }
}

@keyframes aboutLightSweep {
    0%, 18% {
        left: 48%;
        opacity: 0;
    }

    28% {
        opacity: 0.8;
    }

    58% {
        left: 85%;
        opacity: 0.45;
    }

    59%, 100% {
        left: 85%;
        opacity: 0;
    }
}

/* ==========================================
   АДАПТАЦИЯ БАННЕРА
========================================== */

@media (max-width: 1050px) {

    .about-page {
        width: min(100% - 40px, 900px);
    }

    .about-hero {
        min-height: 420px;
        padding: 36px;
    }

    .about-hero-content {
        width: 75%;
        max-width: 700px;
    }

    .about-film-art {
        right: -3%;
        transform: translateY(-50%) scale(0.85);
        transform-origin: center right;
        opacity: 0.8;
    }
}

@media (max-width: 700px) {

    .about-hero {
        min-height: auto;
        padding: 30px 24px;
    }

    .about-hero-content {
        width: 100%;
        max-width: none;
    }

    .about-film-art {
        right: -125px;
        top: 25%;
        transform: translateY(-50%) scale(0.8);
        opacity: 0.16;
    }

    .about-hero-content h2 {
        max-width: 500px;
    }

    .about-hero-content p {
        max-width: 100%;
    }
}

@media (max-width: 600px) {

    .about-page {
        width: calc(100% - 28px);
        margin-top: 25px;
    }

    .about-hero {
        gap: 22px;
        min-height: auto;
        padding: 25px 20px 20px;
        border-radius: 21px;
    }

    .about-hero-content .section-label {
        font-size: 12px !important;
        letter-spacing: 2px;
    }

    .about-hero-content h2 {
        font-size: clamp(32px, 9vw, 44px) !important;
        letter-spacing: -1px;
    }

    .about-hero-content p {
        font-size: 16px !important;
        line-height: 1.65 !important;
    }

    .about-hero-content .btn-primary {
        width: 100%;
        min-height: 52px;
    }

    .about-hero-tags {
        gap: 7px;
        margin-top: 18px;
    }

    .about-hero-tags span {
        padding: 7px 9px;
        font-size: 10px;
    }
}

/* Отключение анимации при соответствующей настройке устройства */

@media (prefers-reduced-motion: reduce) {
    .about-film,
    .about-film-glow,
    .about-film-shine,
    .about-film-star,
    .about-light-sweep {
        animation: none !important;
    }
}

/* ==========================================
   МИНИ-КАРТОЧКИ
========================================== */

.about-mini-card h3 {
    font-size: 20px !important;
}

.about-mini-card p {
    font-size: 16px !important;
}

.about-mini-card .mini-number {
    font-size: 17px !important;
}

/* ==========================================
   РАЗДЕЛ «О ПРОЕКТЕ»
========================================== */

.about-intro .section-label {
    font-size: 16px !important;
}

.about-intro h2 {
    font-size: 35px !important;
}

.about-intro p {
    font-size: 20px !important;
    line-height: 1.9 !important;
}

/* ==========================================
   ВОЗМОЖНОСТИ САЙТА
========================================== */

.about-features .section-label {
    font-size: 16px !important;
}

.about-features .section-heading h2 {
    font-size: 35px !important;
}

.feature-card h3 {
    font-size: 22px !important;
}

.feature-card p {
    font-size: 17px !important;
    line-height: 1.75 !important;
}

/* ==========================================
   НАША ЦЕЛЬ
========================================== */

.mission-content .section-label {
    font-size: 16px !important;
}

.mission-content h2 {
    font-size: 35px !important;
    line-height: 1.4 !important;
}

.mission-content p {
    font-size: 19px !important;
    line-height: 1.85 !important;
}

.mission-symbol span {
    font-size: 85px !important;
}

/* ==========================================
   ЗАКЛЮЧИТЕЛЬНЫЙ БЛОК
========================================== */

.about-bottom h2 {
    font-size: 33px !important;
}

.about-bottom p {
    font-size: 19px !important;
    line-height: 1.8 !important;
}

.about-bottom .btn-primary {
    font-size: 17px !important;
}

</style>


<section class="about-page">

    <!-- ГЛАВНЫЙ БАННЕР -->

    <div class="about-hero">

        <div class="about-hero-content">

            <span class="section-label">О ПРОЕКТЕ</span>

            <h2>
                Кино — это больше,<br>
                чем просто <span>просмотр</span>
            </h2>

            <p>
                КиноМир — пространство для тех, кто любит хорошие истории.
                Здесь можно открывать новые фильмы, выбирать жанры по настроению
                и собирать собственную коллекцию впечатлений.
            </p>

            <a href="catalog.php" class="btn-primary">
                Перейти в каталог
            </a>

            <div class="about-hero-tags">
                <span>Поиск фильмов</span>
                <span>Любимые жанры</span>
                <span>Личная коллекция</span>
            </div>

        </div>


        <!-- ВЕРТИКАЛЬНАЯ КИНОПЛЁНКА -->

        <div class="about-film-art" aria-hidden="true">

            <div class="about-film-glow"></div>

            <div class="about-film">

                <div class="about-film-frame"></div>

                <div class="about-film-frame"></div>

                <div class="about-film-frame"></div>

                <span class="about-film-shine"></span>

            </div>

            <span class="about-film-star one"></span>
            <span class="about-film-star two"></span>
            <span class="about-film-star three"></span>

        </div>


        <span class="about-light-sweep" aria-hidden="true"></span>

    </div>


    <!-- О ПРОЕКТЕ -->

    <div class="about-intro">

        <span class="section-label">О ПРОЕКТЕ</span>

        <h2>Что такое КиноМир?</h2>

        <p>
            КиноМир — это информационный сайт-каталог,
            созданный для удобного поиска и просмотра
            информации о фильмах разных жанров.

            На сайте собрана коллекция кинолент, среди которых
            можно найти как известные мировые произведения,
            так и фильмы для разных настроений и предпочтений.
        </p>

        <p>
            Главная задача проекта — сделать поиск фильмов
            простым и удобным. Пользователь может ознакомиться
            с описанием картины, узнать её год выпуска,
            режиссёра и жанр, а также выбрать подходящий
            вариант для просмотра.
        </p>

    </div>


    <!-- ВОЗМОЖНОСТИ САЙТА -->

    <div class="about-features">

        <div class="section-heading">

            <div>
                <span class="section-label">ВОЗМОЖНОСТИ САЙТА</span>

                <h2>Что вы найдёте на КиноМире?</h2>
            </div>

        </div>


        <div class="features-grid">

            <div class="feature-card">

                <span class="feature-icon">▦</span>

                <h3>Каталог фильмов</h3>

                <p>
                    Большая коллекция фильмов с информацией
                    о названиях, жанрах и годах выпуска.
                </p>

            </div>


            <div class="feature-card">

                <span class="feature-icon">⌕</span>

                <h3>Удобный поиск</h3>

                <p>
                    Возможность быстро находить нужные фильмы
                    по их названию.
                </p>

            </div>


            <div class="feature-card">

                <span class="feature-icon">✦</span>

                <h3>Разные жанры</h3>

                <p>
                    Фантастика, драма, комедия, приключения
                    и другие направления кинематографа.
                </p>

            </div>


            <div class="feature-card">

                <span class="feature-icon">▣</span>

                <h3>Информация о фильмах</h3>

                <p>
                    Подробные сведения о фильмах,
                    включая режиссёров и описания сюжетов.
                </p>

            </div>

        </div>

    </div>


    <!-- НАША ЦЕЛЬ -->

    <div class="about-mission">

        <div class="mission-content">

            <span class="section-label">НАША ЦЕЛЬ</span>

            <h2>
                Помогаем находить истории, которые вдохновляют
            </h2>

            <p>
                Мы стремимся объединить интересные фильмы
                в одном удобном пространстве. Независимо от того,
                ищете ли вы захватывающий боевик, трогательную
                драму или лёгкую комедию, КиноМир поможет
                определиться с выбором.
            </p>

        </div>


        <div class="mission-symbol">
            <span>✦</span>
        </div>

    </div>


    <!-- ЗАКЛЮЧИТЕЛЬНЫЙ БЛОК -->

    <div class="about-bottom">

        <h2>Найди свой следующий любимый фильм</h2>

        <p>
            Открой каталог КиноМира и выбери историю,
            которую захочется посмотреть.
        </p>

        <a href="catalog.php" class="btn-primary">
            Смотреть фильмы
        </a>

    </div>

</section>


<?php include "includes/footer.php"; ?>
