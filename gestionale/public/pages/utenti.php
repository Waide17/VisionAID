<?php
require_once '../../app/auth/auth.php';
checkAuth();
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Utenti - Gestionale</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 flex">

    <!-- Sidebar -->
    <aside class="w-64 bg-blue-800 text-white h-screen p-5 flex flex-col">
        <h2 class="text-2xl font-bold mb-8">Gestionale</h2>
        <nav class="flex flex-col gap-3">
            <a href="dashboard.php" class="hover:bg-blue-700 p-2 rounded">Dashboard</a>
            <a href="utenti.php" class="hover:bg-blue-700 p-2 rounded bg-blue-700">Utenti</a>
            <a href="clienti.php" class="hover:bg-blue-700 p-2 rounded">Clienti</a>
            <a href="../logout.php" class="hover:bg-red-600 p-2 rounded mt-auto">Logout</a>
        </nav>
    </aside>

    <!-- Main content -->
    <main class="flex-1 p-8">
        <header class="mb-6">
            <h1 class="text-3xl font-bold">Gestione Utenti</h1>
            <p class="text-gray-600 mt-1">Visualizza, aggiungi o modifica utenti</p>
        </header>

        <!-- Tabella utenti -->
        <div class="bg-white p-6 rounded shadow">
            <h2 class="font-bold text-xl mb-4">Elenco utenti</h2>
            <table class="w-full border border-gray-200">
                <thead class="bg-gray-100">
                    <tr>
                        <th class="p-2 border">ID</th>
                        <th class="p-2 border">Nome</th>
                        <th class="p-2 border">Email</th>
                        <th class="p-2 border">Ruolo</th>
                        <th class="p-2 border">Azioni</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="p-2 border">1</td>
                        <td class="p-2 border">Mario Rossi</td>
                        <td class="p-2 border">mario@example.com</td>
                        <td class="p-2 border">Admin</td>
                        <td class="p-2 border">
                            <button class="bg-yellow-500 text-white p-1 rounded">Modifica</button>
                            <button class="bg-red-600 text-white p-1 rounded">Elimina</button>
                        </td>
                    </tr>
                    <tr>
                        <td class="p-2 border">2</td>
                        <td class="p-2 border">Luisa Bianchi</td>
                        <td class="p-2 border">luisa@example.com</td>
                        <td class="p-2 border">User</td>
                        <td class="p-2 border">
                            <button class="bg-yellow-500 text-white p-1 rounded">Modifica</button>
                            <button class="bg-red-600 text-white p-1 rounded">Elimina</button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </main>
</body>
</html>
