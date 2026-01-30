<?php
session_start();
require_once '../app/config/database.php';
require_once '../app/auth/auth.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);

    // Funzione che verifica login (definita in auth.php)
    $user = loginUser($email, $password);

    if ($user) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        header('Location: pages/dashboard.php');
        exit;
    } else {
        $error = 'Email o password errati';
    }
}
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Login Gestionale</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 flex items-center justify-center h-screen">
    <div class="bg-white p-8 rounded shadow-md w-96">
        <h1 class="text-2xl font-bold mb-6 text-center">Accedi</h1>

        <?php if ($error): ?>
            <div class="bg-red-100 text-red-700 p-2 rounded mb-4"><?= $error ?></div>
        <?php endif; ?>

        <form method="POST">
            <label class="block mb-2 font-semibold">Email</label>
            <input type="email" name="email" class="w-full p-2 border rounded mb-4" required>

            <label class="block mb-2 font-semibold">Password</label>
            <input type="password" name="password" class="w-full p-2 border rounded mb-4" required>

            <button type="submit" class="w-full bg-blue-600 text-white p-2 rounded hover:bg-blue-700">
                Accedi
            </button>
        </form>
    </div>
</body>
</html>
