<?php
// pages/utenti.php
require_once '../../app/auth/auth.php';
checkAuth();

$db   = getDB();
$user = currentUser();

// Solo admin
if ($user['ruolo'] !== 'admin') {
    header('Location: dashboard.php');
    exit;
}

$success = '';
$error   = '';

// Azioni POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'crea') {
        $nome  = trim($_POST['nome'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $pass  = trim($_POST['password'] ?? '');
        $ruolo = $_POST['ruolo'] ?? 'operatore';

        if (!$nome || !$email || !$pass) {
            $error = 'Tutti i campi sono obbligatori.';
        } elseif (strlen($pass) < 6) {
            $error = 'La password deve essere di almeno 6 caratteri.';
        } else {
            try {
                $hash = password_hash($pass, PASSWORD_BCRYPT);
                $db->prepare("INSERT INTO utenti (nome, email, password, ruolo) VALUES (?, ?, ?, ?)")
                   ->execute([$nome, $email, $hash, $ruolo]);
                $success = "Utente $nome creato con successo.";
            } catch (PDOException $e) {
                $error = 'Email già in uso.';
            }
        }
    }

    if ($action === 'toggle') {
        $uid = (int)($_POST['uid'] ?? 0);
        if ($uid !== $user['id']) {
            $db->prepare("UPDATE utenti SET attivo = 1 - attivo WHERE id = ?")->execute([$uid]);
        }
    }

    if ($action === 'elimina') {
        $uid = (int)($_POST['uid'] ?? 0);
        if ($uid !== $user['id']) {
            $db->prepare("DELETE FROM utenti WHERE id = ?")->execute([$uid]);
            $success = 'Utente eliminato.';
        } else {
            $error = 'Non puoi eliminare te stesso.';
        }
    }
}

$utenti = $db->query("SELECT u.*, (SELECT COUNT(*) FROM tickets WHERE assegnato_a = u.id) as n_ticket FROM utenti u ORDER BY u.id")->fetchAll();
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Utenti — TicketDesk</title>
    <link rel="stylesheet" href="../styles/dashboard.css">
    <style>
        .modal-overlay { display:none;position:fixed;inset:0;background:rgba(0,0,0,0.6);z-index:200;align-items:center;justify-content:center;backdrop-filter:blur(4px) }
        .modal-overlay.open { display:flex }
        .modal { background:var(--surface);border:1px solid var(--border);border-radius:16px;padding:28px;width:440px;max-width:95vw;animation:fadeUp .2s ease }
        @keyframes fadeUp { from{opacity:0;transform:translateY(16px)} to{opacity:1;transform:translateY(0)} }
        .modal-title { font-family:'Syne',sans-serif;font-size:18px;font-weight:700;margin-bottom:20px }
    </style>
</head>
<body>
<div class="app-layout">
    <?php require_once '../app/partials/sidebar.php'; ?>

    <main class="main-content">
        <div class="page-header">
            <div>
                <h1 class="page-title">Gestione Utenti</h1>
                <p class="page-sub"><?= count($utenti) ?> utenti registrati</p>
            </div>
            <button class="btn btn-primary" onclick="document.getElementById('modal-nuovo').classList.add('open')">+ Nuovo Utente</button>
        </div>

        <?php if ($success): ?><div class="alert alert-success">✅ <?= htmlspecialchars($success) ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-error">⚠️ <?= htmlspecialchars($error) ?></div><?php endif; ?>

        <div class="card">
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nome</th>
                            <th>Email</th>
                            <th>Ruolo</th>
                            <th>Ticket assegnati</th>
                            <th>Stato</th>
                            <th>Registrazione</th>
                            <th>Azioni</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($utenti as $u): ?>
                    <tr>
                        <td class="text-sm text-muted"><?= $u['id'] ?></td>
                        <td>
                            <div style="display:flex;align-items:center;gap:10px">
                                <div style="width:32px;height:32px;border-radius:50%;background:var(--accent-g);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:13px;flex-shrink:0"><?= strtoupper(substr($u['nome'],0,1)) ?></div>
                                <span style="font-weight:500"><?= htmlspecialchars($u['nome']) ?></span>
                            </div>
                        </td>
                        <td class="text-sm text-muted"><?= htmlspecialchars($u['email']) ?></td>
                        <td>
                            <?php $rc = ['admin'=>'var(--danger)','operatore'=>'var(--accent2)','viewer'=>'var(--muted2)'][$u['ruolo']] ?? 'var(--muted)'; ?>
                            <span style="font-size:12px;padding:3px 10px;border-radius:100px;background:rgba(255,255,255,0.06);color:<?= $rc ?>;font-weight:600"><?= ucfirst($u['ruolo']) ?></span>
                        </td>
                        <td class="text-sm"><?= $u['n_ticket'] ?></td>
                        <td>
                            <?php if ($u['attivo']): ?>
                                <span class="badge badge-risolto"><span class="badge-dot"></span>Attivo</span>
                            <?php else: ?>
                                <span class="badge badge-chiuso"><span class="badge-dot"></span>Disabilitato</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-sm text-muted"><?= date('d/m/Y', strtotime($u['created_at'])) ?></td>
                        <td>
                            <?php if ($u['id'] !== $user['id']): ?>
                            <div style="display:flex;gap:6px">
                                <form method="POST" style="display:inline">
                                    <input type="hidden" name="action" value="toggle">
                                    <input type="hidden" name="uid" value="<?= $u['id'] ?>">
                                    <button type="submit" class="btn btn-secondary btn-sm"><?= $u['attivo'] ? 'Disabilita' : 'Abilita' ?></button>
                                </form>
                                <form method="POST" style="display:inline" onsubmit="return confirm('Eliminare questo utente?')">
                                    <input type="hidden" name="action" value="elimina">
                                    <input type="hidden" name="uid" value="<?= $u['id'] ?>">
                                    <button type="submit" class="btn btn-danger btn-sm">Elimina</button>
                                </form>
                            </div>
                            <?php else: ?>
                                <span class="text-sm text-muted">Tu</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>

<!-- Modal nuovo utente -->
<div class="modal-overlay" id="modal-nuovo" onclick="if(event.target===this)this.classList.remove('open')">
    <div class="modal">
        <h2 class="modal-title">Nuovo Utente</h2>
        <form method="POST">
            <input type="hidden" name="action" value="crea">
            <div style="display:flex;flex-direction:column;gap:16px">
                <div class="form-group">
                    <label>Nome completo *</label>
                    <input type="text" name="nome" placeholder="Mario Rossi" required>
                </div>
                <div class="form-group">
                    <label>Email *</label>
                    <input type="email" name="email" placeholder="mario@azienda.it" required>
                </div>
                <div class="form-group">
                    <label>Password * (min 6 caratteri)</label>
                    <input type="password" name="password" placeholder="••••••••" required minlength="6">
                </div>
                <div class="form-group">
                    <label>Ruolo</label>
                    <select name="ruolo">
                        <option value="operatore">Operatore</option>
                        <option value="admin">Admin</option>
                        <option value="viewer">Viewer</option>
                    </select>
                </div>
                <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:8px">
                    <button type="button" class="btn btn-secondary" onclick="document.getElementById('modal-nuovo').classList.remove('open')">Annulla</button>
                    <button type="submit" class="btn btn-primary">Crea utente</button>
                </div>
            </div>
        </form>
    </div>
</div>
</body>
</html>