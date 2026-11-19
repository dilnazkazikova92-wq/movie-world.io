
<?php
session_start();
include "includes/db.php";

$error = "";

// Если пользователь уже вошёл в аккаунт
if (isset($_SESSION["user_id"])) {
    if ($_SESSION["user_role"] === "admin") {
        header("Location: admin/index.php");
    } else {
        header("Location: account.php");
    }
    exit;
}

$login = "";
$email = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $login = trim($_POST["login"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";
    $confirmPassword = $_POST["confirm_password"] ?? "";

    if ($login === "" || $email === "" || $password === "" || $confirmPassword === "") {

        $error = "Заполните все поля.";

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $error = "Введите корректный адрес электронной почты.";

    } elseif (strlen($password) < 6) {

        $error = "Пароль должен содержать не менее 6 символов.";

    } elseif ($password !== $confirmPassword) {

        $error = "Пароли не совпадают.";

    } else {

        // Проверяем, существует ли такой логин или почта
        $stmt = $conn->prepare(
            "SELECT id, login, email
             FROM users
             WHERE login = ? OR email = ?
             LIMIT 1"
        );

        $stmt->bind_param("ss", $login, $email);
        $stmt->execute();

        $existingUser = $stmt->get_result()->fetch_assoc();

        $stmt->close();

        if ($existingUser) {

            if ($existingUser["login"] === $login) {
                $error = "Этот логин уже занят. Выберите другой.";
            } else {
                $error = "Пользователь с такой электронной почтой уже существует.";
            }

        } else {

            // Создаём защищённый хеш пароля
            $hashedPassword = password_hash(
                $password,
                PASSWORD_DEFAULT
            );

            $role = "user";
            $notify = 1;

            // Добавляем нового пользователя в базу данных
            $stmt = $conn->prepare(
                "INSERT INTO users
                (login, email, password, role, notify_new_films)
                VALUES (?, ?, ?, ?, ?)"
            );

            $stmt->bind_param(
                "ssssi",
                $login,
                $email,
                $hashedPassword,
                $role,
                $notify
            );

            if ($stmt->execute()) {

                // Выполняем автоматический вход
                session_regenerate_id(true);

                $_SESSION["user_id"] = $stmt->insert_id;
                $_SESSION["user_login"] = $login;
                $_SESSION["user_role"] = "user";

                $stmt->close();

                header("Location: account.php");
                exit;

            } else {

                $error = "Не удалось создать аккаунт. Попробуйте ещё раз.";

            }

            $stmt->close();
        }
    }
}

include "includes/header.php";
?>

<style>
.register-page {
    min-height: 680px;
    display: flex;
    justify-content: center;
    align-items: center;
    padding: 65px 20px;
    box-sizing: border-box;
}

.register-card {
    width: 100%;
    max-width: 510px;
    padding: 45px 48px;
    box-sizing: border-box;
    background: #191920;
    border: 1px solid #30303a;
    border-radius: 18px;
    box-shadow: 0 15px 45px rgba(0, 0, 0, 0.3);
}

.register-icon {
    width: 58px;
    height: 58px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 23px;
    border-radius: 14px;
    background: rgba(255, 204, 77, 0.12);
    color: #ffcc4d;
    font-size: 32px;
}

.register-card .section-label {
    display: block;
    margin-bottom: 12px;
    color: #ffcc4d;
    font-size: 15px;
    font-weight: 700;
    letter-spacing: 2px;
}

.register-card h2 {
    margin: 0 0 14px;
    color: #ffffff;
    font-size: 35px;
    line-height: 1.3;
}

.register-description {
    margin: 0 0 25px;
    color: #a8a8b5;
    font-size: 18px;
    line-height: 1.7;
}

.register-form {
    display: flex;
    flex-direction: column;
    gap: 9px;
}

.register-form label {
    margin-top: 9px;
    color: #eeeeF5;
    font-size: 18px;
    font-weight: 600;
}

.register-form input {
    width: 100%;
    height: 54px;
    box-sizing: border-box;
    padding: 0 17px;
    border: 1px solid #393945;
    border-radius: 9px;
    outline: none;
    background: #111116;
    color: #ffffff;
    font-size: 18px;
}

.register-form input:focus {
    border-color: #ffcc4d;
    box-shadow: 0 0 0 3px rgba(255, 204, 77, 0.1);
}

.register-form input::placeholder {
    color: #777783;
    font-size: 17px;
}

.register-button {
    width: 100%;
    height: 56px;
    margin-top: 20px;
    border: none;
    border-radius: 9px;
    background: #ffcc4d;
    color: #171717;
    font-size: 19px;
    font-weight: 700;
    cursor: pointer;
    transition: 0.2s;
}

.register-button:hover {
    background: #ffdb78;
    transform: translateY(-2px);
}

.register-back {
    display: block;
    margin-top: 25px;
    text-align: center;
    color: #b9b9c5;
    font-size: 17px;
    text-decoration: none;
}

.register-back a {
    color: #ffcc4d;
}

.register-error {
    margin-bottom: 18px;
    padding: 14px 17px;
    border: 1px solid #793c43;
    border-radius: 8px;
    background: rgba(180, 55, 65, 0.12);
    color: #ff8b95;
    font-size: 17px;
    line-height: 1.6;
}

@media (max-width: 500px) {
    .register-card {
        padding: 30px 22px;
    }

    .register-page {
        padding: 35px 15px;
    }

    .register-card h2 {
        font-size: 29px;
    }

    .register-description {
        font-size: 16px;
    }

    .register-form label {
        font-size: 16px;
    }

    .register-form input {
        font-size: 16px;
    }

    .register-back {
        font-size: 15px;
    }
}
</style>

<section class="register-page">

    <div class="register-card">

        <div class="register-icon">✦</div>

        <span class="section-label">
            ПРИСОЕДИНЯЙТЕСЬ К НАМ
        </span>

        <h2>Регистрация</h2>

        <p class="register-description">
            Создайте аккаунт в КиноМире, чтобы сохранять любимые
            фильмы и получать уведомления о новинках.
        </p>

        <?php if ($error !== ""): ?>
            <div class="register-error">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="register.php" class="register-form">

            <label for="login">Логин</label>

            <input
                type="text"
                id="login"
                name="login"
                placeholder="Придумайте логин"
                value="<?php echo htmlspecialchars($login); ?>"
                maxlength="50"
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

            <label for="password">Пароль</label>

            <input
                type="password"
                id="password"
                name="password"
                placeholder="Придумайте пароль"
                minlength="6"
                required
            >

            <label for="confirm_password">Повторите пароль</label>

            <input
                type="password"
                id="confirm_password"
                name="confirm_password"
                placeholder="Повторите пароль"
                minlength="6"
                required
            >

            <button type="submit" class="register-button">
                Создать аккаунт
            </button>

        </form>

        <a href="login.php" class="register-back">
            Уже есть аккаунт? Войти
        </a>

    </div>

</section>

<?php include "includes/footer.php"; ?>
