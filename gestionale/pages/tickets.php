<?php
// pages/tickets.php
require_once '../../app/auth/auth.php';
checkAuth();

$db   = getDB();
$user = currentUser();

// Filtri
$stato    = $_GET['stato']    ?? '';
$priorita = $_GET['priorita'] ?? '';
$cerca    = $_GET['cerca']    ?? '';
$cat      = $_GET['cat']      ?? '';

$where  = ['1=1'];
$params = [];

if ($stato)    { $where[] = 't.stato = ?';         $params[] = $stato; }
if ($priorita) { $where[] = 't.priorita = ?';      $params[] = $priorita; }
if ($cat)      { $where[] = 't.categoria_id = ?';  $params[] = $cat; }
if ($cerca)    { $where[] = '(t.titolo LIKE ? OR t.richiedente_nome LIKE ? OR t.richiedente_email LIKE ?)';
                 $params = array_merge($params, ["%$cerca%", "%$cerca%", "%$cerca%"]); }

$sql = "SELECT t.*, u.nome as assegnato_nome, c.nome as cat_nome, c.colore as cat_colore
        FROM tickets t
        LEFT JOIN utenti u ON t.assegnato_a = u.id
        LEFT JOIN categorie c ON t.categoria_id = c.id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY FIELD(t.priorita,'critica','alta','media','bassa'), t.created_at DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$tickets = $stmt->fetchAll();

$categorie = $db->query("SELECT * FROM categorie ORDER BY nome")->fetchAll();
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ticket — TicketDesk</title>
    <link rel="stylesheet" href="../styles/dashboard.css">
</head>
<body>
<div class="app-layout">
    <?php require_once '../app/partials/sidebar.php'; ?>

    <main class="main-content">
        <div class="page-header">
            <div>
                <h1 class="page-title">Tutti i Ticket</h1>
                <p class="page-sub"><?= count($tickets) ?> ticket trovati</p>
            </div>
            <a href="nuovo_ticket.php" class="btn btn-primary">+ Nuovo Ticket</a>
        </div>

        <!-- Filtri -->
        <form method="GET" class="filters-bar">
            <input type="text" name="cerca" class="search-input" placeholder="🔍  Cerca per titolo o richiedente..." value="<?= htmlspecialchars($cerca) ?>">

            <select name="stato" class="filter-select" onchange="this.form.submit()">
                <option value="">Tutti gli stati</option>
                <option value="aperto"        <?= $stato==='aperto'?'selected':'' ?>>Aperto</option>
                <option value="in_lavorazione"<?= $stato==='in_lavorazione'?'selected':'' ?>>In lavorazione</option>
                <option value="risolto"       <?= $stato==='risolto'?'selected':'' ?>>Risolto</option>
                <option value="chiuso"        <?= $stato==='chiuso'?'selected':'' ?>>Chiuso</option>
            </select>

            <select name="priorita" class="filter-select" onchange="this.form.submit()">
                <option value="">Tutte le priorità</option>
                <option value="critica"<?= $priorita==='critica'?'selected':'' ?>>Critica</option>
                <option value="alta"   <?= $priorita==='alta'?'selected':'' ?>>Alta</option>
                <option value="media"  <?= $priorita==='media'?'selected':'' ?>>Media</option>
                <option value="bassa"  <?= $priorita==='bassa'?'selected':'' ?>>Bassa</option>
            </select>

            <select name="cat" class="filter-select" onchange="this.form.submit()">
                <option value="">Tutte le categorie</option>
                <?php foreach ($categorie as $c): ?>
                <option value="<?= $c['id'] ?>" <?= $cat==$c['id']?'selected':'' ?>><?= htmlspecialchars($c['nome']) ?></option>
                <?php endforeach; ?>
            </select>

            <button type="submit" class="btn btn-secondary btn-sm">Filtra</button>
            <?php if ($stato || $priorita || $cerca || $cat): ?>
                <a href="tickets.php" class="btn btn-secondary btn-sm" style="color:var(--danger)">✕ Reset</a>
            <?php endif; ?>
        </form>

        <!-- Tabella -->
        <div class="card">
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Titolo</th>
                            <th>Richiedente</th>
                            <th>Stato</th>
                            <th>Priorità</th>
                            <th>Categoria</th>
                            <th>Assegnato a</th>
                            <th>Data</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($tickets)): ?>
                        <tr>
                            <td colspan="9">
                                <div class="empty-state">
                                    <svg width="48" height="48" viewBox="0 0 24 24" fill="var(--muted)"><path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2z"/></svg>
                                    <h3>Nessun ticket trovato</h3>
                                    <p>Prova a cambiare i filtri o <a href="nuovo_ticket.php" style="color:var(--accent2)">crea un nuovo ticket</a></p>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($tickets as $t): ?>
                        <tr>
                            <td class="text-muted text-sm" style="font-family:monospace">#<?= str_pad($t['id'], 4, '0', STR_PAD_LEFT) ?></td>
                            <td style="max-width:240px">
                                <a href="ticket_detail.php?id=<?= $t['id'] ?>" style="color:var(--text);text-decoration:none;font-weight:500;display:block" onmouseover="this.style.color='var(--accent2)'" onmouseout="this.style.color='var(--text)'">
                                    <?= htmlspecialchars($t['titolo']) ?>
                                </a>
                            </td>
                            <td class="text-sm">
                                <?php if ($t['richiedente_nome']): ?>
                                    <div><?= htmlspecialchars($t['richiedente_nome']) ?></div>
                                    <div class="text-muted"><?= htmlspecialchars($t['richiedente_email'] ?? '') ?></div>
                                <?php else: ?><span class="text-muted">—</span><?php endif; ?>
                            </td>
                            <td><span class="badge badge-<?= $t['stato'] ?>"><span class="badge-dot"></span><?= str_replace('_',' ', ucfirst($t['stato'])) ?></span></td>
                            <td><span class="badge badge-<?= $t['priorita'] ?>"><?= ucfirst($t['priorita']) ?></span></td>
                            <td class="text-sm">
                                <?php if ($t['cat_nome']): ?>
                                    <span style="display:inline-flex;align-items:center;gap:5px">
                                        <span style="width:8px;height:8px;border-radius:50%;background:<?= htmlspecialchars($t['cat_colore']) ?>"></span>
                                        <?= htmlspecialchars($t['cat_nome']) ?>
                                    </span>
                                <?php else: ?><span class="text-muted">—</span><?php endif; ?>
                            </td>
                            <td class="text-sm"><?= htmlspecialchars($t['assegnato_nome'] ?? '—') ?></td>
                            <td class="text-sm text-muted"><?= date('d/m/Y H:i', strtotime($t['created_at'])) ?></td>
                            <td>
                                <a href="ticket_detail.php?id=<?= $t['id'] ?>" class="btn btn-secondary btn-sm">Apri</a>
                            </td>
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