<?php
// pages/dashboard.php
require_once '../app/auth/auth.php';
checkAuth();

$db   = getDB();
$user = currentUser();

// Statistiche
$stats = [];
$stati = ['aperto', 'in_lavorazione', 'risolto', 'chiuso'];
foreach ($stati as $s) {
    $st = $db->prepare("SELECT COUNT(*) FROM tickets WHERE stato = ?");
    $st->execute([$s]);
    $stats[$s] = $st->fetchColumn();
}
$stats['totale'] = array_sum($stats);

// Ultimi 8 ticket
$recenti = $db->query("
    SELECT t.*, u.nome as assegnato_nome, c.nome as cat_nome
    FROM tickets t
    LEFT JOIN utenti u ON t.assegnato_a = u.id
    LEFT JOIN categorie c ON t.categoria_id = c.id
    ORDER BY t.created_at DESC LIMIT 8
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard — TicketDesk</title>
    <link rel="stylesheet" href="../styles/dashboard.css">
</head>
<body>
<div class="app-layout">
    <?php require_once '../app/partials/sidebar.php'; ?>

    <main class="main-content">
        <div class="page-header">
            <div>
                <h1 class="page-title">Dashboard</h1>
                <p class="page-sub">Benvenuto, <?= htmlspecialchars($user['nome']) ?> — <?= date('l j F Y') ?></p>
            </div>
            <a href="nuovo_ticket.php" class="btn btn-primary">+ Nuovo Ticket</a>
        </div>

        <!-- Stats -->
        <div class="stat-grid">
            <div class="stat-card">
                <div class="stat-icon" style="background:rgba(96,165,250,0.15)">🎫</div>
                <div><div class="stat-val"><?= $stats['totale'] ?></div><div class="stat-lbl">Totale</div></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background:rgba(96,165,250,0.15)">📬</div>
                <div><div class="stat-val" style="color:var(--info)"><?= $stats['aperto'] ?></div><div class="stat-lbl">Aperti</div></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background:rgba(251,191,36,0.15)">⚙️</div>
                <div><div class="stat-val" style="color:var(--warning)"><?= $stats['in_lavorazione'] ?></div><div class="stat-lbl">In lavorazione</div></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background:rgba(52,211,153,0.15)">✅</div>
                <div><div class="stat-val" style="color:var(--success)"><?= $stats['risolto'] ?></div><div class="stat-lbl">Risolti</div></div>
            </div>
        </div>

        <!-- Tabella recenti -->
        <div class="card">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:18px">
                <h2 class="card-title" style="margin-bottom:0">Ticket Recenti</h2>
                <a href="tickets.php" class="btn btn-secondary btn-sm">Vedi tutti →</a>
            </div>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Titolo</th>
                            <th>Stato</th>
                            <th>Priorità</th>
                            <th>Categoria</th>
                            <th>Assegnato a</th>
                            <th>Data</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($recenti)): ?>
                        <tr><td colspan="8" style="text-align:center;padding:40px;color:var(--muted)">Nessun ticket trovato</td></tr>
                    <?php else: ?>
                        <?php foreach ($recenti as $t): ?>
                        <tr>
                            <td class="text-muted text-sm">#<?= $t['id'] ?></td>
                            <td style="max-width:260px">
                                <a href="ticket_detail.php?id=<?= $t['id'] ?>" style="color:var(--text);text-decoration:none;font-weight:500" onmouseover="this.style.color='var(--accent2)'" onmouseout="this.style.color='var(--text)'">
                                    <?= htmlspecialchars($t['titolo']) ?>
                                </a>
                                <?php if ($t['richiedente_nome']): ?>
                                    <div class="text-sm text-muted"><?= htmlspecialchars($t['richiedente_nome']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td><span class="badge badge-<?= $t['stato'] ?>"><span class="badge-dot"></span><?= str_replace('_',' ', ucfirst($t['stato'])) ?></span></td>
                            <td><span class="badge badge-<?= $t['priorita'] ?>"><?= ucfirst($t['priorita']) ?></span></td>
                            <td class="text-sm text-muted"><?= htmlspecialchars($t['cat_nome'] ?? '—') ?></td>
                            <td class="text-sm"><?= htmlspecialchars($t['assegnato_nome'] ?? '—') ?></td>
                            <td class="text-sm text-muted"><?= date('d/m/Y', strtotime($t['created_at'])) ?></td>
                            <td><a href="ticket_detail.php?id=<?= $t['id'] ?>" class="btn btn-secondary btn-sm">Apri</a></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>
</body>
</html>