<?php
declare(strict_types=1);

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

/* =========================================================
   career.php — ARCHITECTURAL CAREER PORTAL (DYNAMIC)
   ✅ Loads jobs from member_jobs table
   ✅ Apply modal submits to job_applications table
   ✅ Resume upload (pdf/doc/docx) -> public/uploads/resumes
   ✅ CSRF protected
   ✅ Open access (no login required to apply)
   ========================================================= */

require_once __DIR__ . '/lib/helpers.php';
require_once __DIR__ . '/config/db.php';

start_session();

$pageTitle = 'Career Portal | Erode Architect Association';

/* -----------------------------
   Helpers
------------------------------ */
function table_exists(string $table): bool {
    $stmt = db()->prepare(
        'SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t'
    );
    $stmt->execute(['t' => $table]);
    return (int)$stmt->fetchColumn() > 0;
}

function ensure_dir(string $absDir): void {
    if (!is_dir($absDir)) mkdir($absDir, 0755, true);
    if (!is_writable($absDir)) throw new RuntimeException('Upload directory is not writable: ' . $absDir);
}

function upload_resume(array $file, string $absDir, string $webDir): string {
    if (empty($file['name'])) return '';

    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Resume upload failed (code ' . ($file['error'] ?? 'unknown') . ').');
    }

    if (!is_uploaded_file($file['tmp_name'])) {
        throw new RuntimeException('Invalid uploaded file.');
    }

    // ✅ allow only PDF / DOC / DOCX
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);

    $allowed = [
        'application/pdf' => 'pdf',
        'application/msword' => 'doc',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
    ];

    if (!isset($allowed[$mime])) {
        throw new RuntimeException('Only PDF/DOC/DOCX resumes allowed.');
    }

    ensure_dir($absDir);

    $filename = 'resume_' . date('Ymd_His') . '_' . bin2hex(random_bytes(6)) . '.' . $allowed[$mime];
    $targetAbs = rtrim($absDir, '/') . '/' . $filename;

    if (!move_uploaded_file($file['tmp_name'], $targetAbs)) {
        throw new RuntimeException('Failed to store resume.');
    }

    return rtrim($webDir, '/') . '/' . $filename;
}

/* -----------------------------
   Ensure required tables exist
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
            portfolio_url VARCHAR(255) NULL,
            summary TEXT NULL,
            resume_path VARCHAR(255) NULL,
            status ENUM('new','shortlisted','rejected') NOT NULL DEFAULT 'new',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX (job_id),
            INDEX (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
} else {
    // Add missing columns safely (in case old table exists)
    $cols = db()->query("SHOW COLUMNS FROM job_applications")->fetchAll(PDO::FETCH_COLUMN, 0);
    if (!in_array('portfolio_url', $cols, true)) db()->exec("ALTER TABLE job_applications ADD portfolio_url VARCHAR(255) NULL");
    if (!in_array('summary', $cols, true)) db()->exec("ALTER TABLE job_applications ADD summary TEXT NULL");
}

/* -----------------------------
   Apply submit handler
------------------------------ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'apply_job') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        flash_set('apply_error', 'Invalid session token. Please refresh and try again.');
        redirect('career.php');
    }

    $jobId = (int)($_POST['job_id'] ?? 0);
    $name = trim($_POST['applicant_name'] ?? '');
    $email = trim($_POST['applicant_email'] ?? '');
    $phone = trim($_POST['applicant_phone'] ?? '');
    $portfolio = trim($_POST['portfolio_url'] ?? '');
    $summary = trim($_POST['summary'] ?? '');

    if ($jobId <= 0 || $name === '' || $email === '') {
        flash_set('apply_error', 'Please enter Name + Email.');
        redirect('career.php');
    }

    // Validate job exists & open
    $jobChk = db()->prepare("SELECT id, status FROM member_jobs WHERE id = ? LIMIT 1");
    $jobChk->execute([$jobId]);
    $jobRow = $jobChk->fetch();
    if (!$jobRow || ($jobRow['status'] ?? '') !== 'open') {
        flash_set('apply_error', 'This job is not available.');
        redirect('career.php');
    }

    try {
        // Resume upload
        $resumePath = null;
        if (!empty($_FILES['resume']['name'])) {
            $abs = __DIR__ . '/public/uploads/resumes';
            $web = 'public/uploads/resumes';
            $resumePath = upload_resume($_FILES['resume'], $abs, $web);
        }

        $ins = db()->prepare("
            INSERT INTO job_applications (job_id, applicant_name, applicant_email, applicant_phone, portfolio_url, summary, resume_path, status)
            VALUES (:job_id, :name, :email, :phone, :portfolio, :summary, :resume, 'new')
        ");
        $ins->execute([
            'job_id' => $jobId,
            'name' => $name,
            'email' => $email,
            'phone' => $phone !== '' ? $phone : null,
            'portfolio' => $portfolio !== '' ? $portfolio : null,
            'summary' => $summary !== '' ? $summary : null,
            'resume' => $resumePath,
        ]);

        flash_set('apply_status', 'Application submitted successfully.');
        redirect('career.php');
    } catch (Throwable $e) {
        flash_set('apply_error', 'Failed to submit: ' . $e->getMessage());
        redirect('career.php');
    }
}

/* -----------------------------
   Load jobs for listing (dynamic)
------------------------------ */
$jobsStmt = db()->query("
    SELECT id, title, job_type, location, description, deadline, created_at, status
    FROM member_jobs
    WHERE status = 'open'
    ORDER BY created_at DESC
");
$jobs = $jobsStmt->fetchAll();

/* -----------------------------
   Flash
------------------------------ */
$applyStatus = flash_get('apply_status');
$applyError  = flash_get('apply_error');

require_once __DIR__ . "/partials/header.php";
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" referrerpolicy="no-referrer" />

<style>
    :root { --eaa-smoke:#475569; --eaa-border:#e2e8f0; --eaa-radius:5px; --eaa-accent:#1e293b; }
    body { background:#f8fafc; color:#1e293b; font-family:'Montserrat',sans-serif; }
    .font-druk { font-family:'Montserrat',sans-serif!important; font-weight:900; text-transform:uppercase; letter-spacing:-0.05em; line-height:0.85; }
    .eaa-radius { border-radius:var(--eaa-radius)!important; }
    .blueprint-grid {
        background-image: linear-gradient(rgba(71,85,105,0.05) 1px, transparent 1px),
                          linear-gradient(90deg, rgba(71,85,105,0.05) 1px, transparent 1px);
        background-size: 40px 40px;
    }
    .job-card {
        background:#fff; border:1px solid var(--eaa-border); padding:35px;
        transition: all .4s cubic-bezier(.16,1,.3,1);
        display:flex; flex-direction:column; position:relative;
    }
    .job-card:hover { border-color:var(--eaa-smoke); transform:translateY(-5px); box-shadow:0 15px 40px rgba(71,85,105,0.1); }
    .tech-label { font-size:8px; font-weight:900; text-transform:uppercase; letter-spacing:.2em; color:#94a3b8; display:block; margin-bottom:6px; }
    .job-tag { font-size:7px; font-weight:900; text-transform:uppercase; letter-spacing:.1em; padding:4px 10px; background:#f1f5f9; color:#64748b; border-radius:2px; }
    .reveal { opacity:0; transform:translateY(20px); transition: all .8s cubic-bezier(.2,.8,.2,1); }
    .reveal.active { opacity:1; transform:translateY(0); }

    #applyModal {
        display:none; position:fixed; inset:0; z-index:1000;
        background:rgba(15,23,42,.8); backdrop-filter: blur(8px);
        align-items:center; justify-content:center; padding:20px;
    }
    .modal-content { background:#fff; width:100%; max-width:640px; padding:50px; border-radius:var(--eaa-radius); position:relative; }
    .tech-input {
        width:100%; background:#f8fafc; border:1px solid var(--eaa-border);
        border-radius:var(--eaa-radius); padding:14px 18px;
        font-size:11px; font-weight:600; text-transform:uppercase;
        letter-spacing:.05em; color:var(--eaa-accent); outline:none;
        transition: all .3s ease;
    }
    .tech-input:focus { border-color:var(--eaa-smoke); background:#fff; }
    .notice { padding:12px 14px; border-radius:var(--eaa-radius); font-size:10px; font-weight:800; text-transform:uppercase; letter-spacing:.12em; }
    .notice-ok { background:#ecfdf5; border:1px solid #a7f3d0; color:#065f46; }
    .notice-bad { background:#fef2f2; border:1px solid #fecaca; color:#991b1b; }
</style>

<?php if ($applyStatus): ?>
<div class="container mx-auto px-6 mt-6 max-w-7xl">
    <div class="notice notice-ok"><?= e($applyStatus) ?></div>
</div>
<?php endif; ?>

<?php if ($applyError): ?>
<div class="container mx-auto px-6 mt-6 max-w-7xl">
    <div class="notice notice-bad"><?= e($applyError) ?></div>
</div>
<?php endif; ?>

<section class="pt-44 pb-20 relative overflow-hidden bg-white border-b border-slate-100">
    <div class="absolute inset-0 blueprint-grid opacity-20 pointer-events-none"></div>
    <div class="container mx-auto px-6 relative z-10">
        <div class="max-w-4xl">
            <span class="text-[8px] font-black uppercase tracking-[0.5em] text-slate-400 block border-l-2 border-slate-400 pl-4 mb-6">Opportunities / <?= date('Y') ?></span>
            <h1 class="font-druk text-5xl md:text-7xl lg:text-8xl text-slate-900 leading-none mb-10">
                Career <br><span class="text-slate-400 italic">Portal</span>
            </h1>
            <p class="max-w-2xl text-slate-500 text-xs md:text-sm font-bold uppercase tracking-widest leading-loose text-justify">
                Discover openings from EAA member firms. Apply directly — resumes are routed to the firm hiring board.
            </p>
        </div>
    </div>
</section>

<main class="py-24 bg-white relative overflow-hidden">
    <div class="container mx-auto px-6 relative z-10">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <?php if (empty($jobs)): ?>
                <div class="p-10 border border-slate-100 bg-slate-50 eaa-radius">
                    <span class="tech-label">No openings</span>
                    <p class="text-sm font-bold text-slate-600">No jobs posted yet.</p>
                </div>
            <?php endif; ?>

            <?php foreach ($jobs as $index => $j): ?>
                <?php
                    $postedAgo = date('d M Y', strtotime((string)$j['created_at']));
                    $deadlineText = !empty($j['deadline']) ? date('d M Y', strtotime((string)$j['deadline'])) : '—';
                ?>
                <div class="job-card eaa-radius reveal" style="transition-delay: <?= ($index % 2) * 100 ?>ms;">
                    <div class="flex flex-col md:flex-row md:items-start justify-between gap-6 mb-8">
                        <div>
                            <span class="tech-label">Ref: JOB-<?= e((string)$j['id']) ?></span>
                            <h3 class="font-bold text-xl text-slate-900 uppercase tracking-tight mb-2"><?= e($j['title']) ?></h3>
                            <div class="flex flex-wrap gap-3 mt-4">
                                <span class="job-tag"><?= e($j['job_type']) ?></span>
                                <?php if (!empty($j['location'])): ?>
                                    <span class="job-tag"><?= e($j['location']) ?></span>
                                <?php endif; ?>
                                <span class="job-tag">Deadline: <?= e($deadlineText) ?></span>
                            </div>
                        </div>
                        <div class="text-left md:text-right">
                            <span class="tech-label">Posted</span>
                            <span class="text-[11px] font-black text-slate-900 uppercase italic"><?= e($postedAgo) ?></span>
                        </div>
                    </div>

                    <p class="text-xs text-slate-500 font-medium leading-relaxed mb-10 text-justify flex-1">
                        <?= e(mb_strimwidth(strip_tags((string)$j['description']), 0, 230, '...')) ?>
                    </p>

                    <div class="pt-8 border-t border-slate-50 flex items-center justify-between">
                        <span class="text-[9px] font-black text-slate-900 uppercase tracking-widest">Status: OPEN</span>
                        <button
                            class="px-8 py-4 bg-slate-900 text-white text-[9px] font-black uppercase tracking-widest eaa-radius hover:bg-slate-700 transition-all shadow-xl shadow-slate-200"
                            onclick="openApplyModal(<?= (int)$j['id'] ?>, <?= json_encode($j['title']) ?>)">
                            Apply Now
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</main>

<!-- APPLICATION MODAL -->
<div id="applyModal">
    <div class="modal-content shadow-2xl">
        <button onclick="closeApplyModal()" class="absolute top-8 right-8 text-slate-300 hover:text-slate-900 transition-colors">
            <i class="fa-solid fa-xmark"></i>
        </button>

        <span class="tech-label">Talent Submission</span>
        <h2 id="modalJobTitle" class="font-druk text-3xl text-slate-900 mt-4 mb-2 uppercase">Position</h2>
        <p class="text-[10px] font-black text-slate-400 uppercase tracking-[0.2em] mb-10">Your application will be routed to the firm hiring board</p>

        <form class="space-y-6" method="post" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="apply_job">
            <input type="hidden" name="job_id" id="modalJobId" value="">

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="tech-label">Full Name *</label>
                    <input name="applicant_name" type="text" class="tech-input" required>
                </div>
                <div>
                    <label class="tech-label">Email Address *</label>
                    <input name="applicant_email" type="email" class="tech-input" required>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="tech-label">Mobile Number</label>
                    <input name="applicant_phone" type="text" class="tech-input" placeholder="+91">
                </div>
                <div>
                    <label class="tech-label">Portfolio / CV Link</label>
                    <input name="portfolio_url" type="url" class="tech-input" placeholder="https://...">
                </div>
            </div>

            <div>
                <label class="tech-label">Resume Upload (PDF/DOC/DOCX)</label>
                <input name="resume" type="file" class="tech-input pt-2" accept=".pdf,.doc,.docx,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document">
            </div>

            <div>
                <label class="tech-label">Professional Summary (Optional)</label>
                <textarea name="summary" class="tech-input min-h-[120px] normal-case tracking-normal text-sm"></textarea>
            </div>

            <div class="pt-4">
                <button type="submit" class="w-full py-5 bg-slate-900 text-white text-[10px] font-black uppercase tracking-widest eaa-radius shadow-2xl hover:bg-slate-700 transition-all">
                    Submit Application
                </button>
                <p class="text-[8px] font-bold text-slate-400 uppercase tracking-widest text-center mt-6">
                    Resumes are stored securely in EAA server uploads.
                </p>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . "/partials/footer.php"; ?>

<script>
    function openApplyModal(jobId, jobTitle) {
        document.getElementById('modalJobId').value = jobId;
        document.getElementById('modalJobTitle').innerText = jobTitle;
        document.getElementById('applyModal').style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }

    function closeApplyModal() {
        document.getElementById('applyModal').style.display = 'none';
        document.body.style.overflow = 'auto';
    }

    document.addEventListener('DOMContentLoaded', () => {
        const revealElements = document.querySelectorAll('.reveal');
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(e => { if (e.isIntersecting) e.target.classList.add('active'); });
        }, { threshold: 0.1 });
        revealElements.forEach(el => observer.observe(el));
    });

    window.onclick = function(event) {
        if (event.target === document.getElementById('applyModal')) closeApplyModal();
    }
</script>
