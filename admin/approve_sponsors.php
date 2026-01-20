<?php
/* =========================================================
   admin/approve_sponsors.php — SPONSOR APPROVAL CONSOLE (ADMIN)
   ========================================================= */

declare(strict_types=1);

require_once __DIR__ . '/../lib/helpers.php';
require_once __DIR__ . '/../config/db.php';

start_session();

$role = $_SESSION['role'] ?? null;
if ($role !== 'admin') {
    redirect('admin/login.php');
}

$pageTitle = 'Sponsor Approvals | Admin';

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

function build_return_url(string $status, string $search): string
{
    $query = array_filter([
        'status' => $status,
        'search' => $search,
    ], static fn($value) => $value !== '');

    $suffix = $query ? ('?' . http_build_query($query)) : '';
    return 'admin/approve_sponsors.php' . $suffix;
}

if (!table_exists('sponsor_requests')) {
    die('sponsor_requests table not found.');
}

$hasReviewedAt = column_exists('sponsor_requests', 'reviewed_at');
$hasReviewedBy = column_exists('sponsor_requests', 'reviewed_by');
$hasUpdatedAt = column_exists('sponsor_requests', 'updated_at');
$hasWebsite = column_exists('sponsor_requests', 'website');
$hasLogo = column_exists('sponsor_requests', 'logo_path');
$hasPhone = column_exists('sponsor_requests', 'phone');
$hasNote = column_exists('sponsor_requests', 'note');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);
    $returnStatus = $_POST['return_status'] ?? 'pending';
    $returnSearch = $_POST['return_search'] ?? '';
    $returnUrl = build_return_url($returnStatus, $returnSearch);

    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        flash_set('error', 'Invalid session token. Please try again.');
        redirect($returnUrl);
    }

    if ($id <= 0) {
        flash_set('error', 'Invalid sponsor ID.');
        redirect($returnUrl);
    }

    if ($action === 'approve' || $action === 'reject') {
        $status = $action === 'approve' ? 'approved' : 'rejected';
        $set = ['status = :status'];
        if ($hasReviewedAt) $set[] = 'reviewed_at = NOW()';
        if ($hasReviewedBy) $set[] = 'reviewed_by = :admin_id';

        $sql = 'UPDATE sponsor_requests SET ' . implode(', ', $set) . ' WHERE id = :id';
        $stmt = db()->prepare($sql);
        $params = ['status' => $status, 'id' => $id];
        if ($hasReviewedBy) $params['admin_id'] = (int)($_SESSION['user_id'] ?? 0);
        $stmt->execute($params);

        flash_set('status', $action === 'approve' ? 'Sponsor approved.' : 'Sponsor rejected.');
        redirect($returnUrl);
    }

    if ($action === 'delete') {
        $logoPath = '';
        if ($hasLogo) {
            $logoStmt = db()->prepare('SELECT logo_path FROM sponsor_requests WHERE id = ?');
            $logoStmt->execute([$id]);
            $logoPath = (string)($logoStmt->fetchColumn() ?? '');
        }

        $stmt = db()->prepare('DELETE FROM sponsor_requests WHERE id = ?');
        $stmt->execute([$id]);

        if ($logoPath !== '' && !str_contains($logoPath, '://') && !str_starts_with($logoPath, '//')) {
            $relativePath = ltrim($logoPath, '/');
            $absPath = __DIR__ . '/../' . $relativePath;
            $rootPath = realpath(__DIR__ . '/..');
            $realPath = realpath($absPath);
            if ($realPath && $rootPath && str_starts_with($realPath, $rootPath) && is_file($realPath)) {
                @unlink($realPath);
            }
        }

        flash_set('status', 'Sponsor request deleted.');
        redirect($returnUrl);
    }
}

$allowed = ['pending', 'approved', 'rejected', 'all'];
$status = $_GET['status'] ?? 'pending';
if (!in_array($status, $allowed, true)) $status = 'pending';
$search = trim($_GET['search'] ?? '');

$where = [];
$params = [];

if ($status !== 'all') {
    $where[] = 'r.status = :status';
    $params['status'] = $status;
}

if ($search !== '') {
    $where[] = '(r.company_name LIKE :q OR u.full_name LIKE :q OR u.email LIKE :q)';
    $params['q'] = '%' . $search . '%';
}

$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$countBy = function (string $st): int {
    $stmt = db()->prepare('SELECT COUNT(*) FROM sponsor_requests WHERE status = ?');
    $stmt->execute([$st]);
    return (int)$stmt->fetchColumn();
};

$cntPending = $countBy('pending');
$cntApproved = $countBy('approved');
$cntRejected = $countBy('rejected');

$orderBy = $hasUpdatedAt ? 'r.updated_at DESC' : 'r.created_at DESC';

$sql = "
SELECT
    r.id,
    r.vendor_user_id,
    r.company_name,
    " . ($hasLogo ? "r.logo_path" : "NULL AS logo_path") . ",
    " . ($hasWebsite ? "r.website" : "NULL AS website") . ",
    " . ($hasPhone ? "r.phone" : "NULL AS phone") . ",
    " . ($hasNote ? "r.note" : "NULL AS note") . ",
    r.status,
    r.created_at,
    " . ($hasUpdatedAt ? "r.updated_at" : "r.created_at AS updated_at") . ",
    " . ($hasReviewedAt ? "r.reviewed_at" : "NULL AS reviewed_at") . ",
    u.full_name,
    u.email
FROM sponsor_requests r
JOIN users u ON u.id = r.vendor_user_id
{$whereSql}
ORDER BY {$orderBy}
LIMIT 200
";

$stmt = db()->prepare($sql);
$stmt->execute($params);
$requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

$statusMsg = flash_get('status');
$errorMsg = flash_get('error');

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
    .tech-label{font-size:8px;font-weight:900;text-transform:uppercase;letter-spacing:.2em;color:#94a3b8;display:block;margin-bottom:6px;}
    .badge{font-size:7px;font-weight:900;text-transform:uppercase;letter-spacing:.12em;padding:4px 10px;border-radius:2px;border:1px solid var(--eaa-border);display:inline-flex;align-items:center;gap:6px;}
    .b-pending{background:#fffbeb;color:#92400e;border-color:#fde68a;}
    .b-approved{background:#ecfdf5;color:#065f46;border-color:#a7f3d0;}
    .b-rejected{background:#fef2f2;color:#991b1b;border-color:#fecaca;}
    .card{background:#fff;border:1px solid var(--eaa-border);border-radius:var(--eaa-radius);padding:22px;}
    .btn{padding:10px 14px;border-radius:var(--eaa-radius);font-size:9px;font-weight:900;text-transform:uppercase;letter-spacing:.14em;border:1px solid var(--eaa-border);background:#fff;color:var(--eaa-smoke);transition:.2s;}
    .btn:hover{background:#0f172a;color:#fff;border-color:#0f172a;}
    .tabs a{font-size:9px;font-weight:900;text-transform:uppercase;letter-spacing:.14em;padding:12px 14px;border-bottom:2px solid transparent;color:#94a3b8;display:inline-flex;gap:10px;align-items:center;}
    .tabs a.active{color:var(--eaa-ink);border-bottom-color:var(--eaa-ink);}
    .table{width:100%;border-collapse:separate;border-spacing:0;}
    .table th{background:#f8fafc;border-bottom:1px solid var(--eaa-border);padding:14px 16px;font-size:8px;font-weight:900;text-transform:uppercase;letter-spacing:.2em;color:#94a3b8;text-align:left;}
    .table td{padding:12px 14px;border-bottom:1px solid #f1f5f9;vertical-align:top;}
    .row:hover td{background:#fcfdff;}
    .icon-btn{width:34px;height:34px;border-radius:6px;border:1px solid var(--eaa-border);background:#fff;display:inline-flex;align-items:center;justify-content:center;color:#475569;transition:.2s;}
    .icon-btn:hover{background:#0f172a;border-color:#0f172a;color:#fff;}
    .icon-btn.danger:hover{background:#ef4444;border-color:#ef4444;color:#fff;}
    .icon-btn.ok:hover{background:#16a34a;border-color:#16a34a;color:#fff;}
    .muted{color:#94a3b8;font-size:10px;font-weight:700;}
    .modal{display:none;position:fixed;inset:0;background:rgba(15,23,42,.78);backdrop-filter:blur(8px);z-index:999;align-items:center;justify-content:center;padding:18px;}
    .modal .box{width:100%;max-width:900px;background:#fff;border-radius:var(--eaa-radius);border:1px solid var(--eaa-border);padding:22px;max-height:86vh;overflow:auto;position:relative;}
    .close{position:absolute;top:12px;right:12px;width:40px;height:40px;border-radius:999px;border:1px solid var(--eaa-border);background:#fff;cursor:pointer;}
    .close:hover{background:#0f172a;color:#fff;border-color:#0f172a;}
</style>

<div class="container mx-auto px-6 pt-10 pb-20 max-w-7xl">

    <div class="flex flex-col md:flex-row md:items-end justify-between gap-6 mb-8">
        <div>
            <span class="tech-label">Admin / Sponsor Moderation</span>
            <h1 class="text-2xl md:text-4xl font-black text-slate-900 uppercase tracking-tight">Sponsor Approvals</h1>
        </div>

        <form method="get" class="flex gap-2 items-center">
            <input type="hidden" name="status" value="<?= e($status) ?>">
            <input
                name="search"
                value="<?= e($search) ?>"
                placeholder="Search company / vendor / email"
                class="px-4 py-3 border border-slate-200 eaa-radius text-[10px] font-bold uppercase tracking-widest outline-none w-72 bg-white"
            >
            <button class="btn">Search</button>
            <a class="btn" href="<?= e(url('admin/approve_sponsors.php')) ?>">Reset</a>
        </form>
    </div>

    <?php if ($statusMsg): ?>
        <div class="card mb-6" style="border-color:#a7f3d0;background:#ecfdf5;color:#065f46;">
            <div class="text-[10px] font-black uppercase tracking-widest"><?= e($statusMsg) ?></div>
        </div>
    <?php endif; ?>

    <?php if ($errorMsg): ?>
        <div class="card mb-6" style="border-color:#fecaca;background:#fef2f2;color:#991b1b;">
            <div class="text-[10px] font-black uppercase tracking-widest"><?= e($errorMsg) ?></div>
        </div>
    <?php endif; ?>

    <div class="card mb-8">
        <div class="tabs flex gap-2 border-b border-slate-100">
            <a class="<?= $status === 'pending' ? 'active' : '' ?>" href="?<?= e(http_build_query(['status'=>'pending','search'=>$search])) ?>">
                Pending <span class="badge b-pending"><?= $cntPending ?></span>
            </a>
            <a class="<?= $status === 'approved' ? 'active' : '' ?>" href="?<?= e(http_build_query(['status'=>'approved','search'=>$search])) ?>">
                Approved <span class="badge b-approved"><?= $cntApproved ?></span>
            </a>
            <a class="<?= $status === 'rejected' ? 'active' : '' ?>" href="?<?= e(http_build_query(['status'=>'rejected','search'=>$search])) ?>">
                Rejected <span class="badge b-rejected"><?= $cntRejected ?></span>
            </a>
            <a class="<?= $status === 'all' ? 'active' : '' ?>" href="?<?= e(http_build_query(['status'=>'all','search'=>$search])) ?>">
                All
            </a>
        </div>

        <div class="overflow-x-auto">
            <table class="table">
                <thead>
                    <tr>
                        <th>Company</th>
                        <th>Vendor</th>
                        <th>Status</th>
                        <th>Dates</th>
                        <th style="text-align:right;width:140px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($requests)): ?>
                    <tr><td colspan="5" class="muted" style="padding:20px;">No sponsor requests found.</td></tr>
                <?php endif; ?>

                <?php foreach ($requests as $r): ?>
                    <?php
                        $badgeClass = 'b-pending';
                        if ($r['status'] === 'approved') $badgeClass = 'b-approved';
                        if ($r['status'] === 'rejected') $badgeClass = 'b-rejected';
                        $logo = $r['logo_path'] ? asset($r['logo_path']) : '';
                        $created = $r['created_at'] ? date('d M Y, h:i A', strtotime($r['created_at'])) : '—';
                        $updated = $r['updated_at'] ? date('d M Y, h:i A', strtotime($r['updated_at'])) : '—';
                    ?>
                    <tr class="row">
                        <td>
                            <div class="text-[12px] font-black text-slate-900 uppercase tracking-tight"><?= e($r['company_name']) ?></div>
                            <?php if (!empty($r['website'])): ?>
                                <div class="muted"><a href="<?= e($r['website']) ?>" target="_blank" rel="noopener"><?= e($r['website']) ?></a></div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="text-[10px] font-black text-slate-900 uppercase"><?= e($r['full_name'] ?: 'Vendor') ?></div>
                            <div class="muted"><?= e($r['email']) ?></div>
                        </td>
                        <td>
                            <span class="badge <?= e($badgeClass) ?>"><?= e($r['status']) ?></span>
                        </td>
                        <td>
                            <div class="muted">Created: <?= e($created) ?></div>
                            <div class="muted">Updated: <?= e($updated) ?></div>
                            <?php if (!empty($r['reviewed_at'])): ?>
                                <div class="muted">Reviewed: <?= e(date('d M Y, h:i A', strtotime($r['reviewed_at']))) ?></div>
                            <?php endif; ?>
                        </td>
                        <td style="text-align:right;">
                            <div class="flex justify-end gap-2">
                                <button
                                    type="button"
                                    class="icon-btn"
                                    title="View details"
                                    onclick="openPreview(
                                        <?= (int)$r['id'] ?>,
                                        <?= json_encode($r['company_name']) ?>,
                                        <?= json_encode($r['status']) ?>,
                                        <?= json_encode($r['full_name']) ?>,
                                        <?= json_encode($r['email']) ?>,
                                        <?= json_encode($r['phone'] ?? '') ?>,
                                        <?= json_encode($r['website'] ?? '') ?>,
                                        <?= json_encode($r['note'] ?? '') ?>,
                                        <?= json_encode($logo) ?>,
                                        <?= json_encode($created) ?>,
                                        <?= json_encode($updated) ?>
                                    )"
                                >
                                    <i class="fa-solid fa-eye"></i>
                                </button>

                                <form method="post">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="id" value="<?= e((string)$r['id']) ?>">
                                    <input type="hidden" name="action" value="approve">
                                    <input type="hidden" name="return_status" value="<?= e($status) ?>">
                                    <input type="hidden" name="return_search" value="<?= e($search) ?>">
                                    <button class="icon-btn ok" title="Approve">
                                        <i class="fa-solid fa-check"></i>
                                    </button>
                                </form>

                                <form method="post">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="id" value="<?= e((string)$r['id']) ?>">
                                    <input type="hidden" name="action" value="reject">
                                    <input type="hidden" name="return_status" value="<?= e($status) ?>">
                                    <input type="hidden" name="return_search" value="<?= e($search) ?>">
                                    <button class="icon-btn danger" title="Reject">
                                        <i class="fa-solid fa-xmark"></i>
                                    </button>
                                </form>

                                <form method="post" onsubmit="return confirm('Delete this sponsor request?');">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="id" value="<?= e((string)$r['id']) ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="return_status" value="<?= e($status) ?>">
                                    <input type="hidden" name="return_search" value="<?= e($search) ?>">
                                    <button class="icon-btn danger" title="Delete">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<div id="previewModal" class="modal">
    <div class="box">
        <button class="close" type="button" onclick="closePreview()">✕</button>
        <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-4">
            <div>
                <div class="tech-label" id="pvMeta">Sponsor Request</div>
                <h2 class="text-xl md:text-2xl font-black uppercase tracking-tight text-slate-900" id="pvTitle">—</h2>
                <div class="muted mt-2" id="pvVendor">—</div>
            </div>
            <span class="badge" id="pvStatus">—</span>
        </div>

        <div id="pvLogoWrap" style="margin-top:16px; display:none;">
            <img id="pvLogo" src="" alt="Logo" style="width:100%;max-height:220px;object-fit:contain;border-radius:5px;border:1px solid #e2e8f0;background:#f8fafc;">
        </div>

        <div class="card" style="margin-top:18px;">
            <div class="text-[11px] font-black uppercase tracking-widest text-slate-500 mb-2">Details</div>
            <div class="text-sm text-slate-700" id="pvDetails"></div>
        </div>

        <div class="flex justify-end gap-2 mt-4">
            <button class="btn" type="button" onclick="closePreview()">Close</button>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/partials/footer.php'; ?>

<script>
function openPreview(id, company, status, vendorName, vendorEmail, phone, website, note, logoUrl, createdAt, updatedAt){
    document.getElementById('pvMeta').innerText = `REF: SPONSOR-${id} • Updated ${updatedAt || '—'}`;
    document.getElementById('pvTitle').innerText = company || '—';
    document.getElementById('pvVendor').innerText = `${vendorName || 'Vendor'} • ${vendorEmail || ''} • Created ${createdAt || '—'}`;

    const badge = document.getElementById('pvStatus');
    badge.innerText = status || '—';
    badge.className = `badge ${status === 'approved' ? 'b-approved' : (status === 'rejected' ? 'b-rejected' : 'b-pending')}`;

    const wrap = document.getElementById('pvLogoWrap');
    const img  = document.getElementById('pvLogo');
    if (logoUrl) {
        img.src = logoUrl;
        wrap.style.display = 'block';
    } else {
        wrap.style.display = 'none';
        img.src = '';
    }

    const details = [
        website ? `Website: ${website}` : null,
        phone ? `Phone: ${phone}` : null,
        note ? `Note: ${note}` : null
    ].filter(Boolean).join('\n');

    document.getElementById('pvDetails').innerText = details || 'No additional details provided.';

    const modal = document.getElementById('previewModal');
    modal.style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function closePreview(){
    document.getElementById('previewModal').style.display = 'none';
    document.body.style.overflow = 'auto';
}

window.addEventListener('click', (e) => {
    const modal = document.getElementById('previewModal');
    if (e.target === modal) closePreview();
});
</script>
