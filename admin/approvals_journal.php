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

function is_safe_url(?string $url): bool
{
    $url = trim((string)$url);
    if ($url === '' || str_starts_with($url, '#')) {
        return true;
    }

    $lower = strtolower($url);
    if (str_starts_with($lower, 'http://') || str_starts_with($lower, 'https://')) {
        return true;
    }

    return !preg_match('/^[a-z][a-z0-9+.-]*:/', $lower);
}

function sanitize_blog_html(?string $html): string
{
    $html = (string)$html;
    if (trim($html) === '') {
        return '';
    }

    $allowedTags = ['p', 'br', 'b', 'strong', 'i', 'em', 'ul', 'ol', 'li', 'h1', 'h2', 'h3', 'h4', 'blockquote', 'img', 'a'];
    $allowedAttrs = [
        'a' => ['href', 'target', 'rel'],
        'img' => ['src', 'alt'],
    ];

    $doc = new DOMDocument();
    libxml_use_internal_errors(true);
    $doc->loadHTML('<?xml encoding="utf-8" ?>' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();

    $removeNodes = [];
    foreach ($doc->getElementsByTagName('*') as $node) {
        $tag = $node->nodeName;
        if (!in_array($tag, $allowedTags, true)) {
            $removeNodes[] = $node;
            continue;
        }

        if ($node->hasAttributes()) {
            $attrsToRemove = [];
            foreach (iterator_to_array($node->attributes) as $attr) {
                $name = strtolower($attr->nodeName);
                if (str_starts_with($name, 'on')) {
                    $attrsToRemove[] = $name;
                    continue;
                }
                if (!in_array($name, $allowedAttrs[$tag] ?? [], true)) {
                    $attrsToRemove[] = $name;
                    continue;
                }
                if (($name === 'href' || $name === 'src') && !is_safe_url($attr->nodeValue)) {
                    $attrsToRemove[] = $name;
                }
            }
            foreach ($attrsToRemove as $attrName) {
                $node->removeAttribute($attrName);
            }
        }
    }

    foreach ($removeNodes as $node) {
        $node->parentNode?->removeChild($node);
    }

    return $doc->saveHTML();
}

function build_return_url(string $status, string $search): string
{
    $query = array_filter([
        'status' => $status,
        'search' => $search,
    ], static fn($value) => $value !== '');

    $suffix = $query ? ('?' . http_build_query($query)) : '';
    return 'admin/approvals_journal.php' . $suffix;
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
$hasCategory    = column_exists('member_blogs', 'category');
$hasUpdatedAt   = column_exists('member_blogs', 'updated_at');

/* -----------------------------
   Handle actions
------------------------------ */
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
        flash_set('error', 'Invalid blog ID.');
        redirect($returnUrl);
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
        redirect($returnUrl);
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
        redirect($returnUrl);
    }

    if ($action === 'delete') {
        $imgStmt = db()->prepare('SELECT featured_image FROM member_blogs WHERE id = ?');
        $imgStmt->execute([$id]);
        $featuredImage = (string)($imgStmt->fetchColumn() ?? '');

        $stmt = db()->prepare('DELETE FROM member_blogs WHERE id = ?');
        $stmt->execute([$id]);

        if ($featuredImage !== '' && !str_contains($featuredImage, '://') && !str_starts_with($featuredImage, '//')) {
            $relativePath = ltrim($featuredImage, '/');
            $absPath = __DIR__ . '/../' . $relativePath;
            $rootPath = realpath(__DIR__ . '/..');
            $realPath = realpath($absPath);
            if ($realPath && $rootPath && str_starts_with($realPath, $rootPath) && is_file($realPath)) {
                @unlink($realPath);
            }
        }

        flash_set('status', 'Blog deleted.');
        redirect($returnUrl);
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
if (!$hasUpdatedAt) {
    $orderBy = 'b.created_at DESC';
}

$sql = "
SELECT
    b.id,
    b.user_id,
    b.title,
    " . ($hasCategory ? "b.category" : "'' AS category") . ",
    b.status,
    b.featured_image,
    b.created_at,
    " . ($hasUpdatedAt ? "b.updated_at" : "b.created_at AS updated_at") . ",
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
    .icon-btn{
        width:34px;height:34px;border-radius:6px;border:1px solid var(--eaa-border);background:#fff;
        display:inline-flex;align-items:center;justify-content:center;color:#475569;transition:.2s;
    }
    .icon-btn:hover{background:#0f172a;border-color:#0f172a;color:#fff;}
    .icon-btn.danger:hover{background:#ef4444;border-color:#ef4444;color:#fff;}
    .icon-btn.ok:hover{background:#16a34a;border-color:#16a34a;color:#fff;}
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
    .table td{padding:12px 14px;border-bottom:1px solid #f1f5f9;vertical-align:top;}
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
    .category-badge{
        background:#f1f5f9;color:#0f172a;border:1px solid #e2e8f0;
        font-size:8px;font-weight:900;text-transform:uppercase;letter-spacing:.12em;
        padding:4px 8px;border-radius:3px;display:inline-flex;align-items:center;
    }
    .meta-row{display:flex;gap:12px;flex-wrap:wrap;}
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
            <a class="btn" href="<?= e(url('admin/approvals_journal.php')) ?>">Reset</a>
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
                        <th style="text-align:right;width:140px;">Actions</th>
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
                        $safeContent = sanitize_blog_html($b['content_html']);
                    ?>
                    <tr class="row">
                        <td>
                            <div class="meta-row mb-2">
                                <span class="tech-label">REF: BLOG-<?= e((string)$b['id']) ?></span>
                                <span class="category-badge"><?= e($b['category'] ?: 'General') ?></span>
                            </div>
                            <div class="text-[12px] font-black text-slate-900 uppercase tracking-tight"><?= e($b['title']) ?></div>
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
                                <button
                                    type="button"
                                    class="icon-btn"
                                    title="View blog"
                                    onclick="openPreview(
                                        <?= (int)$b['id'] ?>,
                                        <?= json_encode($b['title']) ?>,
                                        <?= json_encode($b['category'] ?: 'General') ?>,
                                        <?= json_encode($b['full_name']) ?>,
                                        <?= json_encode($b['email']) ?>,
                                        <?= json_encode($b['status']) ?>,
                                        <?= json_encode($featured) ?>,
                                        <?= json_encode($safeContent) ?>,
                                        <?= json_encode($created) ?>,
                                        <?= json_encode($updated) ?>
                                    )"
                                >
                                    <i class="fa-solid fa-eye"></i>
                                </button>

                                <?php if ($b['status'] === 'pending'): ?>
                                    <form method="post">
                                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                        <input type="hidden" name="id" value="<?= e((string)$b['id']) ?>">
                                        <input type="hidden" name="action" value="approve">
                                        <input type="hidden" name="return_status" value="<?= e($status) ?>">
                                        <input type="hidden" name="return_search" value="<?= e($search) ?>">
                                        <button class="icon-btn ok" title="Approve">
                                            <i class="fa-solid fa-check"></i>
                                        </button>
                                    </form>

                                    <form method="post">
                                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                        <input type="hidden" name="id" value="<?= e((string)$b['id']) ?>">
                                        <input type="hidden" name="action" value="reject">
                                        <input type="hidden" name="return_status" value="<?= e($status) ?>">
                                        <input type="hidden" name="return_search" value="<?= e($search) ?>">
                                        <button class="icon-btn danger" title="Reject">
                                            <i class="fa-solid fa-xmark"></i>
                                        </button>
                                    </form>
                                <?php endif; ?>

                                <form method="post" onsubmit="return confirm('Delete this blog permanently?');">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="id" value="<?= e((string)$b['id']) ?>">
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

<!-- PREVIEW MODAL -->
<div id="previewModal" class="modal">
    <div class="box">
        <button class="close" type="button" onclick="closePreview()">✕</button>

        <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-4">
            <div>
                <div class="tech-label" id="pvMeta">Preview</div>
                <h2 class="text-xl md:text-2xl font-black uppercase tracking-tight text-slate-900" id="pvTitle">—</h2>
                <div class="muted mt-2" id="pvAuthor">—</div>
            </div>
            <span class="badge" id="pvStatusBadge">—</span>
        </div>

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
function openPreview(id, title, category, fullName, email, status, featuredUrl, contentHtml, createdAt, updatedAt){
    document.getElementById('pvMeta').innerText = `REF: BLOG-${id} • ${category}`;
    document.getElementById('pvTitle').innerText = title || '—';
    document.getElementById('pvAuthor').innerText = `${fullName || 'Member'} • ${email || ''} • Created ${createdAt || '—'} • Updated ${updatedAt || '—'}`;

    const badge = document.getElementById('pvStatusBadge');
    badge.innerText = status || '—';
    badge.className = `badge ${status === 'published' ? 'b-published' : (status === 'rejected' ? 'b-rejected' : 'b-pending')}`;

    const wrap = document.getElementById('pvFeaturedWrap');
    const img  = document.getElementById('pvFeatured');

    if (featuredUrl) {
        img.src = featuredUrl;
        wrap.style.display = 'block';
    } else {
        wrap.style.display = 'none';
        img.src = '';
    }

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
