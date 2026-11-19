<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include "includes/db.php";

// Преобразование пути к постеру
function homePosterPath($poster) {
    $poster = trim($poster ?? '');

    if ($poster === '') {
        return '';
    }

    if (!preg_match('~^(https?://|/)~i', $poster)
        && strpos($poster, 'images/') !== 0) {
        $poster = 'images/' . $poster;
    }

    return $poster;
}

// Получаем фильмы для главного слайдера
$heroFilms = [];

$heroResult = $conn->query(
    "SELECT id, title, genre, year, poster, description
     FROM films
     ORDER BY id DESC
     LIMIT 5"
);

if ($heroResult) {
    while ($row = $heroResult->fetch_assoc()) {
        $heroFilms[] = $row;
    }
}

// Получаем фильмы для горизонтальной ленты
$filmsResult = $conn->query(
    "SELECT id, title, genre, year, poster
     FROM films
     ORDER BY id DESC
     LIMIT 12"
);

include "includes/header.php";
?>

<style>

/* ==========================================
   ШИРИНА ГЛАВНОЙ СТРАНИЦЫ
========================================== */

/* На главной снимаем узкое ограничение общего контейнера.
   Контент остаётся по центру и не прилипает к краям экрана. */
main:has(.home-hero) {
    width: 100%;
    max-width: 1800px;
    margin: 0 auto;
    padding: 35px clamp(22px, 4vw, 64px) 65px;
    box-sizing: border-box;
}

/* ==========================================
   ГЛАВНЫЙ БАННЕР-СЛАЙДЕР
========================================== */

.home-hero {
    width: 100%;
    max-width: 1600px;
    margin: 20px auto 0;
    padding: 0;
    box-sizing: border-box;
}

.hero-slider {
    position: relative;
    width: 100%;
    height: 570px;
    overflow: hidden;
    border-radius: 22px;
    background: #191820;
    border: 1px solid rgba(255,255,255,0.08);
    box-shadow: 0 18px 55px rgba(0,0,0,0.3);
}

/* Слайды */

.hero-slide {
    position: absolute;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 58px clamp(55px, 6vw, 105px);
    box-sizing: border-box;
    opacity: 0;
    visibility: hidden;
    pointer-events: none;
    transition: opacity 0.65s ease, visibility 0.65s;
}

.hero-slide.active {
    opacity: 1;
    visibility: visible;
    pointer-events: auto;
    z-index: 2;
}

/* Фоновое изображение */

.hero-backdrop {
    position: absolute;
    inset: -15px;
    width: calc(100% + 30px);
    height: calc(100% + 30px);
    object-fit: cover;
    object-position: center 30%;
    filter: blur(4px) brightness(0.58) saturate(1.12);
    opacity: 0.92;
    z-index: -2;
}

.hero-slide::before {
    content: "";
    position: absolute;
    inset: 0;
    background:
        linear-gradient(
            90deg,
            rgba(12,12,17,0.96) 0%,
            rgba(12,12,17,0.82) 34%,
            rgba(12,12,17,0.42) 66%,
            rgba(12,12,17,0.16) 100%
        ),
        linear-gradient(0deg, rgba(10,10,14,0.42), transparent 48%),
        radial-gradient(ellipse at 78% 48%, rgba(255,204,77,0.13), transparent 42%);
    z-index: -1;
}

/* Контент слева */

.hero-slide-content {
    position: relative;
    z-index: 3;
    width: 58%;
    max-width: 720px;
}

.hero-slide-label {
    display: inline-block;
    margin-bottom: 17px;
    color: #ffcc4d;
    font-size: 15px;
    font-weight: 800;
    letter-spacing: 3px;
    text-transform: uppercase;
}

.hero-slide-content h2 {
    margin: 0 0 15px;
    color: #ffffff;
    font-size: 49px;
    font-weight: 900;
    line-height: 1.15;
    letter-spacing: -1px;
}

.hero-slide-meta {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
    margin-bottom: 20px;
    color: #d5d5dc;
    font-size: 17px;
}

.hero-slide-meta span {
    display: inline-flex;
    align-items: center;
    gap: 7px;
}

.hero-slide-meta .hero-genre {
    color: #ffcc4d;
    font-weight: 700;
}

.hero-slide-meta .hero-meta-dot {
    color: #777783;
}

.hero-slide-description {
    max-width: 570px;
    margin: 0 0 27px;
    color: #c5c5cf;
    font-size: 18px;
    line-height: 1.8;
    display: -webkit-box;
    -webkit-line-clamp: 3;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.hero-slide-buttons {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 13px;
}

.hero-watch-button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 9px;
    padding: 14px 25px;
    border-radius: 10px;
    background: #ffcc4d;
    color: #17171d;
    font-size: 17px;
    font-weight: 800;
    text-decoration: none;
    transition: 0.25s;
}

.hero-watch-button:hover {
    background: #ffdc7b;
    transform: translateY(-3px);
}

.hero-catalog-button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 13px 23px;
    border: 1px solid rgba(255,255,255,0.25);
    border-radius: 10px;
    background: rgba(255,255,255,0.06);
    color: #ffffff;
    font-size: 16px;
    font-weight: 650;
    text-decoration: none;
    transition: 0.25s;
}

.hero-catalog-button:hover {
    background: rgba(255,255,255,0.14);
    border-color: #ffcc4d;
}

/* Постер справа */

.hero-slide-poster {
    position: relative;
    z-index: 2;
    width: 325px;
    height: 455px;
    flex-shrink: 0;
    margin-right: 38px;
    border-radius: 17px;
    overflow: hidden;
    background: #292832;
    border: 1px solid rgba(255,255,255,0.17);
    box-shadow: 0 28px 75px rgba(0,0,0,0.62), 0 0 45px rgba(255,204,77,0.10);
    transform: rotate(1.5deg) scale(1.01);
    transition: transform 0.5s;
}

.hero-slide.active .hero-slide-poster {
    animation: posterAppear 0.8s ease;
}

@keyframes posterAppear {
    from {
        opacity: 0;
        transform: translateX(25px) rotate(5deg);
    }
    to {
        opacity: 1;
        transform: translateX(0) rotate(2deg);
    }
}

.hero-slide-poster img {
    display: block;
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.hero-poster-placeholder {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 100%;
    height: 100%;
    color: #ffcc4d;
    font-size: 65px;
    background: linear-gradient(145deg, #383044, #17171d);
}

/* Стрелки слайдера */

.hero-arrow {
    position: absolute;
    z-index: 5;
    top: 50%;
    transform: translateY(-50%);
    display: flex;
    align-items: center;
    justify-content: center;
    width: 43px;
    height: 43px;
    border: 1px solid rgba(255,255,255,0.22);
    border-radius: 50%;
    background: rgba(15,15,20,0.6);
    color: #ffffff;
    font-size: 25px;
    cursor: pointer;
    transition: 0.25s;
}

.hero-arrow:hover {
    background: #ffcc4d;
    border-color: #ffcc4d;
    color: #17171d;
}

.hero-arrow-left {
    left: 17px;
}

.hero-arrow-right {
    right: 17px;
}

/* Нижняя панель */

.hero-bottom {
    position: absolute;
    z-index: 5;
    bottom: 22px;
    left: 85px;
    right: 85px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20px;
}

.hero-dots {
    display: flex;
    align-items: center;
    gap: 8px;
}

.hero-dot {
    width: 9px;
    height: 9px;
    padding: 0;
    border: none;
    border-radius: 20px;
    background: rgba(255,255,255,0.35);
    cursor: pointer;
    transition: 0.3s;
}

.hero-dot.active {
    width: 28px;
    background: #ffcc4d;
}

.hero-counter {
    color: #c4c4cd;
    font-size: 14px;
    letter-spacing: 1px;
}

/* ==========================================
   ЗАГОЛОВКИ СЕКЦИЙ
========================================== */

.home-section {
    width: 100%;
    max-width: 1600px;
    margin: 0 auto;
    padding: 65px 0 20px;
    box-sizing: border-box;
}

.home-section-heading {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 20px;
    margin-bottom: 28px;
}

.home-section-heading .section-label {
    display: block;
    margin-bottom: 9px;
    font-size: 14px;
}

.home-section-heading h2 {
    margin: 0;
    color: #ffffff;
    font-size: 35px;
    font-weight: 800;
}

/* ==========================================
   РЕКОМЕНДУЕМЫЕ ФИЛЬМЫ
========================================== */

.home-films-controls {
    display: flex;
    gap: 9px;
    flex-shrink: 0;
}

.home-films-arrow {
    width: 42px;
    height: 42px;
    border: 1px solid rgba(255,204,77,0.4);
    border-radius: 50%;
    background: #1d1b22;
    color: #ffcc4d;
    font-size: 24px;
    cursor: pointer;
    transition: 0.25s;
}

.home-films-arrow:hover {
    background: #ffcc4d;
    color: #17171d;
    transform: scale(1.06);
}

.home-films-track {
    display: flex;
    gap: 22px;
    overflow-x: auto;
    overflow-y: hidden;
    scroll-behavior: smooth;
    scroll-snap-type: x mandatory;
    padding: 5px 2px 22px;
    scrollbar-width: none;
}

.home-films-track::-webkit-scrollbar {
    display: none;
}

.home-film-card {
    flex: 0 0 195px;
    min-width: 0;
    color: inherit;
    text-decoration: none;
    scroll-snap-align: start;
    transition: transform 0.3s;
}

.home-film-card:hover {
    transform: translateY(-6px);
}

.home-film-poster {
    position: relative;
    width: 100%;
    height: 285px;
    overflow: hidden;
    border-radius: 13px;
    background: #24232b;
    box-shadow: 0 8px 22px rgba(0,0,0,0.25);
}

.home-film-poster img {
    display: block;
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.4s;
}

.home-film-card:hover .home-film-poster img {
    transform: scale(1.06);
}

.home-film-year {
    position: absolute;
    top: 11px;
    right: 11px;
    padding: 6px 9px;
    border-radius: 6px;
    background: rgba(15,15,20,0.88);
    color: #ffffff;
    font-size: 14px;
    font-weight: 700;
}

.home-film-placeholder {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 100%;
    height: 100%;
    color: #ffcc4d;
    font-size: 42px;
    background: linear-gradient(145deg,#302b35,#17161c);
}

.home-film-info {
    padding: 13px 2px 0;
}

.home-film-info h3 {
    margin: 0 0 7px;
    color: #ffffff;
    font-size: 20px;
    line-height: 1.4;
    font-weight: 700;
}

.home-film-info p {
    margin: 0;
    color: #a6a6b2;
    font-size: 15px;
}

/* ==========================================
   ЖАНРЫ
========================================== */

.home-genres {
    width: 100%;
    max-width: 1600px;
    margin: 0 auto;
    padding: 55px 0 25px;
    box-sizing: border-box;
}

.home-genre-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 20px;
}

.home-genre-card {
    display: block;
    min-height: 155px;
    padding: 25px;
    box-sizing: border-box;
    border: 1px solid rgba(255,255,255,0.09);
    border-radius: 14px;
    background: linear-gradient(145deg,#1c1b23,#17171d);
    text-decoration: none;
    transition: 0.3s;
}

.home-genre-card:hover {
    transform: translateY(-5px);
    border-color: rgba(255,204,77,0.5);
    background: linear-gradient(145deg,#25212a,#191920);
}

.home-genre-icon {
    display: block;
    margin-bottom: 13px;
    color: #ffcc4d;
    font-size: 25px;
}

.home-genre-card h3 {
    margin: 0 0 8px;
    color: #ffffff;
    font-size: 22px;
}

.home-genre-card p {
    margin: 0;
    color: #a6a6b2;
    font-size: 15px;
    line-height: 1.7;
}

/* ==========================================
   О ПРОЕКТЕ
========================================== */

.home-about {
    width: 100%;
    max-width: 1600px;
    margin: 0 auto;
    padding: 55px 0 75px;
    box-sizing: border-box;
}

.home-about-inner {
    position: relative;
    overflow: hidden;
    padding: 38px 42px;
    border: 1px solid rgba(255,204,77,0.13);
    border-radius: 17px;
    background:
        radial-gradient(
            circle at 90% 10%,
            rgba(255,204,77,0.08),
            transparent 35%
        ),
        #191920;
}

.home-about .section-label {
    display: block;
    margin-bottom: 10px;
    font-size: 14px;
}

.home-about h2 {
    margin: 0 0 15px;
    color: #ffffff;
    font-size: 34px;
}

.home-about p {
    max-width: 100%;
    margin: 0;
    color: #b5b5c0;
    font-size: 18px;
    line-height: 1.9;
    text-align: justify;
}

/* ==========================================
   АДАПТИВНОСТЬ
========================================== */

@media (max-width: 1050px) {

    main:has(.home-hero) {
        padding-left: 28px;
        padding-right: 28px;
    }

    .hero-slide {
        padding: 45px 65px;
    }

    .hero-slide-content h2 {
        font-size: 40px;
    }

    .hero-slide-poster {
        width: 235px;
        height: 340px;
        margin-right: 25px;
    }

    .hero-bottom {
        left: 65px;
        right: 65px;
    }

    .home-section,
    .home-genres,
    .home-about {
        padding-left: 30px;
        padding-right: 30px;
    }
}

@media (max-width: 750px) {

    main:has(.home-hero) {
        padding: 20px 0 45px;
    }

    .home-hero {
        padding: 0 18px;
        margin-top: 25px;
    }

    .hero-slider {
        height: 620px;
    }

    .hero-backdrop {
        filter: blur(5px) brightness(0.48) saturate(1.08);
    }

    .hero-slide {
        flex-direction: column-reverse;
        justify-content: flex-end;
        padding: 35px 28px 75px;
        text-align: center;
    }

    .hero-slide-poster {
        width: 155px;
        height: 220px;
        margin: 0 auto 22px;
        transform: none;
    }

    .hero-slide.active .hero-slide-poster {
        animation: none;
    }

    .hero-slide-content {
        width: 100%;
    }

    .hero-slide-content h2 {
        font-size: 30px;
    }

    .hero-slide-label {
        font-size: 12px;
    }

    .hero-slide-meta {
        justify-content: center;
        font-size: 14px;
        margin-bottom: 12px;
    }

    .hero-slide-description {
        font-size: 14px;
        line-height: 1.6;
        margin-bottom: 17px;
        -webkit-line-clamp: 2;
    }

    .hero-slide-buttons {
        justify-content: center;
    }

    .hero-watch-button,
    .hero-catalog-button {
        padding: 11px 16px;
        font-size: 14px;
    }

    .hero-bottom {
        left: 28px;
        right: 28px;
        bottom: 18px;
    }

    .hero-arrow {
        width: 35px;
        height: 35px;
        font-size: 20px;
    }

    .hero-arrow-left {
        left: 8px;
    }

    .hero-arrow-right {
        right: 8px;
    }

    .home-section,
    .home-genres,
    .home-about {
        padding-left: 20px;
        padding-right: 20px;
    }

    .home-section-heading h2 {
        font-size: 27px;
    }

    .home-film-card {
        flex-basis: 160px;
    }

    .home-film-poster {
        height: 230px;
    }

    .home-film-info h3 {
        font-size: 17px;
    }

    .home-film-info p {
        font-size: 14px;
    }

    .home-genre-grid {
        grid-template-columns: 1fr;
        gap: 12px;
    }

    .home-genre-card {
        min-height: 125px;
    }

    .home-genre-card h3 {
        font-size: 20px;
    }

    .home-genre-card p {
        font-size: 14px;
    }

    .home-about-inner {
        padding: 27px 23px;
    }

    .home-about h2 {
        font-size: 27px;
    }

    .home-about p {
        font-size: 16px;
        text-align: left;
    }
}

@media (max-width: 480px) {

    .hero-slider {
        height: 600px;
    }

    .hero-slide {
        padding-left: 22px;
        padding-right: 22px;
    }

    .hero-slide-content h2 {
        font-size: 26px;
    }

    .home-section-heading h2 {
        font-size: 22px;
    }

    .home-film-card {
        flex-basis: 145px;
    }

    .home-film-poster {
        height: 210px;
    }

    .home-films-arrow {
        width: 35px;
        height: 35px;
    }

    .home-section,
    .home-genres,
    .home-about {
        padding-top: 40px;
    }
}


/* ==========================================
   ОБНОВЛЁННЫЙ КИНОБАННЕР И ГЛАВНАЯ
========================================== */
.hero-slider {
    height: clamp(500px, 39vw, 610px);
    border: 1px solid rgba(255, 204, 77, .20);
    border-radius: 25px;
    background: #15141b;
    box-shadow: 0 28px 75px rgba(0,0,0,.38);
}

.hero-slide {
    padding: 58px clamp(48px, 6vw, 100px);
}

.hero-backdrop {
    inset: 0;
    width: 100%;
    height: 100%;
    object-position: center 28%;
    filter: blur(1.5px) brightness(.48) saturate(1.18);
    transform: scale(1.035);
    transition: transform 9s ease, filter .7s ease;
}

.hero-slide.active .hero-backdrop { transform: scale(1.10); }

.hero-slide::before {
    background:
        linear-gradient(90deg, rgba(12,12,17,.97) 0%, rgba(12,12,17,.88) 34%, rgba(12,12,17,.48) 67%, rgba(12,12,17,.20) 100%),
        linear-gradient(0deg, rgba(8,8,12,.65), transparent 52%),
        radial-gradient(ellipse at 76% 50%, rgba(255,204,77,.14), transparent 43%);
}

.hero-slide::after {
    content: "";
    position: absolute;
    inset: 16px;
    border: 1px solid rgba(255,255,255,.07);
    border-radius: 17px;
    pointer-events: none;
}

.hero-slide-content { width: min(58%, 700px); }
.hero-slide-label { font-size: 13px; letter-spacing: 3.5px; }
.hero-slide-content h2 {
    max-width: 650px;
    font-size: clamp(38px, 4vw, 58px);
    line-height: 1.08;
    letter-spacing: -1.7px;
    text-wrap: balance;
    text-shadow: 0 5px 25px rgba(0,0,0,.35);
}
.hero-slide-meta { font-size: 15px; gap: 10px; }
.hero-slide-description {
    max-width: 550px;
    color: rgba(245,244,249,.82);
    font-size: 16px;
    line-height: 1.75;
}
.hero-watch-button {
    min-height: 49px;
    padding: 0 24px;
    border-radius: 9px;
    box-shadow: 0 10px 28px rgba(255,190,53,.19);
}
.hero-catalog-button {
    min-height: 49px;
    padding: 0 22px;
    border-radius: 9px;
    backdrop-filter: blur(10px);
}
.hero-slide-poster {
    width: clamp(220px, 20vw, 300px);
    height: clamp(330px, 29vw, 440px);
    margin-right: clamp(12px, 3vw, 42px);
    border-radius: 13px;
    border: 1px solid rgba(255,255,255,.24);
    box-shadow: 0 30px 85px rgba(0,0,0,.68), 0 0 55px rgba(255,204,77,.10);
    transform: rotate(1deg);
}
.hero-slide.active .hero-slide-poster { animation: kmPosterIn .8s cubic-bezier(.2,.75,.25,1); }
@keyframes kmPosterIn {
    from { opacity: 0; transform: translate3d(28px,12px,0) rotate(4deg) scale(.97); }
    to { opacity: 1; transform: translate3d(0,0,0) rotate(1deg) scale(1); }
}
.hero-bottom { left: clamp(48px, 6vw, 100px); right: clamp(48px, 6vw, 100px); bottom: 25px; }
.hero-dot { background: rgba(255,255,255,.38); }
.hero-counter { color: rgba(255,255,255,.78); }
.hero-arrow { width: 40px; height: 40px; opacity: .72; }
.hero-arrow:hover { opacity: 1; }

.home-section { padding-top: 72px; }
.home-section-heading { margin-bottom: 25px; }
.home-section-heading .section-label,
.home-genres .section-label { color: #ffcc4d; letter-spacing: 2.8px; font-weight: 800; }
.home-section-heading h2 { font-size: clamp(27px, 2.5vw, 36px); letter-spacing: -.7px; }
.home-films-track { gap: 20px; padding: 8px 3px 25px; }
.home-film-card { flex-basis: 190px; }
.home-film-poster {
    height: 280px;
    border-radius: 11px;
    box-shadow: 0 12px 30px rgba(0,0,0,.30);
    outline: 1px solid rgba(255,255,255,.06);
    outline-offset: -1px;
}
.home-film-card:hover .home-film-poster { box-shadow: 0 18px 38px rgba(0,0,0,.45), 0 0 0 1px rgba(255,204,77,.32); }
.home-film-info h3 { font-size: 18px; line-height: 1.35; }
.home-film-info p { color: #aeb0bd; font-size: 14px; }

.home-genres { padding-top: 48px; }
.home-genre-grid { gap: 16px; }
.home-genre-card {
    position: relative;
    min-height: 175px;
    padding: 27px;
    overflow: hidden;
    border-radius: 15px;
    border-color: rgba(255,255,255,.09);
    background: linear-gradient(135deg, #24202c 0%, #191920 70%);
}
.home-genre-card::after {
    content: "";
    position: absolute;
    width: 170px;
    height: 170px;
    right: -55px;
    bottom: -85px;
    border: 1px solid rgba(255,204,77,.17);
    border-radius: 50%;
    box-shadow: 0 0 0 18px rgba(255,204,77,.025), 0 0 0 38px rgba(255,204,77,.018);
    transition: transform .45s ease;
}
.home-genre-card:hover::after { transform: scale(1.18); }
.home-genre-card:nth-child(2) { background: linear-gradient(135deg, #29202a, #191920 72%); }
.home-genre-card:nth-child(3) { background: linear-gradient(135deg, #24252d, #191920 72%); }
.home-genre-icon { position: relative; z-index: 1; font-size: 23px; }
.home-genre-card h3, .home-genre-card p { position: relative; z-index: 1; }
.home-genre-card h3 { font-size: 21px; }
.home-genre-card p { max-width: 260px; }

.home-about { padding-top: 42px; padding-bottom: 25px; }
.home-about-inner {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 25px;
    padding: 25px 30px;
    border-radius: 14px;
    background: linear-gradient(110deg, #1b1a22, #17171d);
}
.home-about-inner::after { content: "✦"; color: rgba(255,204,77,.55); font-size: 38px; padding: 0 15px; }
.home-about .section-label { margin-bottom: 5px; font-size: 11px; }
.home-about h2 { margin-bottom: 5px; font-size: 23px; }
.home-about p { max-width: 850px; font-size: 14px; line-height: 1.65; text-align: left; }

@media (max-width: 1050px) {
    .hero-slider { height: 510px; }
    .hero-slide-poster { width: 215px; height: 325px; }
    .hero-slide-content h2 { font-size: 39px; }
}
@media (max-width: 750px) {
    .hero-slider { height: 650px; }
    .hero-slide { padding: 30px 24px 72px; }
    .hero-slide-poster { width: 145px; height: 215px; margin: 0 auto 19px; }
    .hero-slide-content { width: 100%; }
    .hero-slide-content h2 { font-size: 29px; }
    .hero-slide-description { font-size: 14px; }
    .hero-bottom { left: 25px; right: 25px; }
    .home-about-inner { align-items: flex-start; }
    .home-about-inner::after { display: none; }
}
@media (prefers-reduced-motion: reduce) {
    .hero-slide.active .hero-slide-poster, .hero-backdrop { animation: none !important; transition: none !important; }
}

</style>


<!-- ==========================================
     ГЛАВНЫЙ БАННЕР С ФИЛЬМАМИ
========================================== -->

<section class="home-hero">

    <div class="hero-slider" id="homeHeroSlider">

        <?php if (!empty($heroFilms)): ?>

            <?php foreach ($heroFilms as $index => $film): ?>

                <?php
                $poster = homePosterPath($film['poster'] ?? '');
                $description = trim($film['description'] ?? '');

                if ($description === '') {
                    $description = 'Откройте для себя эту историю в каталоге КиноМир.';
                }
                ?>

                <div class="hero-slide <?php echo $index === 0 ? 'active' : ''; ?>">

                    <?php if ($poster !== ''): ?>
                        <img
                            class="hero-backdrop"
                            src="<?php echo htmlspecialchars($poster, ENT_QUOTES, 'UTF-8'); ?>"
                            alt=""
                        >
                    <?php endif; ?>

                    <div class="hero-slide-content">

                        <span class="hero-slide-label">
                            <span>✦</span> ВЫБОР КИНОМИР
                        </span>

                        <h2>
                            <?php echo htmlspecialchars($film['title'], ENT_QUOTES, 'UTF-8'); ?>
                        </h2>

                        <div class="hero-slide-meta">

                            <span class="hero-genre">
                                <?php echo htmlspecialchars($film['genre'], ENT_QUOTES, 'UTF-8'); ?>
                            </span>

                            <span class="hero-meta-dot">•</span>

                            <span>
                                <?php echo (int)$film['year']; ?>
                            </span>

                        </div>

                        <p class="hero-slide-description">
                            <?php echo htmlspecialchars($description, ENT_QUOTES, 'UTF-8'); ?>
                        </p>

                        <div class="hero-slide-buttons">

                            <a
                                href="film.php?id=<?php echo (int)$film['id']; ?>"
                                class="hero-watch-button"
                            >
                                ▶ Смотреть фильм
                            </a>

                            <a
                                href="catalog.php"
                                class="hero-catalog-button"
                            >
                                Весь каталог
                            </a>

                        </div>

                    </div>

                    <div class="hero-slide-poster">

                        <?php if ($poster !== ''): ?>

                            <img
                                src="<?php echo htmlspecialchars($poster, ENT_QUOTES, 'UTF-8'); ?>"
                                alt="<?php echo htmlspecialchars($film['title'], ENT_QUOTES, 'UTF-8'); ?>"
                                onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';"
                            >

                            <div class="hero-poster-placeholder" style="display:none;">
                                ▶
                            </div>

                        <?php else: ?>

                            <div class="hero-poster-placeholder">
                                ▶
                            </div>

                        <?php endif; ?>

                    </div>

                </div>

            <?php endforeach; ?>

            <!-- Стрелки -->

            <?php if (count($heroFilms) > 1): ?>

                <button
                    type="button"
                    class="hero-arrow hero-arrow-left"
                    onclick="changeHeroSlide(-1)"
                    aria-label="Предыдущий фильм"
                >
                    ‹
                </button>

                <button
                    type="button"
                    class="hero-arrow hero-arrow-right"
                    onclick="changeHeroSlide(1)"
                    aria-label="Следующий фильм"
                >
                    ›
                </button>

            <?php endif; ?>

            <!-- Точки и счётчик -->

            <div class="hero-bottom">

                <div class="hero-dots">

                    <?php foreach ($heroFilms as $index => $film): ?>

                        <button
                            type="button"
                            class="hero-dot <?php echo $index === 0 ? 'active' : ''; ?>"
                            onclick="showHeroSlide(<?php echo $index; ?>)"
                            aria-label="Показать фильм <?php echo $index + 1; ?>"
                        ></button>

                    <?php endforeach; ?>

                </div>

                <span class="hero-counter" id="heroCounter">
                    01 / <?php echo str_pad(count($heroFilms), 2, '0', STR_PAD_LEFT); ?>
                </span>

            </div>

        <?php else: ?>

            <div class="hero-slide active">

                <div class="hero-slide-content">

                    <span class="hero-slide-label">ТВОЙ МИР КИНО</span>

                    <h2>Истории, которые остаются с тобой</h2>

                    <p class="hero-slide-description">
                        Добро пожаловать в КиноМир!
                        Здесь собраны фильмы разных жанров,
                        интересные истории и настоящие шедевры кинематографа.
                    </p>

                    <div class="hero-slide-buttons">
                        <a href="catalog.php" class="hero-watch-button">
                            Смотреть каталог
                        </a>
                    </div>

                </div>

            </div>

        <?php endif; ?>

    </div>

</section>


<!-- ==========================================
     РЕКОМЕНДУЕМЫЕ ФИЛЬМЫ
========================================== -->

<section class="home-section">

    <div class="home-section-heading">

        <div>
            <span class="section-label">
                КИНО, КОТОРОЕ МЫ РЕКОМЕНДУЕМ
            </span>

            <h2>Кино, которое стоит увидеть</h2>
        </div>

        <div class="home-films-controls">

            <button
                type="button"
                class="home-films-arrow"
                onclick="scrollHomeFilms(-1)"
                aria-label="Предыдущие фильмы"
            >
                ‹
            </button>

            <button
                type="button"
                class="home-films-arrow"
                onclick="scrollHomeFilms(1)"
                aria-label="Следующие фильмы"
            >
                ›
            </button>

        </div>

    </div>

    <?php if ($filmsResult && $filmsResult->num_rows > 0): ?>

        <div class="home-films-track" id="homeFilmsTrack">

            <?php while ($film = $filmsResult->fetch_assoc()): ?>

                <?php $poster = homePosterPath($film['poster'] ?? ''); ?>

                <a
                    href="film.php?id=<?php echo (int)$film['id']; ?>"
                    class="home-film-card"
                >

                    <div class="home-film-poster">

                        <?php if ($poster !== ''): ?>

                            <img
                                src="<?php echo htmlspecialchars($poster, ENT_QUOTES, 'UTF-8'); ?>"
                                alt="<?php echo htmlspecialchars($film['title'], ENT_QUOTES, 'UTF-8'); ?>"
                                onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';"
                            >

                            <div class="home-film-placeholder" style="display:none;">
                                ▶
                            </div>

                        <?php else: ?>

                            <div class="home-film-placeholder">
                                ▶
                            </div>

                        <?php endif; ?>

                        <span class="home-film-year">
                            <?php echo (int)$film['year']; ?>
                        </span>

                    </div>

                    <div class="home-film-info">

                        <h3>
                            <?php echo htmlspecialchars($film['title'], ENT_QUOTES, 'UTF-8'); ?>
                        </h3>

                        <p>
                            <?php echo htmlspecialchars($film['genre'], ENT_QUOTES, 'UTF-8'); ?>
                        </p>

                    </div>

                </a>

            <?php endwhile; ?>

        </div>

    <?php else: ?>

        <p>Фильмы пока не добавлены.</p>

    <?php endif; ?>

</section>


<!-- ==========================================
     ПОПУЛЯРНЫЕ ЖАНРЫ
========================================== -->

<section class="home-genres">

    <div class="home-section-heading">

        <div>
            <span class="section-label">
                ВЫБИРАЙ НАСТРОЕНИЕ
            </span>

            <h2>Популярные жанры</h2>
        </div>

    </div>

    <div class="home-genre-grid">

        <a href="genres.php?genre=Фантастика" class="home-genre-card">

            <span class="home-genre-icon">✦</span>

            <h3>Фантастика</h3>

            <p>Другие миры и невероятные истории</p>

        </a>

        <a href="genres.php?genre=Драма" class="home-genre-card">

            <span class="home-genre-icon">♡</span>

            <h3>Драма</h3>

            <p>Истории, которые трогают душу</p>

        </a>

        <a href="genres.php?genre=Комедия" class="home-genre-card">

            <span class="home-genre-icon">☼</span>

            <h3>Комедия</h3>

            <p>Фильмы для хорошего настроения</p>

        </a>

    </div>

</section>


<!-- КОРОТКАЯ ССЫЛКА НА ПРОЕКТ -->
<section class="home-about">
    <div class="home-about-inner">
        <div>
            <span class="section-label">МИР КИНО — В ОДНОМ МЕСТЕ</span>
            <h2>Находи истории по своему настроению</h2>
            <p>Изучай каталог, открывай новые жанры и собирай собственную коллекцию фильмов.</p>
        </div>
        <a href="about.php" class="hero-catalog-button">О проекте&nbsp; →</a>
    </div>
</section>


<script>

/* ==========================================
   ГЛАВНЫЙ СЛАЙДЕР
========================================== */

let currentHeroSlide = 0;
let heroTimer = null;

const heroSlides = document.querySelectorAll(".hero-slide");
const heroDots = document.querySelectorAll(".hero-dot");
const heroCounter = document.getElementById("heroCounter");

function showHeroSlide(index) {

    if (!heroSlides.length) {
        return;
    }

    currentHeroSlide = (index + heroSlides.length) % heroSlides.length;

    heroSlides.forEach((slide, i) => {
        slide.classList.toggle("active", i === currentHeroSlide);
    });

    heroDots.forEach((dot, i) => {
        dot.classList.toggle("active", i === currentHeroSlide);
    });

    if (heroCounter) {
        heroCounter.textContent =
            String(currentHeroSlide + 1).padStart(2, "0") +
            " / " +
            String(heroSlides.length).padStart(2, "0");
    }

    restartHeroTimer();
}

function changeHeroSlide(direction) {
    showHeroSlide(currentHeroSlide + direction);
}

function restartHeroTimer() {

    if (heroTimer) {
        clearInterval(heroTimer);
    }

    if (heroSlides.length > 1) {
        heroTimer = setInterval(function() {
            showHeroSlide(currentHeroSlide + 1);
        }, 7000);
    }
}

// Останавливаем автоматическое переключение,
// пока пользователь находится над баннером.

const heroSlider = document.getElementById("homeHeroSlider");

if (heroSlider) {

    heroSlider.addEventListener("mouseenter", function() {
        if (heroTimer) {
            clearInterval(heroTimer);
        }
    });

    heroSlider.addEventListener("mouseleave", function() {
        restartHeroTimer();
    });

}

// Запускаем слайдер
restartHeroTimer();


/* ==========================================
   ЛЕНТА ФИЛЬМОВ
========================================== */

function scrollHomeFilms(direction) {

    const track = document.getElementById("homeFilmsTrack");

    if (track) {
        track.scrollBy({
            left: direction * 650,
            behavior: "smooth"
        });
    }

}

</script>

<?php include "includes/footer.php"; ?>