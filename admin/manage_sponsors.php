<?php
/* =========================================================
   admin/manage_sponsors.php — SPONSORSHIP APPROVALS
   ========================================================= */

require_once __DIR__ . '/../lib/helpers.php';
require_once __DIR__ . '/../config/db.php';

start_session();

redirect('admin/approve_sponsors.php');

$admin = [
    'name' => 'Admin Node 01',
    'id' => 'EAA-ROOT-X1',
    'access_level' => 'Full Administrative',
    'uptime' => '14 Days, 6 Hours'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $sponsorId = (int) ($_POST['sponsor_id'] ?? 0);

    if (in_array($action, ['approve', 'reject'], true) && $sponsorId > 0) {
        $status = $action === 'approve' ? 'approved' : 'rejected';
        $stmt = db()->prepare('UPDATE sponsors SET status = :status WHERE id = :id');
        $stmt->execute([
            'status' => $status,
            'id' => $sponsorId,
        ]);
    }

    redirect('admin/manage_sponsors.php');
}

$stmt = db()->query(
    "SELECT sponsors.*, vendor_profile.company_name AS vendor_company
     FROM sponsors
     JOIN vendor_profile ON sponsors.vendor_id = vendor_profile.id
     ORDER BY sponsors.created_at DESC"
);
$sponsors = $stmt->fetchAll();

$pageTitle = 'Sponsor Approvals | EAA Admin';
require_once __DIR__ . '/partials/header.php';
?>

<div class="metric-card reveal active">
    <div class="flex items-center justify-between mb-8">
        <h3 class="font-druk text-xl uppercase">Sponsor <span class="text-slate-400 italic">Approvals</span></h3>
        <span class="text-[8px] font-black uppercase tracking-widest px-4 py-1 border border-slate-100 eaa-radius">Total Requests: <?= e((string) count($sponsors)) ?></span>
    </div>

    <?php if (empty($sponsors)): ?>
        <p class="text-xs uppercase tracking-widest text-slate-400">No sponsorship submissions yet.</p>
    <?php else: ?>
        <div class="space-y-4">
            <?php foreach ($sponsors as $sponsor): ?>
                <div class="border border-slate-100 eaa-radius p-6 bg-white flex flex-col lg:flex-row lg:items-center lg:justify-between gap-6">
                    <div>
                        <span class="tech-label">Status: <?= e($sponsor['status']) ?></span>
                        <h4 class="text-sm font-black uppercase text-slate-900"><?= e($sponsor['company_name']) ?></h4>
                        <p class="text-[10px] font-bold uppercase tracking-widest text-slate-400">Vendor: <?= e($sponsor['vendor_company']) ?></p>
                        <?php if (!empty($sponsor['short_desc'])): ?>
                            <p class="text-xs text-slate-500 mt-2"><?= e($sponsor['short_desc']) ?></p>
                        <?php endif; ?>
                        <?php if (!empty($sponsor['website'])): ?>
                            <a href="<?= e($sponsor['website']) ?>" class="text-[10px] font-black uppercase tracking-widest text-slate-500" target="_blank" rel="noreferrer">Visit Website</a>
                        <?php endif; ?>
                    </div>
                    <div class="flex items-center gap-3">
                        <img src="<?= e($sponsor['logo_path']) ?>" alt="<?= e($sponsor['company_name']) ?>" class="h-12 w-12 object-contain border border-slate-100 eaa-radius" onerror="this.src='https://via.placeholder.com/80x80?text=Logo'">
                        <form method="post" class="flex items-center gap-2">
                            <input type="hidden" name="sponsor_id" value="<?= e((string) $sponsor['id']) ?>">
                            <button name="action" value="approve" class="px-4 py-2 bg-slate-900 text-white text-[8px] font-black uppercase tracking-widest eaa-radius">Approve</button>
                            <button name="action" value="reject" class="px-4 py-2 border border-slate-200 text-slate-500 text-[8px] font-black uppercase tracking-widest eaa-radius">Reject</button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/partials/footer.php'; ?>
