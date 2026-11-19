
<?php
session_start();
include "includes/db.php";

$error = "";

// Если уже выполнен вход
if (isset($_SESSION["user_id"])) {
    if ($_SESSION["user_role"] === "admin") {
        header("Location: admin/index.php");
    } else {
        header("Location: account.php");
    }
    exit;
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $login = trim($_POST["login"] ?? "");
    $password = $_POST["password"] ?? "";

    if ($login === "" || $password === "") {
        $error = "Заполните все поля.";
    } else {

        // Сначала проверяем администратора
        $stmt = $conn->prepare(
            "SELECT id, login, password, role
             FROM users
             WHERE role = 'admin'
             LIMIT 1"
        );

        $stmt->execute();
        $admin = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($admin && $login === $admin["login"]) {

            // Администратор должен ввести свой специальный пароль
            if (password_verify($password, $admin["password"])) {

                session_regenerate_id(true);

                $_SESSION["user_id"] = $admin["id"];
                $_SESSION["user_login"] = $admin["login"];
                $_SESSION["user_role"] = "admin";

                header("Location: admin/index.php");
                exit;

            } else {
                $error = "Неверный пароль администратора.";
            }

        } else {

            // Проверяем обычного пользователя
            $stmt = $conn->prepare(
                "SELECT id, login, role
                 FROM users
                 WHERE login = ? AND role = 'user'"
            );

            $stmt->bind_param("s", $login);
            $stmt->execute();

            $user = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            // Если пользователя ещё нет, создаём аккаунт
            if (!$user) {

                $hashedPassword = password_hash(
                    $password,
                    PASSWORD_DEFAULT
                );

                // Для автоматически созданного аккаунта
                // создаём технический адрес электронной почты
                $email = "user_" . bin2hex(random_bytes(6))
                       . "@kinomir.local";

                $notify = 1;
                $role = "user";

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

                    $user = [
                        "id" => $stmt->insert_id,
                        "login" => $login,
                        "role" => "user"
                    ];

                } else {
                    $error = "Не удалось создать аккаунт. Возможно, такой логин уже занят.";
                }

                $stmt->close();
            }

            // Вход обычного пользователя
            if ($user && $error === "") {

                session_regenerate_id(true);

                $_SESSION["user_id"] = $user["id"];
                $_SESSION["user_login"] = $user["login"];
                $_SESSION["user_role"] = "user";

                header("Location: account.php");
                exit;
            }
        }
    }
}

include "includes/header.php";
?>

<style>
.login-page {
    min-height: 620px;
    display: flex;
    justify-content: center;
    align-items: center;
    padding: 65px 20px;
    box-sizing: border-box;
}

.login-card {
    width: 100%;
    max-width: 510px;
    padding: 48px;
    box-sizing: border-box;
    background: #191920;
    border: 1px solid #30303a;
    border-radius: 18px;
    box-shadow: 0 15px 45px rgba(0, 0, 0, 0.3);
}

.login-icon {
    width: 58px;
    height: 58px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 25px;
    border-radius: 14px;
    background: rgba(255, 204, 77, 0.12);
    color: #ffcc4d;
    font-size: 32px;
}

.login-card .section-label {
    display: block;
    margin-bottom: 12px;
    color: #ffcc4d;
    font-size: 15px;
    font-weight: 700;
    letter-spacing: 2px;
}

.login-card h2 {
    margin: 0 0 15px;
    color: #ffffff;
    font-size: 35px;
    line-height: 1.3;
}

.login-description {
    margin: 0 0 30px;
    color: #a8a8b5;
    font-size: 18px;
    line-height: 1.8;
}

.login-form {
    display: flex;
    flex-direction: column;
    gap: 11px;
}

.login-form label {
    margin-top: 10px;
    color: #eeeeF5;
    font-size: 18px;
    font-weight: 600;
}

.login-form input {
    width: 100%;
    height: 56px;
    box-sizing: border-box;
    padding: 0 17px;
    border: 1px solid #393945;
    border-radius: 9px;
    outline: none;
    background: #111116;
    color: #ffffff;
    font-size: 18px;
}

.login-form input:focus {
    border-color: #ffcc4d;
    box-shadow: 0 0 0 3px rgba(255, 204, 77, 0.1);
}

.login-form input::placeholder {
    color: #777783;
    font-size: 17px;
}

.login-button {
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

.login-button:hover {
    background: #ffdb78;
    transform: translateY(-2px);
}

.login-back {
    display: block;
    margin-top: 27px;
    text-align: center;
    color: #b9b9c5;
    font-size: 17px;
    text-decoration: none;
}

.login-back a {
    color: #ffcc4d;
}

.login-error {
    margin-bottom: 20px;
    padding: 14px 17px;
    border: 1px solid #793c43;
    border-radius: 8px;
    background: rgba(180, 55, 65, 0.12);
    color: #ff8b95;
    font-size: 17px;
    line-height: 1.6;
}

@media (max-width: 500px) {
    .login-card {
        padding: 30px 22px;
    }

    .login-page {
        padding: 35px 15px;
    }

    .login-card h2 {
        font-size: 29px;
    }

    .login-description {
        font-size: 16px;
    }

    .login-form label {
        font-size: 16px;
    }

    .login-form input {
        font-size: 16px;
    }

    .login-back {
        font-size: 15px;
    }
}
</style>

<section class="login-page">

    <div class="login-card">

        <div class="login-icon">✦</div>

        <span class="section-label">
            ДОБРО ПОЖАЛОВАТЬ
        </span>

        <h2>Вход в КиноМир</h2>

        <p class="login-description">
            Введи логин и пароль, чтобы перейти
            в личный кабинет и пользоваться возможностями сайта.
        </p>

        <?php if ($error !== ""): ?>
            <div class="login-error">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="login.php" class="login-form">

            <label for="login">Логин</label>

            <input
                type="text"
                id="login"
                name="login"
                placeholder="Введите логин"
                value="<?php echo htmlspecialchars($_POST["login"] ?? ""); ?>"
                required
            >

            <label for="password">Пароль</label>

            <input
                type="password"
                id="password"
                name="password"
                placeholder="Введите пароль"
                required
            >

            <button type="submit" class="login-button">
                Войти
            </button>

        </form>

        <a href="register.php" class="login-back">
            Нет аккаунта? Зарегистрироваться
        </a>

    </div>

</section>

<?php include "includes/footer.php"; ?>
