<?php
/* =========================================================
   admin/create_event.php — CREATE EVENT (ADMIN)
   ✅ Admin-only
   ✅ Create events (public/private)
   ✅ CSRF protected
   ✅ Uses events table (title, description, location, start_date, end_date, is_public)
   ========================================================= */

declare(strict_types=1);

require_once __DIR__ . '/../lib/helpers.php';
require_once __DIR__ . '/../config/db.php';

start_session();

if (($_SESSION['role'] ?? null) !== 'admin') {
    redirect('login.php');
}

$pageTitle = 'Create Event | Admin';

/* -----------------------------
   Helpers
------------------------------ */
function table_exists(string $table): bool
{
    $stmt = db()->prepare(
        'SELECT COUNT(*)
         FROM INFORMATION_SCHEMA.TABLES
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = :table'
    );
    $stmt->execute(['table' => $table]);
    return (int)$stmt->fetchColumn() > 0;
}

function column_exists(string $table, string $column): bool
{
    $stmt = db()->prepare(
        'SELECT COUNT(*)
         FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = :table
           AND COLUMN_NAME = :column'
    );
    $stmt->execute(['table' => $table, 'column' => $column]);
    return (int)$stmt->fetchColumn() > 0;
}

if (!table_exists('events')) {
    die('events table not found. Create it first.');
}

// required columns check
$required = ['title','description','location','start_date','end_date'];
foreach ($required as $col) {
    if (!column_exists('events', $col)) {
        die('Missing required column in events table: ' . $col);
    }
}
$hasPublic = column_exists('events', 'is_public'); // optional

/* -----------------------------
   Handle submit
------------------------------ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        flash_set('error', 'Invalid session token. Please try again.');
        redirect('admin/create_event.php');
    }

    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $start = trim($_POST['start_date'] ?? '');
    $end = trim($_POST['end_date'] ?? '');
    $isPublic = isset($_POST['is_public']) ? 1 : 0;

    if ($title === '' || $description === '' || $start === '' || $end === '') {
        flash_set('error', 'Please fill all required fields.');
        redirect('admin/create_event.php');
    }

    // Datetime validation (expects "YYYY-MM-DDTHH:MM" from datetime-local)
    // Convert to "YYYY-MM-DD HH:MM:SS"
    $startDT = date('Y-m-d H:i:s', strtotime($start));
    $endDT   = date('Y-m-d H:i:s', strtotime($end));

    if (strtotime($endDT) < strtotime($startDT)) {
        flash_set('error', 'End date/time must be after start date/time.');
        redirect('admin/create_event.php');
    }

    try {
        if ($hasPublic) {
            $stmt = db()->prepare(
                "INSERT INTO events (title, description, location, start_date, end_date, is_public)
                 VALUES (:title, :description, :location, :start_date, :end_date, :is_public)"
            );
            $stmt->execute([
                'title' => $title,
                'description' => $description,
                'location' => $location,
                'start_date' => $startDT,
                'end_date' => $endDT,
                'is_public' => $isPublic,
            ]);
        } else {
            $stmt = db()->prepare(
                "INSERT INTO events (title, description, location, start_date, end_date)
                 VALUES (:title, :description, :location, :start_date, :end_date)"
            );
            $stmt->execute([
                'title' => $title,
                'description' => $description,
                'location' => $location,
                'start_date' => $startDT,
                'end_date' => $endDT,
            ]);
        }

        flash_set('status', 'Event created successfully.');
        redirect('admin/manage_events.php');

    } catch (Throwable $e) {
        flash_set('error', 'Failed to create event: ' . $e->getMessage());
        redirect('admin/create_event.php');
    }
}

$statusMsg = flash_get('status');
$errorMsg  = flash_get('error');

require_once __DIR__ . '/partials/header.php';
?>

<style>
    :root{
        --eaa-border:#e2e8f0;
        --eaa-radius:5px;
        --eaa-ink:#0f172a;
        --eaa-smoke:#475569;
    }
    .eaa-radius{border-radius:var(--eaa-radius)!important;}
    .tech-label{
        font-size:8px;font-weight:900;text-transform:uppercase;letter-spacing:.2em;color:#94a3b8;
        display:block;margin-bottom:6px;
    }
    .card{background:#fff;border:1px solid var(--eaa-border);border-radius:var(--eaa-radius);padding:22px;}
    .inp{
        width:100%;background:#fff;border:1px solid var(--eaa-border);border-radius:var(--eaa-radius);
        padding:12px 14px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;
        outline:none;
    }
    .inp:focus{border-color:#0f172a; box-shadow:0 0 0 1px #0f172a;}
    textarea.inp{
        min-height:140px;text-transform:none;letter-spacing:normal;font-weight:600;font-size:14px;
    }
    .btn{
        padding:12px 16px;border-radius:var(--eaa-radius);
        font-size:9px;font-weight:900;text-transform:uppercase;letter-spacing:.14em;
        border:1px solid var(--eaa-border);background:#0f172a;color:#fff;transition:.2s;
    }
    .btn:hover{background:#334155;}
    .btn-ghost{background:#fff;color:var(--eaa-smoke);}
    .btn-ghost:hover{background:#0f172a;color:#fff;}
    .notice{padding:12px 14px;border-radius:var(--eaa-radius);font-size:10px;font-weight:900;text-transform:uppercase;letter-spacing:.12em;}
    .ok{background:#ecfdf5;border:1px solid #a7f3d0;color:#065f46;}
    .bad{background:#fef2f2;border:1px solid #fecaca;color:#991b1b;}
</style>

<div class="container mx-auto px-6 pt-10 pb-20 max-w-4xl">
    <div class="flex items-end justify-between gap-6 mb-8">
        <div>
            <span class="tech-label">Admin / Events</span>
            <h1 class="text-2xl md:text-4xl font-black uppercase tracking-tight text-slate-900">Create Event</h1>
        </div>
        <div class="flex gap-2">
            <a class="btn btn-ghost" href="<?= e(url('admin/manage_events.php')) ?>">Manage Events</a>
        </div>
    </div>

    <?php if ($statusMsg): ?><div class="notice ok mb-6"><?= e($statusMsg) ?></div><?php endif; ?>
    <?php if ($errorMsg): ?><div class="notice bad mb-6"><?= e($errorMsg) ?></div><?php endif; ?>

    <div class="card">
        <form method="post" class="space-y-6">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

            <div>
                <label class="tech-label">Event Title *</label>
                <input class="inp" name="title" placeholder="EAA Workshop on BIM 2026" required>
            </div>

            <div>
                <label class="tech-label">Location</label>
                <input class="inp" name="location" placeholder="Erode / Venue name / Google map landmark">
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="tech-label">Start Date & Time *</label>
                    <input class="inp" type="datetime-local" name="start_date" required>
                </div>
                <div>
                    <label class="tech-label">End Date & Time *</label>
                    <input class="inp" type="datetime-local" name="end_date" required>
                </div>
            </div>

            <div>
                <label class="tech-label">Description *</label>
                <textarea class="inp" name="description" placeholder="Write event agenda, speakers, registration info..." required></textarea>
            </div>

            <?php if ($hasPublic): ?>
            <div class="flex items-center gap-3">
                <input type="checkbox" id="is_public" name="is_public" checked>
                <label for="is_public" class="tech-label" style="margin:0;">Make this event public</label>
            </div>
            <?php endif; ?>

            <div class="pt-4 flex items-center justify-end gap-2">
                <a class="btn btn-ghost" href="<?= e(url('admin/manage_events.php')) ?>">Cancel</a>
                <button class="btn" type="submit">Create Event</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/partials/footer.php'; ?>
