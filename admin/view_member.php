<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/helpers.php';
require_once __DIR__ . '/../config/db.php';

start_session();

// ✅ Admin only
if (($_SESSION['role'] ?? '') !== 'admin') {
    redirect('../login.php');
}

$memberId = (int)($_GET['id'] ?? 0);
if ($memberId <= 0) {
    redirect('manage_members.php');
}

/* =========================================================
   MEMBER + PROFILE (only existing columns)
   ========================================================= */
$memberStmt = db()->prepare(
    "SELECT u.id, u.full_name, u.email, u.status, u.created_at,
            mp.phone
     FROM users u
     JOIN member_profile mp ON mp.user_id = u.id
     WHERE u.id = :id AND u.role = 'member'
     LIMIT 1"
);
$memberStmt->execute(['id' => $memberId]);
$member = $memberStmt->fetch();

if (!$member) {
    redirect('manage_members.php');
}

/* =========================================================
   EVENT STATS
   NOTE: your current event_registrations table may not have 'status'
   ========================================================= */
$hasStatusCol = (function (): bool {
    $stmt = db()->prepare(
        "SELECT COUNT(*)
         FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = 'event_registrations'
           AND COLUMN_NAME = 'status'"
    );
    $stmt->execute();
    return (int)$stmt->fetchColumn() > 0;
})();

if ($hasStatusCol) {
    $statsStmt = db()->prepare(
        "SELECT
            COUNT(*) AS total_registered,
            SUM(CASE WHEN status='attended' THEN 1 ELSE 0 END) AS total_attended
         FROM event_registrations
         WHERE user_id = :uid"
    );
} else {
    $statsStmt = db()->prepare(
        "SELECT COUNT(*) AS total_registered, 0 AS total_attended
         FROM event_registrations
         WHERE user_id = :uid"
    );
}
$statsStmt->execute(['uid' => $memberId]);
$stats = $statsStmt->fetch() ?: ['total_registered' => 0, 'total_attended' => 0];

/* =========================================================
   EVENT LIST
   ========================================================= */
if ($hasStatusCol) {
    $eventsStmt = db()->prepare(
        "SELECT e.title, e.start_date, e.location, er.status
         FROM event_registrations er
         JOIN events e ON e.id = er.event_id
         WHERE er.user_id = :uid
         ORDER BY e.start_date DESC"
    );
} else {
    $eventsStmt = db()->prepare(
        "SELECT e.title, e.start_date, e.location, 'registered' AS status
         FROM event_registrations er
         JOIN events e ON e.id = er.event_id
         WHERE er.user_id = :uid
         ORDER BY e.start_date DESC"
    );
}
$eventsStmt->execute(['uid' => $memberId]);
$events = $eventsStmt->fetchAll();

$pageTitle = 'View Member | EAA Admin';
require_once __DIR__ . '/partials/header.php';

$joined = $member['created_at'] ? date('d M Y', strtotime((string)$member['created_at'])) : '—';
?>

<div class="max-w-6xl mx-auto px-6 py-10">

    <div class="flex items-end justify-between gap-6 mb-8">
        <div>
            <span class="text-[8px] font-black uppercase tracking-[0.4em] text-slate-400 block mb-2">Member Details</span>
            <h1 class="font-druk text-3xl md:text-5xl text-slate-900 uppercase">
                <?= e($member['full_name']) ?>
            </h1>
            <p class="text-[10px] font-bold uppercase tracking-widest text-slate-400 mt-2">
                EAA-MEM-<?= e((string)$member['id']) ?> • Joined <?= e($joined) ?>
            </p>
        </div>

        <a href="manage_members.php"
           class="px-6 py-3 bg-white border border-slate-200 eaa-radius text-[9px] font-black uppercase tracking-widest text-slate-600">
            Back
        </a>
    </div>

    <!-- Stats -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-10">
        <div class="bg-white border border-slate-200 eaa-radius p-6">
            <div class="text-[8px] font-black uppercase tracking-[0.3em] text-slate-400 mb-2">Status</div>
            <div class="text-xl font-black text-slate-900 uppercase"><?= e($member['status']) ?></div>
        </div>

        <div class="bg-white border border-slate-200 eaa-radius p-6">
            <div class="text-[8px] font-black uppercase tracking-[0.3em] text-slate-400 mb-2">Events Registered</div>
            <div class="text-xl font-black text-slate-900"><?= e((string)$stats['total_registered']) ?></div>
        </div>

        <div class="bg-white border border-slate-200 eaa-radius p-6">
            <div class="text-[8px] font-black uppercase tracking-[0.3em] text-slate-400 mb-2">Events Attended</div>
            <div class="text-xl font-black text-green-700"><?= e((string)$stats['total_attended']) ?></div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

        <!-- Profile -->
        <div class="lg:col-span-1 bg-white border border-slate-200 eaa-radius p-6">
            <h3 class="font-druk text-xl uppercase mb-6">Profile</h3>

            <div class="space-y-4">
                <div>
                    <div class="text-[8px] font-black uppercase tracking-[0.3em] text-slate-400">Email</div>
                    <div class="text-[12px] font-bold text-slate-900"><?= e($member['email']) ?></div>
                </div>

                <div>
                    <div class="text-[8px] font-black uppercase tracking-[0.3em] text-slate-400">Phone</div>
                    <div class="text-[12px] font-bold text-slate-900"><?= e($member['phone'] ?? '—') ?></div>
                </div>
            </div>
        </div>

        <!-- Events -->
        <div class="lg:col-span-2 bg-white border border-slate-200 eaa-radius p-6">
            <div class="flex items-end justify-between mb-6">
                <h3 class="font-druk text-xl uppercase">Events</h3>
                <span class="text-[8px] font-black uppercase tracking-[0.3em] text-slate-400">Registrations</span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead class="border-b border-slate-100">
                        <tr>
                            <th class="pb-4 text-[8px] font-black uppercase tracking-[0.2em] text-slate-400">Title</th>
                            <th class="pb-4 text-[8px] font-black uppercase tracking-[0.2em] text-slate-400">Date</th>
                            <th class="pb-4 text-[8px] font-black uppercase tracking-[0.2em] text-slate-400">Location</th>
                            <th class="pb-4 text-[8px] font-black uppercase tracking-[0.2em] text-slate-400 text-right">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        <?php if (empty($events)): ?>
                            <tr>
                                <td colspan="4" class="py-8 text-center text-[10px] font-bold uppercase tracking-widest text-slate-400">
                                    No event registrations found.
                                </td>
                            </tr>
                        <?php endif; ?>

                        <?php foreach ($events as $ev): ?>
                            <?php
                            $d = $ev['start_date'] ? date('d M Y', strtotime((string)$ev['start_date'])) : '—';
                            $st = $ev['status'] ?? 'registered';
                            $badge = $st === 'attended'
                                ? 'bg-green-50 text-green-700 border-green-100'
                                : 'bg-slate-50 text-slate-600 border-slate-100';
                            ?>
                            <tr class="hover:bg-slate-50">
                                <td class="py-5 text-[11px] font-bold text-slate-900"><?= e($ev['title']) ?></td>
                                <td class="py-5 text-[10px] font-bold text-slate-500 uppercase tracking-widest"><?= e($d) ?></td>
                                <td class="py-5 text-[10px] font-bold text-slate-500 uppercase tracking-widest"><?= e($ev['location'] ?? '—') ?></td>
                                <td class="py-5 text-right">
                                    <span class="px-3 py-1 text-[8px] font-black uppercase tracking-widest rounded border <?= $badge ?>">
                                        <?= e((string)$st) ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

        </div>

    </div>
</div>

<?php require_once __DIR__ . '/partials/footer.php'; ?>
