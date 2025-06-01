<?php
session_start();
require_once "config/db_auto_init_and_connect.php";

// Get all products
$sql = "SELECT * FROM products ORDER BY created_at DESC";
$result = mysqli_query($conn, $sql);

// Get slider images
$slider_images = [];
$sql_slider = "SELECT image_path, caption FROM slider_images ORDER BY created_at ASC";
if ($result_slider = mysqli_query($conn, $sql_slider)) {
    while ($row_slider = mysqli_fetch_assoc($result_slider)) {
        $slider_images[] = $row_slider;
    }
    mysqli_free_result($result_slider);
}

$message = '';
if (!$result) {
    $message = "Ошибка при получении списка продуктов: " . mysqli_error($conn);
}

$booking_message = '';
$booked_slots = [];

// Handle booking form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["book_time"])) {

    // Check if user is logged in
    if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
        $booking_message = "<div class=\"alert alert-danger\">Для записи необходимо авторизоваться.</div>";
    } else {
        $user_id = $_SESSION["id"];
        $booking_date = trim($_POST["booking_date"]);
        $booking_time = trim($_POST["booking_time"]);

        if (empty($booking_date) || empty($booking_time)) {
            $booking_message = "<div class=\"alert alert-danger\">Пожалуйста, выберите дату и время.</div>";
        } else {
            $booking_datetime_str = $booking_date . ' ' . $booking_time;
            $booking_datetime = DateTime::createFromFormat('Y-m-d H:i', $booking_datetime_str);

            // Validate date and time format and if it's in the past
            if (!$booking_datetime || $booking_datetime < new DateTime()) {
                $booking_message = "<div class=\"alert alert-danger\">Пожалуйста, выберите корректную дату и время в будущем.</div>";
            } else {
                // Format for database
                $booking_datetime_db = $booking_datetime->format('Y-m-d H:i:s');

                // Check if slot is already booked (double-check with a select query)
                $sql_check = "SELECT id FROM bookings WHERE booking_datetime = ?";
                if ($stmt_check = mysqli_prepare($conn, $sql_check)) {
                     mysqli_stmt_bind_param($stmt_check, "s", $booking_datetime_db);
                     mysqli_stmt_execute($stmt_check);
                     mysqli_stmt_store_result($stmt_check);

                     if (mysqli_stmt_num_rows($stmt_check) > 0) {
                         $booking_message = "<div class=\"alert alert-warning\">Выбранное время уже занято. Пожалуйста, выберите другое.</div>";
                     } else {
                         // Slot is available, insert booking
                         $sql_insert = "INSERT INTO bookings (user_id, booking_datetime) VALUES (?, ?)";
                         if ($stmt_insert = mysqli_prepare($conn, $sql_insert)) {
                             // Debug information before booking insert
                             error_log("Attempting to insert booking:");
                             error_log("Debug - User ID from session: " . (isset($_SESSION["id"]) ? $_SESSION["id"] : "NOT SET"));
                             error_log("Debug - Booking Datetime: " . $booking_datetime_db);
                             error_log("Debug - SQL INSERT: " . $sql_insert);

                             mysqli_stmt_bind_param($stmt_insert, "is", $user_id, $booking_datetime_db);

                             if (mysqli_stmt_execute($stmt_insert)) {
                                 $booking_message = "<div class=\"alert alert-success\">Запись успешно создана!</div>";
                                 // Refresh booked slots list after successful booking
                                 $booked_slots[] = $booking_datetime_db;
                                 // Sort to keep it ordered
                                 sort($booked_slots);
                             } else {
                                 // Check for duplicate entry error specifically (due to UNIQUE index)
                                 if (mysqli_errno($conn) == 1062) { // Error code for Duplicate entry
                                      $booking_message = "<div class=\"alert alert-warning\">Выбранное время уже занято (повторная проверка).</div>";
                                 } else {
                                     echo "Ошибка выполнения запроса INSERT в bookings: " . mysqli_stmt_error($stmt_insert); // Show DB error for debugging
                                      $booking_message = "<div class=\"alert alert-danger\">Произошла ошибка при создании записи.</div>";
                                 }
                             }
                             mysqli_stmt_close($stmt_insert);
                         } else {
                             echo "Ошибка подготовки запроса INSERT в bookings: " . mysqli_error($conn); // Show DB error for debugging
                              $booking_message = "<div class=\"alert alert-danger\">Произошла ошибка при подготовке запроса.</div>";
                         }
                     }
                     mysqli_stmt_close($stmt_check);
                } else {
                    echo "Ошибка подготовки запроса SELECT для проверки: " . mysqli_error($conn); // Show DB error for debugging
                     $booking_message = "<div class=\"alert alert-danger\">Произошла ошибка при проверке доступности времени.</div>";
                }
            }
        }
    }
}

mysqli_close($conn); // Close the database connection
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Магазин</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .product-card {
            transition: transform 0.2s;
        }
        .product-card:hover {
            transform: translateY(-5px);
        }
        .cart-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            padding: 5px 10px;
            border-radius: 50%;
            background: red;
            color: white;
        }
         .product-image-main {
            height: 200px;
            object-fit: cover;
        }
        /* CSS for the slider images */
        .carousel-item img {
            height: 400px; /* Adjust the height as needed */
            object-fit: cover;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="index.php">Магазин</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="index.php">Главная</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="cart.php">Корзина</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="faq.php">Вопросы и ответы</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="about.php">О нас</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="contacts.php">Контакты</a>
                    </li>
                </ul>
                <ul class="navbar-nav">
                    <?php if(isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="cart.php">
                                <i class="fas fa-shopping-cart"></i>
                                <?php
                                if(isset($_SESSION['cart']) && count($_SESSION['cart']) > 0) {
                                    echo '<span class="cart-badge">' . count($_SESSION['cart']) . '</span>';
                                }
                                ?>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="account/profile.php">Личный кабинет</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="auth/logout.php">Выйти</a>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link" href="auth/login.php">Войти</a>
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
         <?php if (!empty($message)): ?>
            <div class="alert alert-warning" role="alert">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($slider_images)): ?>
        <div id="carouselExampleIndicators" class="carousel slide mb-4" data-bs-ride="carousel">
            <div class="carousel-indicators">
                <?php foreach ($slider_images as $key => $image): ?>
                    <button type="button" data-bs-target="#carouselExampleIndicators" data-bs-slide-to="<?php echo $key; ?>" class="<?php echo ($key === 0) ? 'active' : ''; ?>" aria-current="<?php echo ($key === 0) ? 'true' : ''; ?>" aria-label="Слайд <?php echo $key + 1; ?>"></button>
                <?php endforeach; ?>
            </div>
            <div class="carousel-inner">
                <?php foreach ($slider_images as $key => $image): ?>
                <div class="carousel-item <?php echo ($key === 0) ? 'active' : ''; ?>">
                    <img src="<?php echo htmlspecialchars($image['image_path']); ?>" class="d-block w-100" alt="<?php echo htmlspecialchars($image['caption'] ?? 'Изображение слайдера'); ?>">
                    <?php if (!empty($image['caption'])): ?>
                    <div class="carousel-caption d-none d-md-block">
                        <h5><?php echo htmlspecialchars($image['caption']); ?></h5>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <button class="carousel-control-prev" type="button" data-bs-target="#carouselExampleIndicators" data-bs-slide="prev">
                <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                <span class="visually-hidden">Предыдущий</span>
            </button>
            <button class="carousel-control-next" type="button" data-bs-target="#carouselExampleIndicators" data-bs-slide="next">
                <span class="carousel-control-next-icon" aria-hidden="true"></span>
                <span class="visually-hidden">Следующий</span>
            </button>
        </div>
        <?php endif; ?>

        <div class="row">
            <?php
            if ($result && mysqli_num_rows($result) > 0) {
                while($row = mysqli_fetch_assoc($result)) {
                    ?>
                    <div class="col-md-4 mb-4">
                        <div class="card product-card h-100">
                            <?php if(!empty($row['image_path'])): ?>
                                <img src="<?php echo htmlspecialchars($row['image_path']); ?>" class="card-img-top product-image-main" alt="<?php echo htmlspecialchars($row['name']); ?>">
                            <?php else: ?>
                                <img src="https://via.placeholder.com/300x200" class="card-img-top product-image-main" alt="Изображение недоступно">
                            <?php endif; ?>
                            <div class="card-body">
                                <h5 class="card-title"><?php echo htmlspecialchars($row['name']); ?></h5>
                                <p class="card-text"><?php echo htmlspecialchars($row['description']); ?></p>
                                <p class="card-text"><strong>Цена: <?php echo number_format($row['price'], 2); ?> ₽</strong></p>
                                <p class="card-text"><small class="text-muted">В наличии: <?php echo $row['stock']; ?> шт.</small></p>
                                <?php if($row['stock'] > 0): ?>
                                    <form action="cart.php" method="post">
                                        <input type="hidden" name="product_id" value="<?php echo $row['id']; ?>">
                                        <input type="hidden" name="action" value="add">
                                        <input type="hidden" name="product_name" value="<?php echo htmlspecialchars($row['name']); ?>">
                                        <input type="hidden" name="product_price" value="<?php echo $row['price']; ?>">
                                        <button type="submit" class="btn btn-primary">Добавить в корзину</button>
                                    </form>
                                <?php else: ?>
                                    <button class="btn btn-secondary" disabled>Нет в наличии</button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php
                }
            } else {
                echo '<div class="col-12"><p class="text-center">Товары не найдены.</p></div>';
            }
            ?>
        </div>

        <div class="container mt-4">
            <h2>Запись на консультацию</h2>

            <?php echo $booking_message; ?>

            <?php if (isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true): ?>
                <div class="card mb-4">
                    <div class="card-header">Записаться на консультацию</div>
                    <div class="card-body">
                        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                            <div class="row g-3 align-items-center">
                                <div class="col-md-4">
                                    <label for="booking_date" class="form-label">Выберите дату:</label>
                                    <input type="date" class="form-control" id="booking_date" name="booking_date" required>
                                </div>
                                <div class="col-md-4">
                                    <label for="booking_time" class="form-label">Выберите время:</label>
                                    <input type="time" class="form-control" id="booking_time" name="booking_time" required>
                                </div>
                                <div class="col-md-4 d-flex align-items-end">
                                    <button type="submit" name="book_time" class="btn btn-primary w-100">Записаться</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            <?php else: ?>
                <div class="alert alert-info" role="alert">
                    Для записи на консультацию необходимо <a href="auth/login.php">войти</a> или <a href="auth/register.php">зарегистрироваться</a>.
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php include 'includes/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>