<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/helpers.php';
require_once __DIR__ . '/../config/db.php';

start_session();

if (($_SESSION['role'] ?? '') !== 'admin') {
    redirect('../login.php');
}

$pageTitle = 'Council Profile Requests | EAA Admin';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        redirect(basename(__FILE__));
    }

    $id = (int)($_POST['id'] ?? 0);
    $action = $_POST['action'] ?? '';

    $statusMap = [
        'approve' => 'approved',
        'reject'  => 'rejected',
    ];

    if ($id > 0 && isset($statusMap[$action])) {
        $stmt = db()->prepare(
            "UPDATE team_members
             SET approval_status = :st, reviewed_at = NOW()
             WHERE id = :id"
        );
        $stmt->execute(['st' => $statusMap[$action], 'id' => $id]);
    }

    redirect(basename(__FILE__));
}

$stmt = db()->prepare(
    "SELECT tm.id, tm.user_id, tm.photo_path, tm.title, tm.category, tm.visible, tm.approval_status, tm.updated_at,
            u.full_name, u.email
     FROM team_members tm
     JOIN users u ON u.id = tm.user_id
     WHERE tm.visible = 1
     ORDER BY tm.updated_at DESC"
);
$stmt->execute();
$rows = $stmt->fetchAll();

require_once __DIR__ . '/partials/header.php';
?>

<div class="max-w-6xl mx-auto px-6 py-10">
    <div class="flex items-end justify-between mb-8">
        <div>
            <span class="text-[8px] font-black uppercase tracking-[0.4em] text-slate-400 block mb-2">Council Listing Requests</span>
            <h1 class="font-druk text-3xl md:text-5xl text-slate-900 uppercase">Team Profile <span class="text-slate-400 italic">Review</span></h1>
        </div>
        <a href="manage_members.php" class="px-6 py-3 bg-white border border-slate-200 eaa-radius text-[9px] font-black uppercase tracking-widest text-slate-600">
            Back to Members
        </a>
    </div>

    <div class="bg-white border border-slate-200 eaa-radius overflow-x-auto">
        <table class="w-full text-left">
            <thead class="bg-slate-50 border-b border-slate-100">
                <tr>
                    <th class="p-4 text-[8px] font-black uppercase tracking-[0.2em] text-slate-400">Member</th>
                    <th class="p-4 text-[8px] font-black uppercase tracking-[0.2em] text-slate-400">Photo</th>
                    <th class="p-4 text-[8px] font-black uppercase tracking-[0.2em] text-slate-400">Title</th>
                    <th class="p-4 text-[8px] font-black uppercase tracking-[0.2em] text-slate-400">Category</th>
                    <th class="p-4 text-[8px] font-black uppercase tracking-[0.2em] text-slate-400">Status</th>
                    <th class="p-4 text-right text-[8px] font-black uppercase tracking-[0.2em] text-slate-400">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php if (empty($rows)): ?>
                    <tr>
                        <td colspan="6" class="p-8 text-center text-[10px] font-bold uppercase tracking-widest text-slate-400">
                            No council profile requests found.
                        </td>
                    </tr>
                <?php endif; ?>

                <?php foreach ($rows as $r): ?>
                    <?php
                    $photo = $r['photo_path'] ? asset($r['photo_path']) : 'https://via.placeholder.com/80x110?text=EAA';
                    $badge = 'bg-amber-50 text-amber-700 border-amber-100';
                    if ($r['approval_status'] === 'approved') $badge = 'bg-green-50 text-green-700 border-green-100';
                    if ($r['approval_status'] === 'rejected') $badge = 'bg-red-50 text-red-700 border-red-100';
                    ?>
                    <tr class="hover:bg-slate-50">
                        <td class="p-4">
                            <div class="text-[11px] font-bold text-slate-900"><?= e($r['full_name']) ?></div>
                            <div class="text-[9px] font-bold text-slate-400"><?= e($r['email']) ?></div>
                        </td>
                        <td class="p-4">
                            <img src="<?= e($photo) ?>" class="w-14 h-20 object-cover eaa-radius border border-slate-200" alt="photo">
                        </td>
                        <td class="p-4 text-[10px] font-bold text-slate-700 uppercase tracking-widest"><?= e($r['title']) ?></td>
                        <td class="p-4 text-[10px] font-bold text-slate-700 uppercase tracking-widest"><?= e($r['category']) ?></td>
                        <td class="p-4">
                            <span class="px-3 py-1 text-[8px] font-black uppercase tracking-widest rounded border <?= $badge ?>">
                                <?= e($r['approval_status']) ?>
                            </span>
                        </td>
                        <td class="p-4">
                            <div class="flex justify-end gap-2">
                                <form method="post">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="id" value="<?= e((string)$r['id']) ?>">
                                    <input type="hidden" name="action" value="approve">
                                    <button class="px-4 py-2 bg-green-600 text-white text-[9px] font-black uppercase tracking-widest eaa-radius">
                                        Approve
                                    </button>
                                </form>

                                <form method="post">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="id" value="<?= e((string)$r['id']) ?>">
                                    <input type="hidden" name="action" value="reject">
                                    <button class="px-4 py-2 bg-red-600 text-white text-[9px] font-black uppercase tracking-widest eaa-radius">
                                        Reject
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

<?php require_once __DIR__ . '/partials/footer.php'; ?>
