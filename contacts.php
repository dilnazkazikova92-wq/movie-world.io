
<?php
include "includes/db.php";

$success = "";
$error = "";

$name = "";
$email = "";
$message = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $name = trim($_POST["name"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $message = trim($_POST["message"] ?? "");

    if ($name === "" || $email === "" || $message === "") {
        $error = "Заполните все поля формы.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Введите корректный адрес электронной почты.";
    } else {

        $stmt = $conn->prepare(
            "INSERT INTO messages (name, email, message)
             VALUES (?, ?, ?)"
        );

        $stmt->bind_param("sss", $name, $email, $message);

        if ($stmt->execute()) {
            $success = "Ваше сообщение успешно отправлено! Спасибо за обратную связь.";

            $name = "";
            $email = "";
            $message = "";
        } else {
            $error = "Не удалось отправить сообщение. Попробуйте ещё раз.";
        }

        $stmt->close();
    }
}

include "includes/header.php";
?>

<style>
/* Главный блок */
.contacts-hero .section-label {
    font-size: 16px !important;
}

.contacts-hero h2 {
    font-size: 42px !important;
    line-height: 1.3 !important;
}

.contacts-hero p {
    font-size: 20px !important;
    line-height: 1.8 !important;
}

/* Контактная информация */
.contacts-info .section-label {
    font-size: 16px !important;
}

.contacts-info h2 {
    font-size: 34px !important;
    line-height: 1.3 !important;
}

.contacts-description {
    font-size: 19px !important;
    line-height: 1.8 !important;
}

/* Контактные пункты */
.contact-item h3 {
    font-size: 21px !important;
    line-height: 1.4 !important;
}

.contact-item p {
    font-size: 17px !important;
    line-height: 1.7 !important;
}

.contact-icon {
    font-size: 27px !important;
}

/* Форма */
.contact-form-block h2 {
    font-size: 32px !important;
    line-height: 1.3 !important;
}

.contact-form-block > p {
    font-size: 18px !important;
    line-height: 1.7 !important;
}

.contact-form-block label {
    font-size: 17px !important;
}

.contact-form-block input,
.contact-form-block textarea {
    font-size: 17px !important;
    line-height: 1.6 !important;
}

.contact-form-block input::placeholder,
.contact-form-block textarea::placeholder {
    font-size: 16px !important;
}

.contact-submit {
    font-size: 18px !important;
}

/* Сообщения */
.form-success,
.form-error {
    font-size: 16px !important;
    line-height: 1.6 !important;
}

/* Нижний блок */
.contacts-bottom h2 {
    font-size: 32px !important;
    line-height: 1.4 !important;
}

.contacts-bottom p {
    font-size: 19px !important;
    line-height: 1.7 !important;
}

.contacts-bottom .btn-primary {
    font-size: 17px !important;
}

/* Адаптация для небольших экранов */
@media (max-width: 768px) {
    .contacts-hero h2 {
        font-size: 34px !important;
    }

    .contacts-hero p {
        font-size: 18px !important;
    }

    .contacts-info h2,
    .contact-form-block h2 {
        font-size: 29px !important;
    }

    .contacts-description {
        font-size: 17px !important;
    }

    .contacts-bottom h2 {
        font-size: 27px !important;
    }
}
</style>

<section class="contacts-page">

    <!-- Главный блок -->

    <div class="contacts-hero">
        <span class="section-label">МЫ ВСЕГДА НА СВЯЗИ</span>

        <h2>Есть вопросы или предложения?</h2>

        <p>
            Нам важно ваше мнение! Если у вас есть вопросы,
            идеи или пожелания по работе сайта, напишите нам
            через форму обратной связи.
        </p>
    </div>

    <!-- Контактная информация и форма -->

    <div class="contacts-content">

        <div class="contacts-info">

            <span class="section-label">СВЯЗЬ С НАМИ</span>

            <h2>Давайте общаться</h2>

            <p class="contacts-description">
                КиноМир создан для любителей кино.
                Мы открыты к предложениям по улучшению проекта
                и всегда рады обратной связи от посетителей.
            </p>

            <div class="contact-item">
                <div class="contact-icon">✉</div>

                <div>
                    <h3>Обратная связь</h3>
                    <p>Отправьте сообщение через форму на сайте.</p>
                </div>
            </div>

            <div class="contact-item">
                <div class="contact-icon">✦</div>

                <div>
                    <h3>Ваши предложения</h3>
                    <p>Поделитесь идеями по развитию КиноМира.</p>
                </div>
            </div>

            <div class="contact-item">
                <div class="contact-icon">▶</div>

                <div>
                    <h3>Мир кино</h3>
                    <p>Открывайте новые фильмы вместе с нами.</p>
                </div>
            </div>

        </div>

        <!-- Форма -->

        <div class="contact-form-block">

            <h2>Напишите нам</h2>

            <p>Заполните форму, и ваше сообщение будет сохранено.</p>

            <?php if ($success !== ""): ?>
                <div class="form-success">
                    <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>

            <?php if ($error !== ""): ?>
                <div class="form-error">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="contacts.php">

                <label for="name">Ваше имя</label>

                <input
                    type="text"
                    id="name"
                    name="name"
                    placeholder="Введите имя"
                    value="<?php echo htmlspecialchars($name); ?>"
                    maxlength="100"
                    required
                >

                <label for="email">Электронная почта</label>

                <input
                    type="email"
                    id="email"
                    name="email"
                    placeholder="example@mail.com"
                    value="<?php echo htmlspecialchars($email); ?>"
                    maxlength="150"
                    required
                >

                <label for="message">Сообщение</label>

                <textarea
                    id="message"
                    name="message"
                    placeholder="Напишите ваше сообщение..."
                    rows="5"
                    required
                ><?php echo htmlspecialchars($message); ?></textarea>

                <button type="submit" class="contact-submit">
                    Отправить сообщение
                </button>

            </form>

        </div>

    </div>

    <!-- Нижний блок -->

    <div class="contacts-bottom">
        <h2>Спасибо, что выбираете КиноМир!</h2>

        <p>
            Ваши отзывы и предложения помогают делать проект лучше.
        </p>

        <a href="catalog.php" class="btn-primary">
            Вернуться к фильмам
        </a>
    </div>

</section>

<?php include "includes/footer.php"; ?>
