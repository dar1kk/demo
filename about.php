<?php
session_start();
require_once "config/db_connect.php";
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>О нас - Магазин</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
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
                        <a class="nav-link" href="cart.php">Корзина</a>
                    </li>
                     <li class="nav-item">
                        <a class="nav-link" href="faq.php">FAQ</a>
                    </li>
                     <li class="nav-item">
                        <a class="nav-link active" href="about.php">О нас</a>
                    </li>
                     <li class="nav-item">
                        <a class="nav-link" href="contacts.php">Контакты</a>
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
        <h2>О нас</h2>

        <p>Здесь вы можете рассказать о вашей компании, ее истории, миссии, ценностях и т.д.</p>
        
        <h3>Наша история</h3>
        <p>Краткий обзор истории вашей компании.</p>

        <h3>Наша миссия</h3>
        <p>В чем заключается цель вашей компании?</p>

        <h3>Наши ценности</h3>
        <ul>
            <li>Ценность 1</li>
            <li>Ценность 2</li>
            <li>Ценность 3</li>
        </ul>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 