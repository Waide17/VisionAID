<?php
// pages/nuovo_ticket.php
require_once '../../app/auth/auth.php';
checkAuth();

$db     = getDB();
$user   = currentUser();
$error  = '';
$success = '';

$categorie = $db->query("SELECT * FROM categorie ORDER BY nome")->fetchAll();
$operatori = $db->query("SELECT id, nome FROM utenti WHERE attivo = 1 ORDER BY nome")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $titolo       = trim($_POST['titolo'] ?? '');
    $descrizione  = trim($_POST['descrizione'] ?? '');
    $priorita     = $_POST['priorita'] ?? 'media';
    $categoria_id = $_POST['categoria_id'] ?? null;
    $rich_nome    = trim($_POST['richiedente_nome'] ?? '');
    $rich_email   = trim($_POST['richiedente_email'] ?? '');
    $assegnato_a  = $_POST['assegnato_a'] ?: null;

    if (!$titolo || !$descrizione) {
        $error = 'Titolo e descrizione sono obbligatori.';
    } else {
        $stmt = $db->prepare("INSERT INTO tickets
            (titolo, descrizione, priorita, categoria_id, richiedente_nome, richiedente_email, assegnato_a, creato_da)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$titolo, $descrizione, $priorita, $categoria_id ?: null, $rich_nome, $rich_email, $assegnato_a, $user['id']]);
        $id = $db->lastInsertId();
        header("Location: ticket_detail.php?id=$id&new=1");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nuovo Ticket — TicketDesk</title>
    <link rel="stylesheet" href="../styles/dashboard.css">
</head>
<body>
<div class="app-layout">
    <?php require_once '../app/partials/sidebar.php'; ?>

    <main class="main-content" style="max-width:820px">
        <div class="page-header">
            <div>
                <h1 class="page-title">Nuovo Ticket</h1>
                <p class="page-sub">Compila il modulo per aprire un nuovo ticket di assistenza</p>
            </div>
            <a href="tickets.php" class="btn btn-secondary">← Torna ai ticket</a>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error">⚠️ <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <div class="card">
            <form method="POST">
                <div class="form-grid">

                    <!-- Titolo -->
                    <div class="form-group full">
                        <label for="titolo">Titolo ticket *</label>
                        <input type="text" id="titolo" name="titolo" placeholder="Descrivi brevemente il problema..." value="<?= htmlspecialchars($_POST['titolo'] ?? '') ?>" required>
                    </div>

                    <!-- Richiedente -->
                    <div class="form-group">
                        <label for="richiedente_nome">Nome richiedente</label>
                        <input type="text" id="richiedente_nome" name="richiedente_nome" placeholder="Es. Mario Rossi" value="<?= htmlspecialchars($_POST['richiedente_nome'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label for="richiedente_email">Email richiedente</label>
                        <input type="email" id="richiedente_email" name="richiedente_email" placeholder="mario@azienda.it" value="<?= htmlspecialchars($_POST['richiedente_email'] ?? '') ?>">
                    </div>

                    <!-- Priorità e Categoria -->
                    <div class="form-group">
                        <label for="priorita">Priorità</label>
                        <select id="priorita" name="priorita">
                            <option value="bassa"   <?= (($_POST['priorita']??''))==='bassa'?'selected':'' ?>>🟢 Bassa</option>
                            <option value="media"   <?= (($_POST['priorita']??'media'))==='media'?'selected':'' ?>>🔵 Media</option>
                            <option value="alta"    <?= (($_POST['priorita']??''))==='alta'?'selected':'' ?>>🟡 Alta</option>
                            <option value="critica" <?= (($_POST['priorita']??''))==='critica'?'selected':'' ?>>🔴 Critica</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="categoria_id">Categoria</label>
                        <select id="categoria_id" name="categoria_id">
                            <option value="">— Nessuna categoria —</option>
                            <?php foreach ($categorie as $c): ?>
                            <option value="<?= $c['id'] ?>" <?= (($_POST['categoria_id']??'')==$c['id'])?'selected':'' ?>><?= htmlspecialchars($c['nome']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Assegnato a -->
                    <div class="form-group">
                        <label for="assegnato_a">Assegna a</label>
                        <select id="assegnato_a" name="assegnato_a">
                            <option value="">— Non assegnato —</option>
                            <?php foreach ($operatori as $op): ?>
                            <option value="<?= $op['id'] ?>" <?= (($_POST['assegnato_a']??'')==$op['id'])?'selected':'' ?>><?= htmlspecialchars($op['nome']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Descrizione -->
                    <div class="form-group full">
                        <label for="descrizione">Descrizione dettagliata *</label>
                        <textarea id="descrizione" name="descrizione" rows="6" placeholder="Descrivi il problema nel dettaglio: cosa succede, quando succede, quali passi sono già stati tentati..."><?= htmlspecialchars($_POST['descrizione'] ?? '') ?></textarea>
                    </div>

                    <!-- Submit -->
                    <div class="form-group full" style="flex-direction:row;justify-content:flex-end;gap:12px;margin-top:8px">
                        <a href="tickets.php" class="btn btn-secondary">Annulla</a>
                        <button type="submit" class="btn btn-primary">✓ Apri Ticket</button>
                    </div>

                </div>
            </form>
        </div>
    </main>
</div>
</body>
</html>