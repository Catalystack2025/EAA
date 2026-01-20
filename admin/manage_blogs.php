<?php
declare(strict_types=1);

declare(strict_types=1);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/../lib/helpers.php';
require_once __DIR__ . '/../config/db.php';

start_session();

// ✅ Must be admin (adjust this if your project uses different session keys)
if (empty($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
  redirect('../login.php');
}

$pageTitle = 'Blog Approvals | Admin';

$allowedStatuses = ['pending', 'published', 'rejected', 'draft'];
$status = $_GET['status'] ?? 'pending';
$search = trim($_GET['search'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 10;

if (!in_array($status, $allowedStatuses, true)) {
  $status = 'pending';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!csrf_verify($_POST['csrf_token'] ?? null)) {
    flash_set('blog_admin_error', 'Invalid session token. Please try again.');
    redirect('manage_blogs.php');
  }

  $action = $_POST['action'] ?? '';
  $blogId = (int)($_POST['blog_id'] ?? 0);
  $note = trim($_POST['admin_note'] ?? '');

  if ($blogId > 0) {
    if ($action === 'approve') {
      $stmt = db()->prepare("UPDATE blog_posts SET status='published', admin_note=:note, published_at=NOW() WHERE id=:id");
      $stmt->execute(['note' => $note ?: null, 'id' => $blogId]);
      flash_set('blog_admin_status', 'Blog approved and published.');
    } elseif ($action === 'reject') {
      $stmt = db()->prepare("UPDATE blog_posts SET status='rejected', admin_note=:note WHERE id=:id");
      $stmt->execute(['note' => $note ?: null, 'id' => $blogId]);
      flash_set('blog_admin_status', 'Blog rejected.');
    }
  }

  $redirect = 'manage_blogs.php?status=' . urlencode($status);
  if ($search !== '') $redirect .= '&search=' . urlencode($search);
  if ($page > 1) $redirect .= '&page=' . $page;

  redirect($redirect);
}

// Build filters
$filters = ['bp.status = :status'];
$params = ['status' => $status];

if ($search !== '') {
  $filters[] = '(bp.title LIKE :q OR u.full_name LIKE :q OR u.email LIKE :q)';
  $params['q'] = '%' . $search . '%';
}

$where = 'WHERE ' . implode(' AND ', $filters);

// count
$countStmt = db()->prepare(
  "SELECT COUNT(*)
   FROM blog_posts bp
   JOIN users u ON u.id = bp.user_id
   $where"
);
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($total / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

// list
$listStmt = db()->prepare(
  "SELECT bp.id, bp.title, bp.category, bp.status, bp.created_at, bp.published_at,
          u.full_name, u.email
   FROM blog_posts bp
   JOIN users u ON u.id = bp.user_id
   $where
   ORDER BY bp.created_at DESC
   LIMIT :limit OFFSET :offset"
);
foreach ($params as $k => $v) $listStmt->bindValue(':' . $k, $v);
$listStmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$listStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$listStmt->execute();
$blogs = $listStmt->fetchAll();

$statusMsg = flash_get('blog_admin_status');
$errorMsg = flash_get('blog_admin_error');

require_once __DIR__ . '/partials/header.php';
?>

<div class="max-w-7xl mx-auto px-6 pt-28 pb-16">

  <div class="flex flex-col md:flex-row md:items-end justify-between gap-6 mb-8">
    <div>
      <h1 class="text-2xl md:text-3xl font-black uppercase tracking-tight text-slate-900">Blog Approvals</h1>
      <p class="text-xs font-bold uppercase tracking-widest text-slate-400 mt-2">Approve / Reject member blog submissions</p>
    </div>
  </div>

  <?php if ($statusMsg): ?>
    <div class="mb-6 p-4 bg-green-50 border border-green-200 text-green-700 text-xs font-bold uppercase tracking-widest rounded">
      <?= e($statusMsg) ?>
    </div>
  <?php endif; ?>

  <?php if ($errorMsg): ?>
    <div class="mb-6 p-4 bg-amber-50 border border-amber-200 text-amber-700 text-xs font-bold uppercase tracking-widest rounded">
      <?= e($errorMsg) ?>
    </div>
  <?php endif; ?>

  <form method="get" class="bg-white border border-slate-100 rounded p-4 flex flex-col md:flex-row gap-4 justify-between items-center mb-8">
    <div class="flex items-center gap-3">
      <label class="text-[10px] font-black uppercase tracking-widest text-slate-400">Status</label>
      <select name="status" class="bg-slate-50 border border-slate-200 rounded px-3 py-2 text-[11px] font-bold uppercase tracking-widest">
        <?php foreach ($allowedStatuses as $opt): ?>
          <option value="<?= e($opt) ?>" <?= $status === $opt ? 'selected' : '' ?>><?= e($opt) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="flex-1 w-full md:max-w-md">
      <input name="search" value="<?= e($search) ?>" placeholder="Search title / author / email"
             class="w-full bg-slate-50 border border-slate-200 rounded px-4 py-2 text-sm outline-none" />
    </div>

    <div class="flex gap-2">
      <button class="px-5 py-2 bg-slate-900 text-white rounded text-xs font-black uppercase tracking-widest">Apply</button>
      <a href="manage_blogs.php" class="px-5 py-2 text-slate-500 rounded text-xs font-black uppercase tracking-widest">Reset</a>
    </div>
  </form>

  <div class="bg-white border border-slate-100 rounded overflow-x-auto">
    <table class="w-full text-left">
      <thead class="bg-slate-50 border-b border-slate-100">
        <tr>
          <th class="p-4 text-[10px] font-black uppercase tracking-widest text-slate-400">Title</th>
          <th class="p-4 text-[10px] font-black uppercase tracking-widest text-slate-400">Author</th>
          <th class="p-4 text-[10px] font-black uppercase tracking-widest text-slate-400">Status</th>
          <th class="p-4 text-[10px] font-black uppercase tracking-widest text-slate-400">Created</th>
          <th class="p-4 text-[10px] font-black uppercase tracking-widest text-slate-400 text-right">Action</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-50">
        <?php if (!$blogs): ?>
          <tr><td colspan="5" class="p-8 text-center text-sm text-slate-400">No blogs found.</td></tr>
        <?php endif; ?>

        <?php foreach ($blogs as $b): ?>
          <tr class="hover:bg-slate-50">
            <td class="p-4">
              <div class="font-bold text-slate-900"><?= e($b['title']) ?></div>
              <div class="text-xs text-slate-400 uppercase tracking-widest"><?= e($b['category'] ?: '—') ?></div>
            </td>
            <td class="p-4">
              <div class="text-sm font-bold text-slate-900"><?= e($b['full_name'] ?: '—') ?></div>
              <div class="text-xs text-slate-400"><?= e($b['email']) ?></div>
            </td>
            <td class="p-4">
              <span class="px-3 py-1 rounded border text-[10px] font-black uppercase tracking-widest
                <?= $b['status'] === 'published' ? 'bg-green-50 text-green-700 border-green-200' : '' ?>
                <?= $b['status'] === 'pending' ? 'bg-amber-50 text-amber-700 border-amber-200' : '' ?>
                <?= $b['status'] === 'rejected' ? 'bg-red-50 text-red-700 border-red-200' : '' ?>
                <?= $b['status'] === 'draft' ? 'bg-slate-50 text-slate-600 border-slate-200' : '' ?>
              ">
                <?= e($b['status']) ?>
              </span>
            </td>
            <td class="p-4 text-sm text-slate-600">
              <?= e(date('d M Y', strtotime($b['created_at']))) ?>
            </td>
            <td class="p-4">
              <div class="flex justify-end gap-2">
                <a href="view_blog.php?id=<?= (int)$b['id'] ?>"
                   class="px-3 py-2 border border-slate-200 rounded text-xs font-black uppercase tracking-widest text-slate-700">
                  View
                </a>

                <?php if ($b['status'] === 'pending'): ?>
                  <form method="post" class="flex gap-2 items-center">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="blog_id" value="<?= (int)$b['id'] ?>">
                    <input type="text" name="admin_note" placeholder="Admin note (optional)"
                           class="hidden lg:block bg-slate-50 border border-slate-200 rounded px-3 py-2 text-sm w-56" />

                    <button name="action" value="approve"
                            class="px-3 py-2 bg-slate-900 text-white rounded text-xs font-black uppercase tracking-widest">
                      Approve
                    </button>
                    <button name="action" value="reject"
                            class="px-3 py-2 bg-red-600 text-white rounded text-xs font-black uppercase tracking-widest">
                      Reject
                    </button>
                  </form>
                <?php endif; ?>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <div class="flex justify-between items-center mt-6">
    <div class="text-xs text-slate-400 uppercase tracking-widest font-bold">
      Page <?= (int)$page ?> / <?= (int)$totalPages ?> — Total <?= (int)$total ?>
    </div>

    <div class="flex gap-2">
      <?php
        $base = 'manage_blogs.php?status=' . urlencode($status);
        if ($search !== '') $base .= '&search=' . urlencode($search);
      ?>
      <a class="px-3 py-2 border rounded text-xs" href="<?= e($base . '&page=' . max(1, $page - 1)) ?>">Prev</a>
      <a class="px-3 py-2 border rounded text-xs" href="<?= e($base . '&page=' . min($totalPages, $page + 1)) ?>">Next</a>
    </div>
  </div>

</div>

<?php require_once __DIR__ . '/partials/footer.php'; ?>
