<?php
session_start();
require_once "config/db_connect.php";

// Initialize cart in session if it doesn't exist
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = array();
}

// --- Start: Convert old scalar cart format to new array format ---
if (!empty($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $product_id => &$item) { // Use reference for direct modification
        // Check if the item is in the old scalar format (just quantity)
        if (is_numeric($item)) {
            $old_quantity = $item;
            
            // Fetch product details from database
            $sql = "SELECT name, price FROM products WHERE id = ?";
            if ($stmt = mysqli_prepare($conn, $sql)) {
                mysqli_stmt_bind_param($stmt, "i", $product_id);
                if (mysqli_stmt_execute($stmt)) {
                    $result = mysqli_stmt_get_result($stmt);
                    if ($product = mysqli_fetch_assoc($result)) {
                        // Convert to new array format
                        $item = array(
                            'id' => $product_id,
                            'name' => $product['name'],
                            'price' => $product['price'],
                            'quantity' => $old_quantity
                        );
                    } else {
                        // Product not found, remove from cart
                        unset($_SESSION['cart'][$product_id]);
                    }
                    mysqli_free_result($result);
                } else {
                    // Database error during fetch, log or handle appropriately
                    error_log("Database error fetching product for cart conversion: " . mysqli_error($conn));
                     unset($_SESSION['cart'][$product_id]); // Remove problematic item
                }
                 mysqli_stmt_close($stmt);
            } else {
                 // Database error preparing statement
                 error_log("Database error preparing statement for cart conversion: " . mysqli_error($conn));
                  unset($_SESSION['cart'][$product_id]); // Remove problematic item
            }
        }
    }
     unset($item); // Unset the reference
}
// --- End: Convert old scalar cart format ---

$message = '';

// Handle add to cart, remove from cart, update quantity
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
        $quantity = isset($_POST['quantity']) ? intval($_POST['quantity']) : 1;

        switch ($action) {
            case 'add':
                if ($product_id > 0) {
                    // Check if product exists and is in stock
                    $sql = "SELECT id, name, price, stock FROM products WHERE id = ?";
                    if ($stmt = mysqli_prepare($conn, $sql)) {
                        mysqli_stmt_bind_param($stmt, "i", $product_id);
                        if (mysqli_stmt_execute($stmt)) {
                            $result = mysqli_stmt_get_result($stmt);
                            if ($product = mysqli_fetch_assoc($result)) {
                                if ($product['stock'] > 0) {
                                    if (isset($_SESSION['cart'][$product_id])) {
                                        // Item already in cart, check if adding more exceeds stock
                                        // Since we converted old format, we can directly access ['quantity']
                                        if (($_SESSION['cart'][$product_id]['quantity'] + $quantity) <= $product['stock']) {
                                            $_SESSION['cart'][$product_id]['quantity'] += $quantity;
                                            $message = "Количество товара обновлено в корзине.";
                                        } else {
                                            $message = "Недостаточно товара на складе для добавления такого количества.";
                                        }
                                    } else {
                                        // Add new item to cart
                                        if ($quantity <= $product['stock']) {
                                            $_SESSION['cart'][$product_id] = array(
                                                'id' => $product['id'],
                                                'name' => $product['name'],
                                                'price' => $product['price'],
                                                'quantity' => $quantity
                                            );
                                            $message = "Товар добавлен в корзину.";
                                        } else {
                                             $message = "Недостаточно товара на складе для добавления такого количества.";
                                        }
                                    }
                                } else {
                                     $message = "Товар временно отсутствует на складе.";
                                }
                            } else {
                                $message = "Товар не найден.";
                            }
                            mysqli_free_result($result);
                        } else {
                             $message = "Ошибка выполнения запроса: " . mysqli_error($conn);
                        }
                        mysqli_stmt_close($stmt);
                    } else {
                        $message = "Ошибка подготовки запроса: " . mysqli_error($conn);
                    }
                } else {
                    $message = "Неверный ID продукта.";
                }
                break;
            
            case 'remove':
                if ($product_id > 0) {
                    if (isset($_SESSION['cart'][$product_id])) {
                        unset($_SESSION['cart'][$product_id]);
                        $message = "Товар удален из корзины.";
                    }
                }
                break;

            case 'update':
                 if ($product_id > 0 && $quantity >= 0) {
                     // Check if product exists and if updated quantity exceeds stock
                     $sql = "SELECT stock FROM products WHERE id = ?";
                      if ($stmt = mysqli_prepare($conn, $sql)) {
                         mysqli_stmt_bind_param($stmt, "i", $product_id);
                         if (mysqli_stmt_execute($stmt)) {
                             $result = mysqli_stmt_get_result($stmt);
                             if ($product = mysqli_fetch_assoc($result)) {
                                 if ($quantity <= $product['stock']) {
                                      if ($quantity > 0) {
                                          $_SESSION['cart'][$product_id]['quantity'] = $quantity;
                                          $message = "Количество товара обновлено.";
                                      } else {
                                          unset($_SESSION['cart'][$product_id]);
                                           $message = "Товар удален из корзины.";
                                      }
                                 } else {
                                      $message = "Недостаточно товара на складе для такого количества.";
                                      // Optionally, reset quantity to max stock or previous value
                                      // if (isset($_SESSION['cart'][$product_id])) {
                                      //     $_SESSION['cart'][$product_id]['quantity'] = $product['stock'];
                                      // }
                                 }
                             } else {
                                 $message = "Товар не найден.";
                             }
                              mysqli_free_result($result);
                         } else {
                             $message = "Ошибка выполнения запроса: " . mysqli_error($conn);
                         }
                         mysqli_stmt_close($stmt);
                     } else {
                         $message = "Ошибка подготовки запроса: " . mysqli_error($conn);
                     }
                 } else {
                      $message = "Неверные данные для обновления корзины.";
                 }
                break;
        }
         // Redirect to prevent form resubmission
        header("location: cart.php?message=" . urlencode($message));
        exit();
    }
}

// Calculate total amount
$total_amount = 0;
if (!empty($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $item) {
        // Ensure item is in array format before accessing 'price' and 'quantity'
        if (is_array($item) && isset($item['price']) && isset($item['quantity'])) {
             $total_amount += $item['price'] * $item['quantity'];
        } else {
            // Log or handle cases where item is unexpectedly not an array after conversion attempt
            error_log("Unexpected cart item format after conversion attempt: " . print_r($item, true));
            // Optionally remove the malformed item:
            // unset($_SESSION['cart'][$item['id']]); // This might need product_id if item structure is broken
        }
    }
}


?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Корзина</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .cart-item {
            transition: background-color 0.2s;
        }
        .cart-item:hover {
            background-color: #f8f9fa;
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
                        <a class="nav-link" href="index.php">Главная</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="cart.php">Корзина</a>
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
        <h2>Корзина</h2>

         <?php if (!empty($message) || isset($_GET['message'])): ?>
            <div class="alert alert-info alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($message ?: $_GET['message']); ?>
                 <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if (!empty($_SESSION['cart'])): ?>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Товар</th>
                            <th>Цена</th>
                            <th>Количество</th>
                            <th>Сумма</th>
                            <th>Действия</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($_SESSION['cart'] as $product_id => $item): ?>
                            <tr class="cart-item">
                                <td><?php echo htmlspecialchars($item['name']); ?></td>
                                <td><?php echo number_format($item['price'], 2); ?> ₽</td>
                                <td>
                                    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" class="d-flex align-items-center">
                                        <input type="hidden" name="action" value="update">
                                        <input type="hidden" name="product_id" value="<?php echo $product_id; ?>">
                                        <input type="number" name="quantity" value="<?php echo $item['quantity']; ?>" 
                                               min="1" class="form-control form-control-sm" style="width: 70px;">
                                        <button type="submit" class="btn btn-sm btn-outline-primary ms-2">
                                            <i class="fas fa-sync-alt"></i>
                                        </button>
                                    </form>
                                </td>
                                <td><?php echo number_format($item['price'] * $item['quantity'], 2); ?> ₽</td>
                                <td>
                                    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" style="display: inline;">
                                        <input type="hidden" name="action" value="remove">
                                        <input type="hidden" name="product_id" value="<?php echo $product_id; ?>">
                                        <button type="submit" class="btn btn-sm btn-danger">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="3" class="text-end"><strong>Итого:</strong></td>
                            <td><strong><?php echo number_format($total_amount, 2); ?> ₽</strong></td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <div class="d-flex justify-content-between mt-4">
                <a href="index.php" class="btn btn-outline-primary">
                    <i class="fas fa-arrow-left"></i> Продолжить покупки
                </a>
                <?php if(isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true): ?>
                    <a href="checkout.php" class="btn btn-success">
                        Оформить заказ <i class="fas fa-arrow-right"></i>
                    </a>
                <?php else: ?>
                    <div class="alert alert-warning mb-0">
                        Для оформления заказа необходимо <a href="auth/login.php">войти</a> или <a href="auth/register.php">зарегистрироваться</a>
                    </div>
                <?php endif; ?>
            </div>

        <?php else: ?>
            <div class="alert alert-info">
                Ваша корзина пуста. <a href="index.php">Перейти к покупкам</a>
            </div>
        <?php endif; ?>

    </div>

    <?php include 'includes/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 