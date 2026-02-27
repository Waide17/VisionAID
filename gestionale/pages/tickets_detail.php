<?php
// pages/ticket_detail.php
require_once '../../app/auth/auth.php';
checkAuth();

$db   = getDB();
$user = currentUser();
$id   = (int)($_GET['id'] ?? 0);

if (!$id) { header('Location: tickets.php'); exit; }

$ticket = $db->prepare("
    SELECT t.*, u.nome as assegnato_nome, c.nome as cat_nome, c.colore as cat_colore,
           creator.nome as creato_da_nome
    FROM tickets t
    LEFT JOIN utenti u ON t.assegnato_a = u.id
    LEFT JOIN categorie c ON t.categoria_id = c.id
    LEFT JOIN utenti creator ON t.creato_da = creator.id
    WHERE t.id = ?
");
$ticket->execute([$id]);
$t = $ticket->fetch();

if (!$t) { header('Location: tickets.php'); exit; }

$success = '';
$error   = '';

// Azioni POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'commento') {
        $testo = trim($_POST['testo'] ?? '');
        if ($testo) {
            $db->prepare("INSERT INTO commenti (ticket_id, utente_id, testo) VALUES (?, ?, ?)")
               ->execute([$id, $user['id'], $testo]);
            $success = 'Commento aggiunto.';
        }
    }

    if ($action === 'aggiorna') {
        $stato       = $_POST['stato'] ?? $t['stato'];
        $priorita    = $_POST['priorita'] ?? $t['priorita'];
        $assegnato_a = $_POST['assegnato_a'] ?: null;
        $risolto_at  = ($stato === 'risolto' && $t['stato'] !== 'risolto') ? date('Y-m-d H:i:s') : $t['risolto_at'];

        $db->prepare("UPDATE tickets SET stato=?, priorita=?, assegnato_a=?, risolto_at=? WHERE id=?")
           ->execute([$stato, $priorita, $assegnato_a, $risolto_at, $id]);
        $success = 'Ticket aggiornato.';

        // Ricarica dati
        $ticket->execute([$id]);
        $t = $ticket->fetch();
    }
}

$commenti = $db->prepare("
    SELECT k.*, u.nome as autore FROM commenti k
    JOIN utenti u ON k.utente_id = u.id
    WHERE k.ticket_id = ? ORDER BY k.created_at ASC
");
$commenti->execute([$id]);
$commenti = $commenti->fetchAll();

$operatori = $db->query("SELECT id, nome FROM utenti WHERE attivo = 1 ORDER BY nome")->fetchAll();
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>#<?= $id ?> — <?= htmlspecialchars($t['titolo']) ?></title>
    <link rel="stylesheet" href="../styles/dashboard.css">
</head>
<body>
<div class="app-layout">
    <?php require_once '../app/partials/sidebar.php'; ?>

    <main class="main-content">
        <?php if (isset($_GET['new'])): ?>
            <div class="alert alert-success">✅ Ticket aperto con successo!</div>
        <?php endif; ?>
        <?php if ($success): ?><div class="alert alert-success">✅ <?= htmlspecialchars($success) ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-error">⚠️ <?= htmlspecialchars($error) ?></div><?php endif; ?>

        <div class="page-header">
            <div>
                <div style="display:flex;align-items:center;gap:10px;margin-bottom:6px">
                    <span style="font-family:monospace;color:var(--muted);font-size:14px">#<?= str_pad($id,4,'0',STR_PAD_LEFT) ?></span>
                    <span class="badge badge-<?= $t['stato'] ?>"><span class="badge-dot"></span><?= str_replace('_',' ', ucfirst($t['stato'])) ?></span>
                    <span class="badge badge-<?= $t['priorita'] ?>"><?= ucfirst($t['priorita']) ?></span>
                </div>
                <h1 class="page-title" style="font-size:22px"><?= htmlspecialchars($t['titolo']) ?></h1>
            </div>
            <a href="tickets.php" class="btn btn-secondary">← Ticket</a>
        </div>

        <div class="detail-grid">
            <!-- LEFT: descrizione + commenti -->
            <div>
                <div class="card mb-4">
                    <h2 class="card-title">Descrizione</h2>
                    <div style="font-size:14px;line-height:1.8;white-space:pre-wrap"><?= htmlspecialchars($t['descrizione']) ?></div>

                    <?php if ($t['richiedente_nome'] || $t['richiedente_email']): ?>
                    <div style="margin-top:16px;padding-top:16px;border-top:1px solid var(--border);display:flex;gap:16px;font-size:13px">
                        <div><span class="text-muted">Richiedente:</span> <?= htmlspecialchars($t['richiedente_nome'] ?? '') ?></div>
                        <?php if ($t['richiedente_email']): ?>
                        <div><a href="mailto:<?= htmlspecialchars($t['richiedente_email']) ?>" style="color:var(--accent2)"><?= htmlspecialchars($t['richiedente_email']) ?></a></div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Commenti -->
                <div class="card">
                    <h2 class="card-title">Commenti (<?= count($commenti) ?>)</h2>

                    <?php if (empty($commenti)): ?>
                        <p style="color:var(--muted);font-size:14px;text-align:center;padding:20px 0">Nessun commento ancora.</p>
                    <?php else: ?>
                        <div class="comments-list">
                            <?php foreach ($commenti as $c): ?>
                            <div class="comment-item">
                                <div class="comment-header">
                                    <div class="comment-author">
                                        <span style="display:inline-flex;width:22px;height:22px;background:var(--accent-g);border-radius:50%;align-items:center;justify-content:center;font-size:10px;font-weight:700;margin-right:6px"><?= strtoupper(substr($c['autore'],0,1)) ?></span>
                                        <?= htmlspecialchars($c['autore']) ?>
                                    </div>
                                    <div class="comment-date"><?= date('d/m/Y H:i', strtotime($c['created_at'])) ?></div>
                                </div>
                                <div class="comment-body"><?= nl2br(htmlspecialchars($c['testo'])) ?></div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <!-- Form commento -->
                    <form method="POST" style="margin-top:20px;padding-top:16px;border-top:1px solid var(--border)">
                        <input type="hidden" name="action" value="commento">
                        <div class="form-group">
                            <label>Aggiungi commento</label>
                            <textarea name="testo" rows="3" placeholder="Scrivi un aggiornamento o nota interna..."></textarea>
                        </div>
                        <div style="display:flex;justify-content:flex-end;margin-top:10px">
                            <button type="submit" class="btn btn-primary btn-sm">Invia commento</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- RIGHT: sidebar gestione -->
            <div>
                <form method="POST">
                    <input type="hidden" name="action" value="aggiorna">
                    <div class="card">
                        <h2 class="card-title">Gestione</h2>
                        <div style="display:flex;flex-direction:column;gap:14px">

                            <div class="form-group">
                                <label>Stato</label>
                                <select name="stato">
                                    <option value="aperto"        <?= $t['stato']==='aperto'?'selected':'' ?>>📬 Aperto</option>
                                    <option value="in_lavorazione"<?= $t['stato']==='in_lavorazione'?'selected':'' ?>>⚙️ In lavorazione</option>
                                    <option value="risolto"       <?= $t['stato']==='risolto'?'selected':'' ?>>✅ Risolto</option>
                                    <option value="chiuso"        <?= $t['stato']==='chiuso'?'selected':'' ?>>🔒 Chiuso</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label>Priorità</label>
                                <select name="priorita">
                                    <option value="bassa"  <?= $t['priorita']==='bassa'?'selected':'' ?>>🟢 Bassa</option>
                                    <option value="media"  <?= $t['priorita']==='media'?'selected':'' ?>>🔵 Media</option>
                                    <option value="alta"   <?= $t['priorita']==='alta'?'selected':'' ?>>🟡 Alta</option>
                                    <option value="critica"<?= $t['priorita']==='critica'?'selected':'' ?>>🔴 Critica</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label>Assegnato a</label>
                                <select name="assegnato_a">
                                    <option value="">— Non assegnato —</option>
                                    <?php foreach ($operatori as $op): ?>
                                    <option value="<?= $op['id'] ?>" <?= $t['assegnato_a']==$op['id']?'selected':'' ?>><?= htmlspecialchars($op['nome']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <button type="submit" class="btn btn-primary" style="width:100%">Salva modifiche</button>
                        </div>
                    </div>
                </form>

                <!-- Meta info -->
                <div class="card" style="margin-top:16px">
                    <h2 class="card-title">Dettagli</h2>
                    <div class="detail-meta">
                        <div class="meta-row">
                            <div class="meta-label">Categoria</div>
                            <div class="meta-value">
                                <?php if ($t['cat_nome']): ?>
                                    <span style="display:inline-flex;align-items:center;gap:6px">
                                        <span style="width:10px;height:10px;border-radius:50%;background:<?= htmlspecialchars($t['cat_colore']) ?>"></span>
                                        <?= htmlspecialchars($t['cat_nome']) ?>
                                    </span>
                                <?php else: ?><span class="text-muted">—</span><?php endif; ?>
                            </div>
                        </div>
                        <div class="meta-row">
                            <div class="meta-label">Creato da</div>
                            <div class="meta-value"><?= htmlspecialchars($t['creato_da_nome'] ?? '—') ?></div>
                        </div>
                        <div class="meta-row">
                            <div class="meta-label">Apertura</div>
                            <div class="meta-value text-sm"><?= date('d/m/Y H:i', strtotime($t['created_at'])) ?></div>
                        </div>
                        <div class="meta-row">
                            <div class="meta-label">Ultimo aggiornamento</div>
                            <div class="meta-value text-sm"><?= date('d/m/Y H:i', strtotime($t['updated_at'])) ?></div>
                        </div>
                        <?php if ($t['risolto_at']): ?>
                        <div class="meta-row">
                            <div class="meta-label">Risolto il</div>
                            <div class="meta-value text-sm" style="color:var(--success)"><?= date('d/m/Y H:i', strtotime($t['risolto_at'])) ?></div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>
</body>
</html>