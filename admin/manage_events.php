<?php
/* =========================================================
   admin/manage_events.php — EVENT PLANNER LEDGER (DB)
   ✅ Premium ledger UI (your style)
   ✅ DB-backed listing + filters + search
   ✅ Manifest counter (registrations per event)
   ✅ Actions: Attendees / Edit / Delete
   ========================================================= */

declare(strict_types=1);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/../lib/helpers.php';
require_once __DIR__ . '/../config/db.php';

start_session();

/* -----------------------------
   Basic Admin Guard
------------------------------ */
$role = $_SESSION['role'] ?? '';
if ($role !== 'admin') {
    redirect('../login.php');
}

$pageTitle = 'Event Planner | EAA Root';

/* -----------------------------
   Helpers
------------------------------ */
function table_exists(string $table): bool {
    $stmt = db()->prepare(
        "SELECT COUNT(*)
         FROM INFORMATION_SCHEMA.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t"
    );
    $stmt->execute(['t' => $table]);
    return (int)$stmt->fetchColumn() > 0;
}

function column_exists(string $table, string $column): bool {
    $stmt = db()->prepare(
        "SELECT COUNT(*)
         FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = :t
           AND COLUMN_NAME = :c"
    );
    $stmt->execute(['t' => $table, 'c' => $column]);
    return (int)$stmt->fetchColumn() > 0;
}

/* -----------------------------
   Ensure tables (minimal)
------------------------------ */
if (!table_exists('events')) {
    db()->exec("
        CREATE TABLE events (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            description TEXT NULL,
            location VARCHAR(255) NULL,
            cover_image VARCHAR(255) NULL,
            start_date DATETIME NOT NULL,
            end_date DATETIME NULL,
            category VARCHAR(100) NOT NULL DEFAULT 'General',
            status ENUM('draft','published','completed','cancelled') NOT NULL DEFAULT 'draft',
            created_by INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_events_created_by FOREIGN KEY (created_by) REFERENCES users(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
}

if (!table_exists('event_registrations')) {
    db()->exec("
        CREATE TABLE event_registrations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            event_id INT NOT NULL,
            user_id INT NOT NULL,
            status ENUM('registered','attended','cancelled') NOT NULL DEFAULT 'registered',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX (event_id),
            INDEX (user_id),
            CONSTRAINT fk_regs_event FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
            CONSTRAINT fk_regs_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
}

/* -----------------------------
   Delete handler
------------------------------ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_event') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        flash_set('event_error', 'Invalid session token. Please try again.');
        redirect('manage_events.php');
    }

    $eventId = (int)($_POST['event_id'] ?? 0);
    if ($eventId <= 0) {
        flash_set('event_error', 'Invalid event id.');
        redirect('manage_events.php');
    }

    try {
        $del = db()->prepare("DELETE FROM events WHERE id = :id LIMIT 1");
        $del->execute(['id' => $eventId]);
        flash_set('event_status', 'Event deleted successfully.');
        redirect('manage_events.php');
    } catch (Throwable $e) {
        flash_set('event_error', 'Failed to delete: ' . $e->getMessage());
        redirect('manage_events.php');
    }
}

/* -----------------------------
   Filters
------------------------------ */
$q = trim($_GET['q'] ?? '');
$status = trim($_GET['status'] ?? '');
$category = trim($_GET['category'] ?? '');

$conditions = [];
$params = [];

if ($q !== '') {
    $conditions[] = "(events.title LIKE :q OR events.location LIKE :q OR events.id = :id_exact)";
    $params['q'] = '%' . $q . '%';
    $params['id_exact'] = is_numeric($q) ? (int)$q : 0;
}

if ($status !== '') {
    $conditions[] = "events.status = :status";
    $params['status'] = $status;
}

if ($category !== '') {
    $conditions[] = "events.category = :category";
    $params['category'] = $category;
}

$whereSql = $conditions ? ("WHERE " . implode(" AND ", $conditions)) : "";

/* -----------------------------
   Get events + manifest count
------------------------------ */
$hasEndDate = column_exists('events', 'end_date');
$hasCover = column_exists('events', 'cover_image');

$sql = "
    SELECT
        events.id,
        events.title,
        events.location,
        events.category,
        events.status,
        events.start_date" . ($hasEndDate ? ", events.end_date" : "") . ",
        COUNT(event_registrations.id) AS reg_count
    FROM events
    LEFT JOIN event_registrations
      ON event_registrations.event_id = events.id
    $whereSql
    GROUP BY events.id
    ORDER BY events.start_date DESC
    LIMIT 200
";
$stmt = db()->prepare($sql);
$stmt->execute($params);
$events = $stmt->fetchAll();

/* -----------------------------
   Stats
------------------------------ */
$statUpcoming = db()->query("SELECT COUNT(*) FROM events WHERE status IN ('published','draft')")->fetchColumn();
$statManifest = db()->query("SELECT COUNT(*) FROM event_registrations")->fetchColumn();

$eventStatus = flash_get('event_status');
$eventError  = flash_get('event_error');

require_once __DIR__ . '/partials/header.php';
?>

<style>
    .ledger-table-container {
        background: #ffffff;
        border: 1px solid #eef2f6;
        border-radius: 5px;
        box-shadow: 0 10px 30px -10px rgba(71, 85, 105, 0.05);
    }
    .eaa-table { width: 100%; border-collapse: separate; border-spacing: 0; }
    .eaa-table th {
        background: #f8fafc;
        border-bottom: 1px solid #eef2f6;
        padding: 20px 30px;
        color: #94a3b8;
        font-size: 8px;
        font-weight: 900;
        text-transform: uppercase;
        letter-spacing: 0.2em;
    }
    .eaa-table td { padding: 24px 30px; border-bottom: 1px solid #f1f5f9; vertical-align: middle; }
    .eaa-table tr:last-child td { border-bottom: none; }
    .eaa-table tr:hover td { background-color: #fcfdfe; }

    .action-node {
        width: 32px; height: 32px; display: flex; align-items: center; justify-content: center;
        border-radius: 3px; border: 1px solid #e2e8f0; color: #64748b; transition: all 0.2s ease; background: #ffffff;
        cursor: pointer;
    }
    .action-node:hover { background: #1e293b; color: #ffffff; border-color: #1e293b; transform: translateY(-1px); }
    .manifest-counter {
        background: #f1f5f9; padding: 4px 10px; border-radius: 3px; font-size: 10px; font-weight: 800;
        color: #1e293b; border: 1px solid #e2e8f0;
    }
    .notice { padding: 12px 14px; border-radius: 5px; font-size: 10px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.12em; }
    .notice-ok { background: #ecfdf5; border: 1px solid #a7f3d0; color: #065f46; }
    .notice-bad { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; }
</style>

<div class="mb-12 reveal active">

    <?php if ($eventStatus): ?>
        <div class="mb-6 notice notice-ok"><?= e($eventStatus) ?></div>
    <?php endif; ?>
    <?php if ($eventError): ?>
        <div class="mb-6 notice notice-bad"><?= e($eventError) ?></div>
    <?php endif; ?>

    <div class="flex flex-col md:flex-row md:items-end justify-between gap-8 mb-10">
        <div>
            <span class="text-[8px] font-black uppercase tracking-[0.4em] text-slate-400 mb-2 block">Database Node: OPS_EVENT</span>
            <h2 class="font-druk text-3xl md:text-5xl text-slate-900 uppercase">Event <span class="text-slate-400 italic">Planner</span></h2>
        </div>

        <div class="flex gap-4">
            <div class="px-8 py-4 bg-white border border-slate-200 eaa-radius flex flex-col justify-center shadow-sm">
                <span class="text-[7px] font-black text-slate-400 uppercase tracking-widest mb-1">Upcoming</span>
                <span class="text-xl font-black text-slate-900"><?= e((string)$statUpcoming) ?></span>
            </div>
            <div class="px-8 py-4 bg-white border border-slate-200 eaa-radius flex flex-col justify-center shadow-sm">
                <span class="text-[7px] font-black text-slate-400 uppercase tracking-widest mb-1">Total Manifest</span>
                <span class="text-xl font-black text-green-600"><?= e((string)$statManifest) ?></span>
            </div>

            <a href="create_event.php" class="px-10 py-4 bg-slate-900 text-white text-[10px] font-black uppercase tracking-widest eaa-radius shadow-2xl hover:bg-slate-700 transition-all">
                + New Event
            </a>
        </div>
    </div>

    <!-- FILTER BAR -->
    <form method="get" class="p-4 bg-white border border-slate-100 eaa-radius flex flex-col lg:flex-row gap-4 justify-between items-center shadow-sm">
        <div class="flex items-center gap-2 overflow-x-auto no-scrollbar w-full lg:w-auto">
            <select name="status" class="px-4 py-3 bg-slate-50 border border-slate-100 eaa-radius text-[9px] font-black uppercase tracking-widest">
                <option value="">All Status</option>
                <?php foreach (['draft','published','completed','cancelled'] as $s): ?>
                    <option value="<?= e($s) ?>" <?= $status === $s ? 'selected' : '' ?>><?= e($s) ?></option>
                <?php endforeach; ?>
            </select>

            <select name="category" class="px-4 py-3 bg-slate-50 border border-slate-100 eaa-radius text-[9px] font-black uppercase tracking-widest">
                <option value="">All Categories</option>
                <?php
                $cats = db()->query("SELECT DISTINCT category FROM events ORDER BY category")->fetchAll(PDO::FETCH_COLUMN);
                foreach ($cats as $c):
                ?>
                    <option value="<?= e($c) ?>" <?= $category === $c ? 'selected' : '' ?>><?= e($c) ?></option>
                <?php endforeach; ?>
            </select>

            <a href="manage_events.php" class="px-6 py-3 bg-slate-900 text-white text-[9px] font-black uppercase tracking-widest eaa-radius">Reset</a>
        </div>

        <div class="relative w-full lg:w-96">
            <input name="q" value="<?= e($q) ?>" type="text" placeholder="FILTER BY TITLE, VENUE, OR ID..." class="w-full bg-slate-50 border border-slate-100 eaa-radius px-6 py-3.5 text-[9px] font-bold uppercase tracking-widest outline-none focus:border-slate-400 transition-all">
            <i class="fa-solid fa-magnifying-glass absolute right-6 top-1/2 -translate-y-1/2 text-slate-300 text-[10px]"></i>
        </div>

        <button type="submit" class="px-8 py-3.5 bg-white border border-slate-200 text-slate-700 text-[9px] font-black uppercase tracking-widest eaa-radius hover:bg-slate-50">
            Apply
        </button>
    </form>
</div>

<!-- TABLE -->
<div class="ledger-table-container reveal active" style="transition-delay: 100ms;">
    <div class="overflow-x-auto">
        <table class="eaa-table">
            <thead>
                <tr>
                    <th>Event Detail / Ref_ID</th>
                    <th>Chronological Node</th>
                    <th>Site Location</th>
                    <th>Manifest</th>
                    <th>Status</th>
                    <th class="text-right">Operations</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($events)): ?>
                    <tr><td colspan="6" class="text-center text-slate-400 text-xs font-bold uppercase tracking-widest py-10">No events found</td></tr>
                <?php endif; ?>

                <?php foreach ($events as $eRow): ?>
                    <?php
                    $statusClass = 'bg-slate-100 text-slate-500 border-slate-100';
                    if ($eRow['status'] === 'published') $statusClass = 'bg-blue-50 text-blue-600 border-blue-100';
                    if ($eRow['status'] === 'completed') $statusClass = 'bg-green-50 text-green-600 border-green-100';
                    if ($eRow['status'] === 'draft') $statusClass = 'bg-amber-50 text-amber-600 border-amber-100';
                    if ($eRow['status'] === 'cancelled') $statusClass = 'bg-red-50 text-red-600 border-red-100';

                    $dateText = date('d M Y', strtotime($eRow['start_date']));
                    $timeText = date('h:i A', strtotime($eRow['start_date']));
                    ?>
                    <tr>
                        <td>
                            <div class="flex flex-col">
                                <span class="text-[12px] font-black text-slate-900 uppercase tracking-tight mb-1"><?= e($eRow['title']) ?></span>
                                <div class="flex items-center gap-2">
                                    <span class="text-[7px] font-black text-white bg-slate-400 px-1.5 py-0.5 rounded-sm uppercase tracking-widest"><?= e($eRow['category']) ?></span>
                                    <span class="text-[8px] font-bold text-slate-400 uppercase tracking-[0.1em]">EAA-EVT-<?= e((string)$eRow['id']) ?></span>
                                </div>
                            </div>
                        </td>

                        <td>
                            <div class="flex flex-col">
                                <span class="text-[10px] font-black text-slate-600 uppercase tracking-widest"><?= e($dateText) ?></span>
                                <span class="text-[9px] font-bold text-slate-400 uppercase tracking-widest"><?= e($timeText) ?></span>
                            </div>
                        </td>

                        <td>
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 rounded bg-slate-50 flex items-center justify-center text-slate-300 border border-slate-100">
                                    <i class="fa-solid fa-location-dot text-[10px]"></i>
                                </div>
                                <span class="text-[10px] font-bold text-slate-500 uppercase tracking-widest"><?= e($eRow['location'] ?? '—') ?></span>
                            </div>
                        </td>

                        <td>
                            <div class="inline-flex items-center gap-3">
                                <span class="manifest-counter"><?= e((string)$eRow['reg_count']) ?></span>
                                <span class="text-[7px] font-black uppercase text-slate-300 tracking-widest">Logged</span>
                            </div>
                        </td>

                        <td>
                            <span class="px-3 py-1 text-[7px] font-black uppercase tracking-widest rounded border <?= e($statusClass) ?>">
                                <?= e($eRow['status']) ?>
                            </span>
                        </td>

                        <td>
                            <div class="flex justify-end gap-2">
                                <a class="action-node" href="event_attendees.php?event_id=<?= e((string)$eRow['id']) ?>" title="Attendee List">
                                    <i class="fa-solid fa-list-check text-[11px]"></i>
                                </a>

                                <a class="action-node" href="edit_event.php?id=<?= e((string)$eRow['id']) ?>" title="Modify Node">
                                    <i class="fa-solid fa-pen-nib text-[11px]"></i>
                                </a>

                                <form method="post" action="manage_events.php" onsubmit="return confirm('Delete this event? This will also delete registrations.');">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="action" value="delete_event">
                                    <input type="hidden" name="event_id" value="<?= e((string)$eRow['id']) ?>">
                                    <button class="action-node hover:!bg-red-500 hover:!border-red-500" title="Delete Entry" type="submit">
                                        <i class="fa-solid fa-trash-can text-[11px]"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>

            </tbody>
        </table>
    </div>

    <div class="px-8 py-6 bg-slate-50/50 border-t border-slate-100 flex items-center justify-between">
        <span class="text-[8px] font-black uppercase tracking-widest text-slate-400 italic">
            Chronicle Node: Loaded <?= e((string)count($events)) ?> record(s)
        </span>
    </div>
</div>

<?php require_once __DIR__ . '/partials/footer.php'; ?>
