<?php
session_start();
require_once "config/db_connect.php";

$message = '';
$callback_message = '';
$callback_name = '';
$callback_phone = '';

// Handle callback request submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['callback_submit'])) {
    $callback_name = trim($_POST['callback_name']);
    $callback_phone = trim($_POST['callback_phone']);

    if (empty($callback_name) || empty($callback_phone)) {
        $callback_message = "<div class=\"alert alert-danger\">Пожалуйста, заполните все поля формы обратного звонка.</div>";
    } elseif (!preg_match('/^\+7\(\d{3}\)-\d{3}-\d{2}-\d{2}$/', $callback_phone)) {
         $callback_message = "<div class=\"alert alert-danger\">Неверный формат номера телефона. Используйте формат: +7(XXX)-XXX-XX-XX</div>";
    } else {
        // Save callback request to database
        $sql = "INSERT INTO callback_requests (name, phone) VALUES (?, ?)";
        if ($stmt = mysqli_prepare($conn, $sql)) {
            mysqli_stmt_bind_param($stmt, "ss", $callback_name, $callback_phone);

            if (mysqli_stmt_execute($stmt)) {
                $callback_message = "<div class=\"alert alert-success\">Спасибо за ваш запрос на обратный звонок! Мы свяжемся с вами в ближайшее время.</div>";
                // Clear form fields
                $callback_name = '';
                $callback_phone = '';
            } else {
                $callback_message = "<div class=\"alert alert-danger\">Произошла ошибка при отправке запроса. Пожалуйста, попробуйте позже.</div>";
            }
            mysqli_stmt_close($stmt);
        } else {
             $callback_message = "<div class=\"alert alert-danger\">Произошла ошибка при подготовке запроса. Пожалуйста, попробуйте позже.</div>";
        }
    }
}

?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Контакты - Магазин</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="index.php">Логотип</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">Главная</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="cart.php">Корзина</a>
                    </li>
                     <li class="nav-item">
                        <a class="nav-link" href="faq.php">FAQ</a>
                    </li>
                     <li class="nav-item">
                        <a class="nav-link" href="about.php">О нас</a>
                    </li>
                     <li class="nav-item">
                        <a class="nav-link active" href="contacts.php">Контакты</a>
                    </li>
                </ul>
                <ul class="navbar-nav">
                    <?php if(isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true): ?>
                         <li class="nav-item">
                            <a class="nav-link" href="account/profile.php">Профиль</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="auth/logout.php">Выйти</a>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                                <a class="nav-link" href="auth/login.php">Авторизация</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="auth/register.php">Регистрация</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <h2>Контакты</h2>

         <?php if (!empty($message)): ?>
            <div class="alert <?php echo strpos($message, 'Спасибо') !== false ? 'alert-success' : 'alert-danger'; ?>" role="alert">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-md-6">
                <p>Если у вас есть вопросы или предложения, пожалуйста, свяжитесь с нами:</p>
                <ul>
                    <li>Email: info@yourshop.com</li>
                    <li>Телефон: +123 456 7890</li>
                    <li>Адрес: Улица Примерная, 1, Город, Страна</li>
                </ul>
            </div>
            <div class="col-md-6">
                <h4>Заказать обратный звонок</h4>
                <?php echo $callback_message; ?>
                <form action="contacts.php" method="post">
                    <input type="hidden" name="callback_submit" value="1">
                    <div class="mb-3">
                        <label for="callback_name" class="form-label">Ваше имя</label>
                        <input type="text" class="form-control" id="callback_name" name="callback_name" value="<?php echo htmlspecialchars($callback_name); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="callback_phone" class="form-label">Номер телефона</label>
                        <input type="tel" class="form-control" id="callback_phone" name="callback_phone" value="<?php echo htmlspecialchars($callback_phone); ?>" placeholder="+7(XXX)-XXX-XX-XX" required>
                    </div>
                    <button type="submit" class="btn btn-primary">Заказать звонок</button>
                </form>
            </div>
        </div>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Маска для телефона в форме обратного звонка
        document.querySelector('#callback_phone').addEventListener('input', function(e) {
            let x = e.target.value.replace(/\D/g, '').match(/(\d{0,1})(\d{0,3})(\d{0,3})(\d{0,2})(\d{0,2})/);
            e.target.value = !x[2] ? x[1] : '+7(' + x[2] + (x[3] ? ')-' + x[3] : '') + (x[4] ? '-' + x[4] : '') + (x[5] ? '-' + x[5] : '');
        });
    </script>
</body>
</html> 