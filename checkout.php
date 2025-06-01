<?php
session_start();
require_once "config/db_connect.php";

// Check if the user is logged in
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: auth/login.php");
    exit;
}

// Check if the cart is empty
if (empty($_SESSION['cart'])) {
    header("location: cart.php");
    exit;
}

$user_id = $_SESSION['id'];
$total_amount = 0;
$cart_items = $_SESSION['cart'];
$message = '';

// Calculate total amount and fetch latest product details (optional but recommended)
// For simplicity now, we use data from session. For production, re-fetching price/stock is safer.
foreach ($cart_items as $item) {
    $total_amount += $item['price'] * $item['quantity'];
}

// Handle order placement
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['place_order'])) {
    // Begin transaction
    mysqli_begin_transaction($conn);
    $order_placed = false;

    try {
        // Insert into orders table
        $sql_order = "INSERT INTO orders (user_id, total_amount, status) VALUES (?, ?, 'pending')";
        if ($stmt_order = mysqli_prepare($conn, $sql_order)) {
            mysqli_stmt_bind_param($stmt_order, "id", $user_id, $total_amount);
            if (mysqli_stmt_execute($stmt_order)) {
                $order_id = mysqli_insert_id($conn);
                $order_items_inserted = true;

                // Insert into order_items table and update product stock
                $sql_item = "INSERT INTO order_items (order_id, product_id, quantity, price) VALUES (?, ?, ?, ?)";
                $sql_stock = "UPDATE products SET stock = stock - ? WHERE id = ? AND stock >= ?";

                if ($stmt_item = mysqli_prepare($conn, $sql_item) AND $stmt_stock = mysqli_prepare($conn, $sql_stock)) {
                    foreach ($cart_items as $item) {
                        // Check stock again before finalizing
                        $check_stock_sql = "SELECT stock FROM products WHERE id = ?";
                        if($stmt_check_stock = mysqli_prepare($conn, $check_stock_sql)){
                            mysqli_stmt_bind_param($stmt_check_stock, "i", $item['id']);
                            mysqli_stmt_execute($stmt_check_stock);
                            $stock_result = mysqli_stmt_get_result($stmt_check_stock);
                            $product_stock = mysqli_fetch_assoc($stock_result)['stock'];
                             mysqli_stmt_close($stmt_check_stock);

                            if ($product_stock < $item['quantity']) {
                                // Stock insufficient, rollback and show error
                                $message = "Недостаточно товара '" . htmlspecialchars($item['name']) . "' на складе. Доступно: " . $product_stock . ". В корзине: " . $item['quantity'];
                                $order_items_inserted = false;
                                break; // Exit loop
                            }
                        } else {
                             $message = "Ошибка проверки остатка на складе.";
                             $order_items_inserted = false;
                              break;
                        }


                        // Insert order item
                        mysqli_stmt_bind_param($stmt_item, "iiid", $order_id, $item['id'], $item['quantity'], $item['price']);
                        if (!mysqli_stmt_execute($stmt_item)) {
                            $order_items_inserted = false;
                            break; // Exit loop on error
                        }

                        // Update stock
                        mysqli_stmt_bind_param($stmt_stock, "iii", $item['quantity'], $item['id'], $item['quantity']);
                         if (!mysqli_stmt_execute($stmt_stock)) {
                            $order_items_inserted = false;
                            break; // Exit loop on error
                        }
                    }

                     mysqli_stmt_close($stmt_item);
                     mysqli_stmt_close($stmt_stock);

                } else {
                     $message = "Ошибка подготовки запросов для элементов заказа или обновления склада.";
                     $order_items_inserted = false;
                }

                if ($order_items_inserted) {
                    // Commit transaction
                    mysqli_commit($conn);
                    $_SESSION['cart'] = array(); // Clear the cart
                    $order_placed = true;
                    header("location: account/profile.php?order_placed=success"); // Redirect to profile or order confirmation
                    exit();
                } else {
                    // Rollback transaction
                     mysqli_rollback($conn);
                     if(empty($message)){ // Set a generic error if no specific stock error was set
                          $message = "Произошла ошибка при добавлении элементов заказа или обновлении склада.";
                     }
                }

            } else {
                 mysqli_rollback($conn); // Rollback order insert if item insertion failed early
                 $message = "Ошибка при выполнении запроса добавления заказа.";
            }
            mysqli_stmt_close($stmt_order);
        } else {
             $message = "Ошибка при подготовке запроса добавления заказа.";
        }

    } catch (Exception $e) {
         mysqli_rollback($conn);
         $message = "Произошла ошибка при оформлении заказа: " . $e->getMessage();
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Оформление заказа</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="index.php">Shop</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="cart.php">Cart</a>
                    </li>
                     <li class="nav-item">
                        <a class="nav-link" href="faq.php">FAQ</a>
                    </li>
                </ul>
                <ul class="navbar-nav">
                    <?php if(isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true): ?>
                         <li class="nav-item">
                            <a class="nav-link" href="account/profile.php">Profile</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="auth/logout.php">Logout</a>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link" href="auth/login.php">Login</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="auth/register.php">Register</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <h2>Оформление заказа</h2>

        <?php if (!empty($message)): ?>
            <div class="alert alert-danger" role="alert">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($cart_items)): ?>
            <div class="card mb-4">
                <div class="card-header">
                    Детали заказа
                </div>
                <div class="card-body">
                    <ul class="list-group list-group-flush">
                        <?php foreach ($cart_items as $item): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <?php echo htmlspecialchars($item['name']); ?> (x<?php echo $item['quantity']; ?>)
                                <span class="badge bg-primary rounded-pill">$<?php echo number_format($item['price'] * $item['quantity'], 2); ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                     <h5 class="mt-3 text-end">Итого: $<?php echo number_format($total_amount, 2); ?></h5>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    Подтверждение заказа
                </div>
                <div class="card-body">
                    <p>Нажимая кнопку "Подтвердить заказ", вы соглашаетесь с условиями и размещаете заказ.</p>
                    <form action="checkout.php" method="post">
                        <button type="submit" name="place_order" class="btn btn-success">Подтвердить заказ</button>
                        <a href="cart.php" class="btn btn-secondary">Вернуться в корзину</a>
                    </form>
                </div>
            </div>

        <?php else: ?>
            <div class="alert alert-info" role="alert">
                Ваша корзина пуста. Невозможно оформить заказ.
            </div>
        <?php endif; ?>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 