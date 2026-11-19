<?php
session_start();
include "includes/db.php";

/* ПРОВЕРКА АВТОРИЗАЦИИ */

if (
    !isset($_SESSION["user_id"]) ||
    ($_SESSION["user_role"] ?? "") !== "user"
) {
    header("Location: login.php");
    exit;
}

$userId = (int)$_SESSION["user_id"];

/* CSRF-ТОКЕН */

if (empty($_SESSION["edit_profile_token"])) {
    $_SESSION["edit_profile_token"] = bin2hex(random_bytes(32));
}

$message = "";
$error = "";

/* ПОЛУЧЕНИЕ ДАННЫХ ПОЛЬЗОВАТЕЛЯ */

$stmt = $conn->prepare(
    "SELECT login, email, password
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

/* ОБРАБОТКА ФОРМЫ */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    if (
        !isset($_POST["edit_profile_token"]) ||
        !hash_equals(
            $_SESSION["edit_profile_token"],
            $_POST["edit_profile_token"]
        )
    ) {
        $error = "Ошибка проверки формы. Обновите страницу.";

    } else {

        $newLogin = trim($_POST["login"] ?? "");
        $newEmail = trim($_POST["email"] ?? "");

        $currentPassword = $_POST["current_password"] ?? "";
        $newPassword = $_POST["new_password"] ?? "";
        $confirmPassword = $_POST["confirm_password"] ?? "";

        /* ПРОВЕРКА ЛОГИНА */

        if ($newLogin === "" || mb_strlen($newLogin) < 3) {

            $error = "Логин должен содержать не менее 3 символов.";

        } elseif (mb_strlen($newLogin) > 50) {

            $error = "Логин слишком длинный.";

        } elseif (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {

            $error = "Введите корректный адрес электронной почты.";

        } elseif ($currentPassword === "") {

            $error = "Введите текущий пароль.";

        } elseif (!password_verify($currentPassword, $user["password"])) {

            $error = "Текущий пароль введён неправильно.";

        } elseif ($newPassword !== "" && strlen($newPassword) < 6) {

            $error = "Новый пароль должен содержать не менее 6 символов.";

        } elseif ($newPassword !== $confirmPassword) {

            $error = "Новые пароли не совпадают.";

        } else {

            /* ПРОВЕРКА УНИКАЛЬНОСТИ ЛОГИНА И ПОЧТЫ */

            $stmt = $conn->prepare(
                "SELECT id
                 FROM users
                 WHERE (login = ? OR email = ?)
                 AND id != ?"
            );

            $stmt->bind_param(
                "ssi",
                $newLogin,
                $newEmail,
                $userId
            );

            $stmt->execute();

            $duplicate = $stmt->get_result()->fetch_assoc();

            $stmt->close();

            if ($duplicate) {

                $error = "Такой логин или электронная почта уже используются.";

            } else {

                /* СОХРАНЕНИЕ ИЗМЕНЕНИЙ */

                if ($newPassword !== "") {

                    $passwordHash = password_hash(
                        $newPassword,
                        PASSWORD_DEFAULT
                    );

                    $stmt = $conn->prepare(
                        "UPDATE users
                         SET login = ?, email = ?, password = ?
                         WHERE id = ?"
                    );

                    $stmt->bind_param(
                        "sssi",
                        $newLogin,
                        $newEmail,
                        $passwordHash,
                        $userId
                    );

                } else {

                    $stmt = $conn->prepare(
                        "UPDATE users
                         SET login = ?, email = ?
                         WHERE id = ?"
                    );

                    $stmt->bind_param(
                        "ssi",
                        $newLogin,
                        $newEmail,
                        $userId
                    );
                }

                if ($stmt->execute()) {

                    $stmt->close();

                    unset($_SESSION["edit_profile_token"]);

                    header("Location: account.php?profile_updated=1");
                    exit;

                } else {

                    $error = "Не удалось сохранить изменения.";
                    $stmt->close();
                }
            }
        }
    }
}

include "includes/header.php";
?>

<style>
.edit-profile-page {
    width: 100%;
    max-width: 850px;
    margin: 0 auto;
    padding: 50px 25px 80px;
    color: #f5f5f7;
}

.edit-profile-back {
    display: inline-block;
    margin-bottom: 25px;
    color: #ffcc4d;
    font-size: 16px;
    font-weight: 700;
    text-decoration: none;
}

.edit-profile-back:hover {
    color: #ffdc7b;
}

.edit-profile-header {
    margin-bottom: 30px;
}

.edit-profile-header span {
    display: block;
    margin-bottom: 12px;
    color: #ffcc4d;
    font-size: 14px;
    font-weight: 700;
    letter-spacing: 2px;
}

.edit-profile-header h2 {
    margin: 0 0 12px;
    color: #fff;
    font-size: 34px;
}

.edit-profile-header p {
    margin: 0;
    color: #b9b9c5;
    font-size: 17px;
    line-height: 1.7;
}

.edit-profile-card {
    padding: 32px;
    background: #1c1b25;
    border: 1px solid #393744;
    border-radius: 17px;
}

.edit-profile-card h3 {
    margin: 0 0 23px;
    color: #fff;
    font-size: 21px;
}

.edit-profile-form {
    display: flex;
    flex-direction: column;
    gap: 19px;
}

.edit-profile-field {
    display: flex;
    flex-direction: column;
    gap: 9px;
}

.edit-profile-field label {
    color: #c5c5cf;
    font-size: 15px;
}

.edit-profile-field input {
    width: 100%;
    padding: 14px 15px;
    box-sizing: border-box;
    background: #111117;
    border: 1px solid #44434f;
    border-radius: 8px;
    color: #fff;
    font-size: 16px;
}

.edit-profile-field input:focus {
    outline: none;
    border-color: #ffcc4d;
}

.edit-profile-divider {
    height: 1px;
    margin: 8px 0;
    background: #393744;
}

.edit-profile-hint {
    margin: -5px 0 0;
    color: #9999a7;
    font-size: 14px;
    line-height: 1.6;
}

.edit-profile-submit {
    align-self: flex-start;
    margin-top: 8px;
    padding: 15px 26px;
    background: #ffcc4d;
    border: none;
    border-radius: 8px;
    color: #191919;
    font-size: 16px;
    font-weight: 700;
    cursor: pointer;
    transition: 0.25s;
}

.edit-profile-submit:hover {
    background: #ffdc7b;
    transform: translateY(-2px);
}

.edit-profile-message,
.edit-profile-error {
    margin-bottom: 20px;
    padding: 14px 16px;
    border-radius: 8px;
    font-size: 15px;
}

.edit-profile-error {
    background: rgba(220, 70, 70, 0.12);
    border: 1px solid rgba(220, 70, 70, 0.35);
    color: #ff9999;
}

@media (max-width: 650px) {
    .edit-profile-page {
        padding: 30px 15px 50px;
    }

    .edit-profile-card {
        padding: 22px;
    }

    .edit-profile-header h2 {
        font-size: 27px;
    }

    .edit-profile-submit {
        width: 100%;
    }
}
</style>

<section class="edit-profile-page">

    <a href="account.php" class="edit-profile-back">
        ← Вернуться в личный кабинет
    </a>

    <div class="edit-profile-header">
        <span>ЛИЧНЫЙ КАБИНЕТ</span>
        <h2>Редактирование профиля</h2>
        <p>
            Здесь ты можешь изменить свои личные данные
            и при необходимости установить новый пароль.
        </p>
    </div>

    <div class="edit-profile-card">

        <h3>Личные данные</h3>

        <?php if ($error !== ""): ?>
            <div class="edit-profile-error">
                <?php echo htmlspecialchars($error, ENT_QUOTES, "UTF-8"); ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="edit-profile-form">

            <input
                type="hidden"
                name="edit_profile_token"
                value="<?php echo htmlspecialchars($_SESSION["edit_profile_token"], ENT_QUOTES, "UTF-8"); ?>"
            >

            <div class="edit-profile-field">
                <label for="login">Логин</label>
                <input
                    type="text"
                    id="login"
                    name="login"
                    maxlength="50"
                    value="<?php echo htmlspecialchars($_POST["login"] ?? $user["login"], ENT_QUOTES, "UTF-8"); ?>"
                    required
                >
            </div>

            <div class="edit-profile-field">
                <label for="email">Электронная почта</label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    value="<?php echo htmlspecialchars($_POST["email"] ?? $user["email"], ENT_QUOTES, "UTF-8"); ?>"
                    required
                >
            </div>

            <div class="edit-profile-divider"></div>

            <h3>Изменение пароля</h3>

            <div class="edit-profile-field">
                <label for="current_password">Текущий пароль</label>
                <input
                    type="password"
                    id="current_password"
                    name="current_password"
                    autocomplete="current-password"
                    required
                >
            </div>

            <div class="edit-profile-field">
                <label for="new_password">Новый пароль</label>
                <input
                    type="password"
                    id="new_password"
                    name="new_password"
                    minlength="6"
                    autocomplete="new-password"
                    placeholder="Оставьте пустым, если не хотите менять"
                >
            </div>

            <div class="edit-profile-field">
                <label for="confirm_password">Повторите новый пароль</label>
                <input
                    type="password"
                    id="confirm_password"
                    name="confirm_password"
                    minlength="6"
                    autocomplete="new-password"
                    placeholder="Повторите новый пароль"
                >
            </div>

            <p class="edit-profile-hint">
                Текущий пароль нужен для подтверждения изменений.
                Если менять пароль не нужно, оставь оба поля нового пароля пустыми.
            </p>

            <button type="submit" class="edit-profile-submit">
                Сохранить изменения
            </button>

        </form>

    </div>

</section>

<?php include "includes/footer.php"; ?>