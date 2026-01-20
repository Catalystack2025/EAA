<?php

declare(strict_types=1);

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

/* =========================================================
   job_applications.php — MEMBER JOB APPLICATIONS VIEW
   ✅ Only job owner can access
   ✅ List applications
   ✅ Actions: New / Shortlist / Reject
   ✅ CSRF protected
   ========================================================= */

require_once __DIR__ . '/lib/helpers.php';
require_once __DIR__ . '/config/db.php';

start_session();

$currentUserId = $_SESSION['user_id'] ?? null;
if ($currentUserId === null) {
    redirect('login.php');
}

$pageTitle = 'Job Applications | EAA';

/* -----------------------------
   Helper
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

/* -----------------------------
   Ensure required tables exist
   (in case file is used before accountpage.php runs)
------------------------------ */
if (!table_exists('member_jobs')) {
    db()->exec("
        CREATE TABLE member_jobs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            title VARCHAR(255) NOT NULL,
            job_type VARCHAR(50) NOT NULL DEFAULT 'Full-Time',
            location VARCHAR(120) NULL,
            description MEDIUMTEXT NOT NULL,
            deadline DATE NULL,
            status ENUM('open','closed') NOT NULL DEFAULT 'open',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX (user_id),
            INDEX (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
}

if (!table_exists('job_applications')) {
    db()->exec("
        CREATE TABLE job_applications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            job_id INT NOT NULL,
            applicant_name VARCHAR(255) NOT NULL,
            applicant_email VARCHAR(255) NOT NULL,
            applicant_phone VARCHAR(30) NULL,
            resume_path VARCHAR(255) NULL,
            status ENUM('new','shortlisted','rejected') NOT NULL DEFAULT 'new',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX (job_id),
            INDEX (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
}

/* -----------------------------
   Inputs
------------------------------ */
$jobId = (int)($_GET['job_id'] ?? 0);
if ($jobId <= 0) {
    flash_set('error', 'Invalid job reference.');
    redirect('accountpage.php#careers');
}

/* -----------------------------
   Load job and verify ownership
------------------------------ */
$jobStmt = db()->prepare('SELECT id, user_id, title, job_type, location, deadline, status, created_at FROM member_jobs WHERE id = ? LIMIT 1');
$jobStmt->execute([$jobId]);
$job = $jobStmt->fetch();

if (!$job) {
    flash_set('error', 'Job not found.');
    redirect('accountpage.php#careers');
}

if ((int)$job['user_id'] !== (int)$currentUserId) {
    flash_set('error', 'You do not have permission to view this job.');
    redirect('accountpage.php#careers');
}

/* -----------------------------
   Handle status update for application
------------------------------ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        flash_set('error', 'Invalid session token. Please try again.');
        redirect('job_applications.php?job_id=' . $jobId);
    }

    $action = $_POST['action'] ?? '';
    $applicationId = (int)($_POST['application_id'] ?? 0);

    $map = [
        'mark_new' => 'new',
        'shortlist' => 'shortlisted',
        'reject' => 'rejected',
    ];

    if ($applicationId > 0 && isset($map[$action])) {
        // Make sure application belongs to this job
        $ownStmt = db()->prepare('SELECT id FROM job_applications WHERE id = ? AND job_id = ? LIMIT 1');
        $ownStmt->execute([$applicationId, $jobId]);
        $ok = $ownStmt->fetchColumn();

        if ($ok) {
            $upd = db()->prepare('UPDATE job_applications SET status = :status WHERE id = :id AND job_id = :job_id');
            $upd->execute([
                'status' => $map[$action],
                'id' => $applicationId,
                'job_id' => $jobId,
            ]);
            flash_set('status', 'Application updated.');
        } else {
            flash_set('error', 'Invalid application reference.');
        }
    } else {
        flash_set('error', 'Invalid action.');
    }

    redirect('job_applications.php?job_id=' . $jobId);
}

/* -----------------------------
   Load applications
------------------------------ */
$appStmt = db()->prepare("
    SELECT id, applicant_name, applicant_email, applicant_phone, resume_path, status, created_at
    FROM job_applications
    WHERE job_id = ?
    ORDER BY created_at DESC
");
$appStmt->execute([$jobId]);
$applications = $appStmt->fetchAll();

/* -----------------------------
   Flash
------------------------------ */
$statusMsg = flash_get('status');
$errorMsg  = flash_get('error');

require_once __DIR__ . '/partials/header.php';
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" referrerpolicy="no-referrer" />

<style>
    :root {
        --eaa-border: #e2e8f0;
        --eaa-radius: 5px;
        --eaa-accent: #0f172a;
    }
    body { background:#f8fafc; font-family:'Montserrat',sans-serif; }
    .eaa-radius { border-radius: var(--eaa-radius) !important; }
    .tech-label {
        font-size: 8px; font-weight: 900; text-transform: uppercase; letter-spacing: 0.2em; color: #94a3b8;
        display: block; margin-bottom: 8px;
    }
    .console-card { background:#fff; border:1px solid var(--eaa-border); border-radius:var(--eaa-radius); padding:28px; }
    .notice { padding: 12px 14px; border-radius: var(--eaa-radius); font-size: 10px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.12em; }
    .notice-ok { background: #ecfdf5; border: 1px solid #a7f3d0; color: #065f46; }
    .notice-bad { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; }

    .badge {
        font-size: 7px; font-weight: 900; text-transform: uppercase; letter-spacing: 0.12em;
        padding: 4px 10px; border-radius: 2px; display:inline-flex; align-items:center; gap:6px;
    }
    .b-new { background:#fffbeb; color:#92400e; border:1px solid #fde68a; }
    .b-short { background:#ecfdf5; color:#065f46; border:1px solid #a7f3d0; }
    .b-rej { background:#fef2f2; color:#991b1b; border:1px solid #fecaca; }

    .action-btn {
        width: 38px; height: 38px; display:flex; align-items:center; justify-content:center;
        border-radius: 4px; border:1px solid #e2e8f0; background:#fff; color:#64748b;
        transition: all .2s ease;
    }
    .action-btn:hover { background: var(--eaa-accent); color:#fff; border-color: var(--eaa-accent); transform: translateY(-1px); }
</style>

<div class="container mx-auto px-6 max-w-6xl pt-40 pb-14">
    <div class="mb-8">
        <a href="accountpage.php#careers" class="text-[9px] font-black uppercase tracking-widest text-slate-500 hover:text-slate-900">
            <i class="fa-solid fa-arrow-left mr-2"></i>Back to Careers
        </a>
    </div>

    <?php if ($statusMsg): ?>
        <div class="mb-6 notice notice-ok"><?= e($statusMsg) ?></div>
    <?php endif; ?>
    <?php if ($errorMsg): ?>
        <div class="mb-6 notice notice-bad"><?= e($errorMsg) ?></div>
    <?php endif; ?>

    <div class="console-card mb-8">
        <span class="tech-label">Job Details</span>
        <h1 class="text-2xl font-black uppercase tracking-tight text-slate-900 mb-3"><?= e($job['title']) ?></h1>

        <div class="flex flex-wrap gap-3">
            <span class="text-[8px] font-black uppercase tracking-widest text-slate-400"><?= e($job['job_type']) ?></span>
            <?php if (!empty($job['location'])): ?>
                <span class="text-[8px] font-black uppercase tracking-widest text-slate-400">• <?= e($job['location']) ?></span>
            <?php endif; ?>
            <?php if (!empty($job['deadline'])): ?>
                <span class="text-[8px] font-black uppercase tracking-widest text-slate-400">• Deadline: <?= e(date('d M Y', strtotime((string)$job['deadline']))) ?></span>
            <?php endif; ?>
            <span class="text-[8px] font-black uppercase tracking-widest text-slate-400">• Status: <?= e($job['status']) ?></span>
        </div>
    </div>

    <div class="console-card">
        <div class="flex items-end justify-between gap-6 mb-8">
            <div>
                <span class="tech-label">Applications</span>
                <h2 class="text-lg font-black uppercase tracking-tight text-slate-900"><?= e((string)count($applications)) ?> total</h2>
            </div>
        </div>

        <?php if (empty($applications)): ?>
            <div class="p-6 border border-slate-100 bg-slate-50 eaa-radius">
                <p class="text-[10px] font-bold uppercase tracking-widest text-slate-500">No applications yet.</p>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead class="border-b border-slate-100">
                        <tr>
                            <th class="pb-4 tech-label">Applicant</th>
                            <th class="pb-4 tech-label">Contact</th>
                            <th class="pb-4 tech-label">Applied</th>
                            <th class="pb-4 tech-label">Status</th>
                            <th class="pb-4 tech-label text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        <?php foreach ($applications as $a): ?>
                            <?php
                            $badgeClass = 'b-new';
                            if ($a['status'] === 'shortlisted') $badgeClass = 'b-short';
                            if ($a['status'] === 'rejected') $badgeClass = 'b-rej';
                            ?>
                            <tr class="hover:bg-slate-50 transition-colors">
                                <td class="py-5">
                                    <div class="flex flex-col">
                                        <span class="text-[11px] font-black uppercase tracking-tight text-slate-900"><?= e($a['applicant_name']) ?></span>
                                        <span class="text-[8px] font-bold uppercase tracking-widest text-slate-400">APP-<?= e((string)$a['id']) ?></span>
                                    </div>
                                </td>
                                <td class="py-5">
                                    <div class="flex flex-col">
                                        <span class="text-[9px] font-bold uppercase tracking-widest text-slate-700"><?= e($a['applicant_email']) ?></span>
                                        <?php if (!empty($a['applicant_phone'])): ?>
                                            <span class="text-[8px] font-bold uppercase tracking-widest text-slate-400"><?= e($a['applicant_phone']) ?></span>
                                        <?php endif; ?>
                                        <?php if (!empty($a['resume_path'])): ?>
                                            <a class="text-[8px] font-black uppercase tracking-widest text-slate-500 hover:text-slate-900 mt-2 inline-flex items-center gap-2"
                                               href="<?= e(asset((string)$a['resume_path'])) ?>" target="_blank" rel="noopener">
                                                <i class="fa-solid fa-file-arrow-down"></i> Resume
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="py-5">
                                    <span class="text-[9px] font-bold uppercase tracking-widest text-slate-500">
                                        <?= e(date('d M Y', strtotime((string)$a['created_at']))) ?>
                                    </span>
                                </td>
                                <td class="py-5">
                                    <span class="badge <?= $badgeClass ?>"><?= e($a['status']) ?></span>
                                </td>
                                <td class="py-5">
                                    <div class="flex justify-end gap-2">
                                        <form method="post">
                                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                            <input type="hidden" name="application_id" value="<?= e((string)$a['id']) ?>">
                                            <input type="hidden" name="action" value="mark_new">
                                            <button class="action-btn" title="Mark as New">
                                                <i class="fa-solid fa-rotate-left"></i>
                                            </button>
                                        </form>

                                        <form method="post">
                                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                            <input type="hidden" name="application_id" value="<?= e((string)$a['id']) ?>">
                                            <input type="hidden" name="action" value="shortlist">
                                            <button class="action-btn" title="Shortlist">
                                                <i class="fa-solid fa-check"></i>
                                            </button>
                                        </form>

                                        <form method="post">
                                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                            <input type="hidden" name="application_id" value="<?= e((string)$a['id']) ?>">
                                            <input type="hidden" name="action" value="reject">
                                            <button class="action-btn" title="Reject">
                                                <i class="fa-solid fa-xmark"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

    </div>
</div>

<?php require_once __DIR__ . '/partials/footer.php'; ?>
