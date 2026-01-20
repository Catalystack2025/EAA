<?php
/* =========================================================
   admin/manage_blogs.php — BLOG APPROVAL CONSOLE (ADMIN)
   ✅ Approve / Reject member blog submissions
   ✅ Preview content + featured image
   ✅ Works even if published_at column does NOT exist
   ✅ Uses member_blogs table (your current structure)
   ========================================================= */

declare(strict_types=1);

require_once __DIR__ . '/../lib/helpers.php';
require_once __DIR__ . '/../config/db.php';

start_session();

/* -----------------------------
   Auth guard (admin only)
------------------------------ */
$role = $_SESSION['role'] ?? null;
if ($role !== 'admin') {
    redirect('login.php');
}

$pageTitle = 'Manage Blogs | Admin';

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

/* -----------------------------
   Validate required table
------------------------------ */
if (!table_exists('member_blogs')) {
    die('member_blogs table not found. Create it from accountpage.php blog section first.');
}

$hasPublishedAt = column_exists('member_blogs', 'published_at');
$hasReviewedAt  = column_exists('member_blogs', 'reviewed_at');
$hasReviewedBy  = column_exists('member_blogs', 'reviewed_by');

/* -----------------------------
   Handle actions
------------------------------ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);

    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        flash_set('error', 'Invalid session token. Please try again.');
        redirect('manage_blogs.php');
    }

    if ($id <= 0) {
        flash_set('error', 'Invalid blog ID.');
        redirect('manage_blogs.php');
    }

    if ($action === 'approve') {
        // build dynamic update based on available columns
        $set = ['status = "published"'];
        if ($hasPublishedAt) $set[] = 'published_at = NOW()';
        if ($hasReviewedAt)  $set[] = 'reviewed_at = NOW()';
        if ($hasReviewedBy)  $set[] = 'reviewed_by = :admin_id';

        $sql = 'UPDATE member_blogs SET ' . implode(', ', $set) . ' WHERE id = :id';
        $stmt = db()->prepare($sql);
        $params = ['id' => $id];
        if ($hasReviewedBy) $params['admin_id'] = (int)($_SESSION['user_id'] ?? 0);
        $stmt->execute($params);

        flash_set('status', 'Blog approved and published.');
        redirect('manage_blogs.php?status=pending');
    }

    if ($action === 'reject') {
        $set = ['status = "rejected"'];
        if ($hasReviewedAt)  $set[] = 'reviewed_at = NOW()';
        if ($hasReviewedBy)  $set[] = 'reviewed_by = :admin_id';

        $sql = 'UPDATE member_blogs SET ' . implode(', ', $set) . ' WHERE id = :id';
        $stmt = db()->prepare($sql);
        $params = ['id' => $id];
        if ($hasReviewedBy) $params['admin_id'] = (int)($_SESSION['user_id'] ?? 0);
        $stmt->execute($params);

        flash_set('status', 'Blog rejected.');
        redirect('manage_blogs.php?status=pending');
    }

    if ($action === 'delete') {
        $stmt = db()->prepare('DELETE FROM member_blogs WHERE id = ?');
        $stmt->execute([$id]);
        flash_set('status', 'Blog deleted.');
        redirect('manage_blogs.php');
    }
}

/* -----------------------------
   Filters
------------------------------ */
$allowed = ['pending', 'published', 'rejected', 'draft', 'all'];
$status = $_GET['status'] ?? 'pending';
if (!in_array($status, $allowed, true)) $status = 'pending';

$search = trim($_GET['search'] ?? '');

$where = [];
$params = [];

if ($status !== 'all') {
    $where[] = 'b.status = :status';
    $params['status'] = $status;
}

if ($search !== '') {
    $where[] = '(b.title LIKE :q OR u.full_name LIKE :q OR u.email LIKE :q)';
    $params['q'] = '%' . $search . '%';
}

$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

/* -----------------------------
   Counts for tabs
------------------------------ */
$countBy = function(string $st): int {
    $stmt = db()->prepare('SELECT COUNT(*) FROM member_blogs WHERE status = ?');
    $stmt->execute([$st]);
    return (int)$stmt->fetchColumn();
};
$cntPending   = $countBy('pending');
$cntPublished = $countBy('published');
$cntRejected  = $countBy('rejected');

/* -----------------------------
   Fetch list
------------------------------ */
$orderBy = 'b.updated_at DESC';
if (!column_exists('member_blogs', 'updated_at')) {
    $orderBy = 'b.created_at DESC';
}

$sql = "
SELECT
    b.id,
    b.user_id,
    b.title,
    b.category,
    b.status,
    b.featured_image,
    b.created_at,
    " . (column_exists('member_blogs', 'updated_at') ? "b.updated_at" : "b.created_at AS updated_at") . ",
    " . ($hasPublishedAt ? "b.published_at" : "NULL AS published_at") . ",
    b.content_html,
    u.full_name,
    u.email
FROM member_blogs b
JOIN users u ON u.id = b.user_id
{$whereSql}
ORDER BY {$orderBy}
LIMIT 200
";

$stmt = db()->prepare($sql);
$stmt->execute($params);
$blogs = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* -----------------------------
   Flash
------------------------------ */
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
    .badge{
        font-size:7px;font-weight:900;text-transform:uppercase;letter-spacing:.12em;
        padding:4px 10px;border-radius:2px;border:1px solid var(--eaa-border);
        display:inline-flex;align-items:center;gap:6px;
    }
    .b-pending{background:#fffbeb;color:#92400e;border-color:#fde68a;}
    .b-published{background:#ecfdf5;color:#065f46;border-color:#a7f3d0;}
    .b-rejected{background:#fef2f2;color:#991b1b;border-color:#fecaca;}
    .card{background:#fff;border:1px solid var(--eaa-border);border-radius:var(--eaa-radius);padding:22px;}
    .btn{
        padding:10px 14px;border-radius:var(--eaa-radius);
        font-size:9px;font-weight:900;text-transform:uppercase;letter-spacing:.14em;
        border:1px solid var(--eaa-border);background:#fff;color:var(--eaa-smoke);
        transition:.2s;
    }
    .btn:hover{background:#0f172a;color:#fff;border-color:#0f172a;}
    .btn-danger:hover{background:#ef4444;border-color:#ef4444;}
    .btn-ok:hover{background:#16a34a;border-color:#16a34a;}
    .tabs a{
        font-size:9px;font-weight:900;text-transform:uppercase;letter-spacing:.14em;
        padding:12px 14px;border-bottom:2px solid transparent;color:#94a3b8;
        display:inline-flex;gap:10px;align-items:center;
    }
    .tabs a.active{color:var(--eaa-ink);border-bottom-color:var(--eaa-ink);}
    .table{width:100%;border-collapse:separate;border-spacing:0;}
    .table th{
        background:#f8fafc;border-bottom:1px solid var(--eaa-border);
        padding:14px 16px;font-size:8px;font-weight:900;text-transform:uppercase;letter-spacing:.2em;color:#94a3b8;
        text-align:left;
    }
    .table td{padding:16px;border-bottom:1px solid #f1f5f9;vertical-align:top;}
    .row:hover td{background:#fcfdff;}
    .modal{
        display:none;position:fixed;inset:0;background:rgba(15,23,42,.78);
        backdrop-filter:blur(8px);z-index:999;align-items:center;justify-content:center;padding:18px;
    }
    .modal .box{
        width:100%;max-width:900px;background:#fff;border-radius:var(--eaa-radius);
        border:1px solid var(--eaa-border);padding:22px;max-height:86vh;overflow:auto;
        position:relative;
    }
    .close{
        position:absolute;top:12px;right:12px;width:40px;height:40px;border-radius:999px;
        border:1px solid var(--eaa-border);background:#fff;cursor:pointer;
    }
    .close:hover{background:#0f172a;color:#fff;border-color:#0f172a;}
    .muted{color:#94a3b8;font-size:10px;font-weight:700;}
</style>

<div class="container mx-auto px-6 pt-10 pb-20 max-w-7xl">

    <div class="flex flex-col md:flex-row md:items-end justify-between gap-6 mb-8">
        <div>
            <span class="tech-label">Admin / Content Moderation</span>
            <h1 class="text-2xl md:text-4xl font-black text-slate-900 uppercase tracking-tight">Blog Approval</h1>
        </div>

        <form method="get" class="flex gap-2 items-center">
            <input type="hidden" name="status" value="<?= e($status) ?>">
            <input
                name="search"
                value="<?= e($search) ?>"
                placeholder="Search title / author / email"
                class="px-4 py-3 border border-slate-200 eaa-radius text-[10px] font-bold uppercase tracking-widest outline-none w-72 bg-white"
            >
            <button class="btn">Search</button>
            <a class="btn" href="<?= e(url('admin/manage_blogs.php')) ?>">Reset</a>
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
            <a class="<?= $status === 'published' ? 'active' : '' ?>" href="?<?= e(http_build_query(['status'=>'published','search'=>$search])) ?>">
                Published <span class="badge b-published"><?= $cntPublished ?></span>
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
                        <th>Post</th>
                        <th>Author</th>
                        <th>Status</th>
                        <th>Dates</th>
                        <th style="text-align:right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($blogs)): ?>
                    <tr><td colspan="5" class="muted" style="padding:20px;">No blog posts found.</td></tr>
                <?php endif; ?>

                <?php foreach ($blogs as $b): ?>
                    <?php
                        $badgeClass = 'b-pending';
                        if ($b['status'] === 'published') $badgeClass = 'b-published';
                        if ($b['status'] === 'rejected') $badgeClass = 'b-rejected';
                        $featured = $b['featured_image'] ? asset($b['featured_image']) : '';
                        $created = $b['created_at'] ? date('d M Y, h:i A', strtotime($b['created_at'])) : '—';
                        $updated = $b['updated_at'] ? date('d M Y, h:i A', strtotime($b['updated_at'])) : '—';
                    ?>
                    <tr class="row">
                        <td>
                            <div class="tech-label">REF: BLOG-<?= e((string)$b['id']) ?> • <?= e($b['category'] ?: 'General') ?></div>
                            <div class="text-[12px] font-black text-slate-900 uppercase tracking-tight mb-2"><?= e($b['title']) ?></div>
                            <button
                                type="button"
                                class="btn"
                                onclick="openPreview(
                                    <?= (int)$b['id'] ?>,
                                    <?= json_encode($b['title']) ?>,
                                    <?= json_encode($b['full_name']) ?>,
                                    <?= json_encode($b['email']) ?>,
                                    <?= json_encode($b['status']) ?>,
                                    <?= json_encode($featured) ?>,
                                    <?= json_encode($b['content_html']) ?>
                                )"
                            >Preview</button>
                        </td>

                        <td>
                            <div class="text-[10px] font-black text-slate-900 uppercase"><?= e($b['full_name'] ?: 'Member') ?></div>
                            <div class="muted"><?= e($b['email']) ?></div>
                        </td>

                        <td>
                            <span class="badge <?= e($badgeClass) ?>"><?= e($b['status']) ?></span>
                        </td>

                        <td>
                            <div class="muted">Created: <?= e($created) ?></div>
                            <div class="muted">Updated: <?= e($updated) ?></div>
                            <?php if (!empty($b['published_at'])): ?>
                                <div class="muted">Published: <?= e(date('d M Y, h:i A', strtotime($b['published_at']))) ?></div>
                            <?php endif; ?>
                        </td>

                        <td style="text-align:right;">
                            <div class="flex justify-end gap-2">
                                <?php if ($b['status'] === 'pending'): ?>
                                    <form method="post">
                                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                        <input type="hidden" name="id" value="<?= e((string)$b['id']) ?>">
                                        <input type="hidden" name="action" value="approve">
                                        <button class="btn btn-ok">Approve</button>
                                    </form>

                                    <form method="post">
                                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                        <input type="hidden" name="id" value="<?= e((string)$b['id']) ?>">
                                        <input type="hidden" name="action" value="reject">
                                        <button class="btn btn-danger">Reject</button>
                                    </form>
                                <?php endif; ?>

                                <form method="post" onsubmit="return confirm('Delete this blog permanently?');">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="id" value="<?= e((string)$b['id']) ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <button class="btn btn-danger">Delete</button>
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

<!-- PREVIEW MODAL -->
<div id="previewModal" class="modal">
    <div class="box">
        <button class="close" type="button" onclick="closePreview()">✕</button>

        <div class="tech-label" id="pvMeta">Preview</div>
        <h2 class="text-xl md:text-2xl font-black uppercase tracking-tight text-slate-900" id="pvTitle">—</h2>
        <div class="muted mt-2" id="pvAuthor">—</div>

        <div id="pvFeaturedWrap" style="margin-top:16px; display:none;">
            <img id="pvFeatured" src="" alt="Featured" style="width:100%;max-height:320px;object-fit:cover;border-radius:5px;border:1px solid #e2e8f0;">
        </div>

        <div class="card" style="margin-top:18px;">
            <div id="pvContent" style="font-size:14px;line-height:1.7;color:#0f172a;"></div>
        </div>

        <div class="flex justify-end gap-2 mt-4">
            <button class="btn" type="button" onclick="closePreview()">Close</button>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/partials/footer.php'; ?>

<script>
function openPreview(id, title, fullName, email, status, featuredUrl, contentHtml){
    document.getElementById('pvMeta').innerText = `REF: BLOG-${id} • STATUS: ${status}`;
    document.getElementById('pvTitle').innerText = title || '—';
    document.getElementById('pvAuthor').innerText = `${fullName || 'Member'} • ${email || ''}`;

    const wrap = document.getElementById('pvFeaturedWrap');
    const img  = document.getElementById('pvFeatured');

    if (featuredUrl) {
        img.src = featuredUrl;
        wrap.style.display = 'block';
    } else {
        wrap.style.display = 'none';
        img.src = '';
    }

    // Render HTML (admin preview). If you want extra safety, sanitize later.
    document.getElementById('pvContent').innerHTML = contentHtml || '<p class="muted">No content.</p>';

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
