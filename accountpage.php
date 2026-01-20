<?php

declare(strict_types=1);

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

/* =========================================================
   accountpage.php — MEMBER PROFESSIONAL CONSOLE (FULL)
   ✅ Correct redirects for /erodearchassoc/
   ✅ Council Profile submit + Admin review fields
   ✅ Blog system (draft/pending)
   ✅ Rich editor: Bold / Italic / Align + Inline Image insert (base64)
   ✅ Featured image upload (auto mkdir + writable checks)
   ✅ Careers backend: Post new position + list jobs
   ✅ Font Awesome CDN (reliable)
   ========================================================= */

require_once __DIR__ . '/lib/helpers.php';
require_once __DIR__ . '/config/db.php';

start_session();

$currentUserId = $_SESSION['user_id'] ?? null;
if ($currentUserId === null) {
    redirect('login.php');
}

$pageTitle = 'Member Console | Erode Architect Association';

/* -----------------------------
   Helpers
------------------------------ */

function ensure_upload_dir(string $dir): void
{
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    if (!is_writable($dir)) {
        throw new RuntimeException('Upload directory is not writable: ' . $dir);
    }
}

function upload_image(array $file, string $prefix, string $uploadDirAbs, string $uploadDirWeb): string
{
    if (empty($file['name'])) {
        return '';
    }

    if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Upload failed (code ' . ($file['error'] ?? 'unknown') . ').');
    }

    if (!is_uploaded_file($file['tmp_name'])) {
        throw new RuntimeException('Upload tmp file is not valid.');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($file['tmp_name']);

    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];
    if (!isset($allowed[$mimeType])) {
        throw new RuntimeException('Only JPG, PNG, WebP allowed.');
    }

    ensure_upload_dir($uploadDirAbs);

    $filename = sprintf(
        '%s_%d_%s.%s',
        $prefix,
        (int)($_SESSION['user_id'] ?? 0),
        bin2hex(random_bytes(6)),
        $allowed[$mimeType]
    );

    $targetAbs = rtrim($uploadDirAbs, '/') . '/' . $filename;

    if (!move_uploaded_file($file['tmp_name'], $targetAbs)) {
        throw new RuntimeException('Failed to store uploaded image.');
    }

    // store WEB path in DB
    return rtrim($uploadDirWeb, '/') . '/' . $filename;
}

function table_exists(string $table): bool
{
    $stmt = db()->prepare(
        'SELECT COUNT(*)
         FROM INFORMATION_SCHEMA.TABLES
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = :table'
    );
    $stmt->execute(['table' => $table]);
    return (int) $stmt->fetchColumn() > 0;
}

/* -----------------------------
   Ensure DB tables (safe create)
------------------------------ */

if (!table_exists('team_members')) {
    db()->exec("
        CREATE TABLE team_members (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL UNIQUE,
            photo_path VARCHAR(255) NULL,
            title VARCHAR(120) NOT NULL,
            category VARCHAR(120) NOT NULL,
            visible TINYINT(1) NOT NULL DEFAULT 0,
            approval_status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
            reviewed_at DATETIME NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
}

if (!table_exists('member_blogs')) {
    db()->exec("
        CREATE TABLE member_blogs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            title VARCHAR(255) NOT NULL,
            category VARCHAR(120) NOT NULL DEFAULT 'General',
            content_html MEDIUMTEXT NOT NULL,
            featured_image VARCHAR(255) NULL,
            status ENUM('draft','pending','published','rejected') NOT NULL DEFAULT 'pending',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX (user_id),
            INDEX (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
}

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
   Load user
------------------------------ */
$userStmt = db()->prepare('SELECT id, full_name, email, status FROM users WHERE id = ? LIMIT 1');
$userStmt->execute([$currentUserId]);
$user = $userStmt->fetch();

if (!$user) {
    redirect('logout.php');
}

/* -----------------------------
   Load council profile row
------------------------------ */
$profileStmt = db()->prepare('SELECT photo_path, title, category, visible, approval_status, reviewed_at FROM team_members WHERE user_id = ? LIMIT 1');
$profileStmt->execute([$currentUserId]);
$teamProfile = $profileStmt->fetch();

/* -----------------------------
   Lists
------------------------------ */
$blogsStmt = db()->prepare('SELECT id, title, category, status, created_at, updated_at FROM member_blogs WHERE user_id = ? ORDER BY updated_at DESC LIMIT 20');
$blogsStmt->execute([$currentUserId]);
$blogs = $blogsStmt->fetchAll();

$jobsStmt = db()->prepare('SELECT id, title, job_type, deadline, status, created_at FROM member_jobs WHERE user_id = ? ORDER BY created_at DESC LIMIT 50');
$jobsStmt->execute([$currentUserId]);
$jobs = $jobsStmt->fetchAll();

/* -----------------------------
   Form handlers
------------------------------ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        flash_set('error', 'Invalid session token. Please try again.');
        redirect('accountpage.php');
    }

    // 1) Council profile update
    if ($action === 'team_profile_update') {
        $title = trim($_POST['title'] ?? '');
        $category = trim($_POST['category'] ?? '');
        $visible = isset($_POST['visible']) ? 1 : 0;

        if ($title === '' || $category === '') {
            flash_set('team_profile_error', 'Please provide both your title and category.');
            redirect('accountpage.php');
        }

        try {
            $uploadDirAbs = __DIR__ . '/public/uploads';
            $uploadDirWeb = 'public/uploads';

            $uploadPath = $teamProfile['photo_path'] ?? null;
            if (!empty($_FILES['photo']['name'])) {
                $uploadPath = upload_image($_FILES['photo'], 'team', $uploadDirAbs, $uploadDirWeb);
            }

            $upsert = db()->prepare(
                'INSERT INTO team_members (user_id, photo_path, title, category, visible, approval_status, reviewed_at)
                 VALUES (:user_id, :photo_path, :title, :category, :visible, "pending", NULL)
                 ON DUPLICATE KEY UPDATE
                    photo_path = VALUES(photo_path),
                    title = VALUES(title),
                    category = VALUES(category),
                    visible = VALUES(visible),
                    approval_status = "pending",
                    reviewed_at = NULL'
            );

            $upsert->execute([
                'user_id' => $currentUserId,
                'photo_path' => $uploadPath,
                'title' => $title,
                'category' => $category,
                'visible' => $visible,
            ]);

            flash_set('team_profile_status', 'Your council profile has been updated and submitted for review.');
            redirect('accountpage.php');
        } catch (Throwable $e) {
            flash_set('team_profile_error', 'Failed to update council profile: ' . $e->getMessage());
            redirect('accountpage.php');
        }
    }

    // 2) Blog create (draft/pending)
    if ($action === 'blog_create') {
        $blogTitle = trim($_POST['blog_title'] ?? '');
        $blogCategory = trim($_POST['blog_category'] ?? 'General');
        $contentHtml = trim($_POST['content_html'] ?? '');
        $saveAsDraft = isset($_POST['save_draft']) && $_POST['save_draft'] === '1';

        if ($blogTitle === '' || $contentHtml === '') {
            flash_set('blog_error', 'Please add both Title and Content.');
            redirect('accountpage.php#journal');
        }

        try {
            $featuredImagePath = null;
            if (!empty($_FILES['featured_image']['name'])) {
                $uploadDirAbs = __DIR__ . '/public/uploads';
                $uploadDirWeb = 'public/uploads';
                $featuredImagePath = upload_image($_FILES['featured_image'], 'blog', $uploadDirAbs, $uploadDirWeb);
            }

            $status = $saveAsDraft ? 'draft' : 'pending';

            $ins = db()->prepare(
                'INSERT INTO member_blogs (user_id, title, category, content_html, featured_image, status)
                 VALUES (:user_id, :title, :category, :content_html, :featured_image, :status)'
            );
            $ins->execute([
                'user_id' => $currentUserId,
                'title' => $blogTitle,
                'category' => $blogCategory !== '' ? $blogCategory : 'General',
                'content_html' => $contentHtml,
                'featured_image' => $featuredImagePath,
                'status' => $status,
            ]);

            flash_set('blog_status', $saveAsDraft ? 'Draft saved.' : 'Submitted for review.');
            redirect('accountpage.php#journal');
        } catch (Throwable $e) {
            flash_set('blog_error', 'Failed to save blog: ' . $e->getMessage());
            redirect('accountpage.php#journal');
        }
    }

    // 3) Job create
    if ($action === 'job_create') {
        $title = trim($_POST['job_title'] ?? '');
        $jobType = trim($_POST['job_type'] ?? 'Full-Time');
        $location = trim($_POST['job_location'] ?? '');
        $deadline = trim($_POST['job_deadline'] ?? '');
        $description = trim($_POST['job_description'] ?? '');

        if ($title === '' || $description === '') {
            flash_set('job_error', 'Please add Job Title and Description.');
            redirect('accountpage.php#careers');
        }

        try {
            $ins = db()->prepare("
                INSERT INTO member_jobs (user_id, title, job_type, location, description, deadline, status)
                VALUES (:user_id, :title, :job_type, :location, :description, :deadline, 'open')
            ");
            $ins->execute([
                'user_id' => $currentUserId,
                'title' => $title,
                'job_type' => $jobType !== '' ? $jobType : 'Full-Time',
                'location' => $location !== '' ? $location : null,
                'description' => $description,
                'deadline' => $deadline !== '' ? $deadline : null,
            ]);

            flash_set('job_status', 'Job posted successfully.');
            redirect('accountpage.php#careers');
        } catch (Throwable $e) {
            flash_set('job_error', 'Failed to post job: ' . $e->getMessage());
            redirect('accountpage.php#careers');
        }
    }
}

/* -----------------------------
   Flash messages
------------------------------ */
$teamProfileStatus = flash_get('team_profile_status');
$teamProfileError  = flash_get('team_profile_error');
$blogStatus        = flash_get('blog_status');
$blogError         = flash_get('blog_error');
$jobStatus         = flash_get('job_status');
$jobError          = flash_get('job_error');
$globalError       = flash_get('error');

/* -----------------------------
   Member info (basic)
------------------------------ */
$member = [
    'name' => $user['full_name'] ?: 'Member',
    'id' => 'EAA-MEM-' . (string) $user['id'],
    'status' => ($user['status'] ?? 'active') === 'active' ? 'Active Member' : ucfirst((string) $user['status']),
    'expiry' => '—',
];

require_once __DIR__ . "/partials/header.php";
?>

<!-- Font Awesome (CDN reliable) -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" referrerpolicy="no-referrer" />

<style>
    :root {
        --eaa-smoke: #475569;
        --eaa-border: #e2e8f0;
        --eaa-radius: 5px;
        --eaa-accent: #1e293b;
    }

    body { background-color: #f8fafc; color: #1e293b; font-family: 'Montserrat', sans-serif; }
    .font-druk { font-family: 'Montserrat', sans-serif !important; font-weight: 900; text-transform: uppercase; letter-spacing: -0.05em; line-height: 0.85; }
    .eaa-radius { border-radius: var(--eaa-radius) !important; }

    .blueprint-grid {
        background-image: linear-gradient(rgba(71, 85, 105, 0.05) 1px, transparent 1px),
                          linear-gradient(90deg, rgba(71, 85, 105, 0.05) 1px, transparent 1px);
        background-size: 40px 40px;
    }

    .tech-input {
        width: 100%;
        background: #ffffff;
        border: 1px solid var(--eaa-border);
        border-radius: var(--eaa-radius);
        padding: 12px 16px;
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: var(--eaa-accent);
        outline: none;
        transition: all 0.3s ease;
    }
    .tech-input:focus { border-color: var(--eaa-smoke); box-shadow: 0 0 0 1px var(--eaa-smoke); }

    .tech-label {
        font-size: 8px; font-weight: 900; text-transform: uppercase; letter-spacing: 0.2em; color: #94a3b8;
        display: block; margin-bottom: 8px;
    }

    .editor-toolbar {
        display: flex; gap: 2px; background: #f1f5f9; padding: 4px;
        border: 1px solid var(--eaa-border); border-bottom: none;
        border-top-left-radius: var(--eaa-radius); border-top-right-radius: var(--eaa-radius);
        flex-wrap: wrap;
    }
    .toolbar-btn {
        width: 34px; height: 34px; display: flex; align-items: center; justify-content: center;
        background: white; color: var(--eaa-smoke); font-size: 12px; border-radius: 2px;
        transition: all 0.2s ease; border: 1px solid rgba(226,232,240,0.7);
    }
    .toolbar-btn:hover { background: var(--eaa-accent); color: white; border-color: var(--eaa-accent); }

    .editor-surface {
        border: 1px solid var(--eaa-border);
        border-top: none;
        border-bottom-left-radius: var(--eaa-radius);
        border-bottom-right-radius: var(--eaa-radius);
        background: #fff;
        padding: 14px 16px;
        min-height: 320px;
        font-size: 14px;
        text-transform: none;
        letter-spacing: normal;
        outline: none;
    }
    .editor-surface:focus { box-shadow: 0 0 0 1px var(--eaa-smoke); }

    .console-tab {
        font-size: 9px; font-weight: 900; text-transform: uppercase; letter-spacing: 0.15em;
        color: #94a3b8; padding: 15px 25px; border-bottom: 2px solid transparent;
        transition: all 0.3s ease; cursor: pointer;
    }
    .console-tab.active { color: var(--eaa-accent); border-bottom-color: var(--eaa-accent); }

    .console-card { background: #ffffff; border: 1px solid var(--eaa-border); border-radius: var(--eaa-radius); padding: 30px; height: 100%; }

    .status-badge {
        font-size: 7px; font-weight: 900; text-transform: uppercase; letter-spacing: 0.1em;
        padding: 4px 10px; border-radius: 2px;
    }
    .badge-pending { background: #fef3c7; color: #92400e; }
    .badge-published { background: #dcfce7; color: #166534; }
    .badge-missed { background: #fee2e2; color: #b91c1c; }

    .reveal { opacity: 0; transform: translateY(20px); transition: all 0.8s cubic-bezier(0.2, 0.8, 0.2, 1); }
    .reveal.active { opacity: 1; transform: translateY(0); }
    .tab-content { display: none; }
    .tab-content.active { display: block; }

    .notice { padding: 12px 14px; border-radius: var(--eaa-radius); font-size: 10px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.12em; }
    .notice-ok { background: #ecfdf5; border: 1px solid #a7f3d0; color: #065f46; }
    .notice-warn { background: #fffbeb; border: 1px solid #fde68a; color: #92400e; }
    .notice-bad { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; }
</style>

<?php if ($globalError): ?>
<div class="container mx-auto px-6 mt-6">
    <div class="notice notice-bad"><?= e($globalError) ?></div>
</div>
<?php endif; ?>

<!-- MEMBER TOP HEADER -->
<section class="pt-44 pb-12 bg-white relative overflow-hidden border-b border-slate-100">
    <div class="absolute inset-0 blueprint-grid opacity-20 pointer-events-none"></div>
    <div class="container mx-auto px-6 relative z-10">
        <div class="flex flex-col lg:flex-row lg:items-end justify-between gap-10">
            <div class="reveal">
                <span class="inline-flex items-center px-3 py-1 bg-slate-100 text-[8px] font-black uppercase tracking-[0.3em] text-slate-500 eaa-radius mb-6">
                    <?= e($member['status']) ?>
                </span>
                <h1 class="font-druk text-5xl md:text-7xl lg:text-8xl text-slate-900 leading-none">
                    <?= e($member['name']) ?>
                </h1>
            </div>
            <div class="text-left lg:text-right reveal" style="transition-delay: 100ms;">
                <span class="tech-label">Membership Record</span>
                <span class="font-druk text-2xl lg:text-3xl text-slate-400"><?= e($member['id']) ?></span>
            </div>
        </div>
    </div>
</section>

<!-- CONSOLE NAVIGATION -->
<section class="bg-white border-b border-slate-100 sticky top-[80px] z-40">
    <div class="container mx-auto px-6">
        <div class="flex items-center gap-2 overflow-x-auto no-scrollbar">
            <div class="console-tab active" data-tab="membership">Overview</div>
            <div class="console-tab" data-tab="journal">Journal Submissions</div>
            <div class="console-tab" data-tab="careers">Firm Careers</div>
        </div>
    </div>
</section>

<main class="py-16">
    <div class="container mx-auto px-6 max-w-7xl">

        <!-- TAB: OVERVIEW -->
        <div id="membership" class="tab-content active reveal">
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

                <div class="lg:col-span-1">
                    <div class="console-card">
                        <h3 class="font-druk text-xl mb-8">Tenure / Foundation</h3>
                        <div class="space-y-6">
                            <div class="flex justify-between border-b border-slate-50 pb-4">
                                <span class="tech-label">Valid Until</span>
                                <span class="text-[11px] font-bold text-slate-900"><?= e($member['expiry']) ?></span>
                            </div>
                            <button class="w-full py-4 border border-slate-200 text-[9px] font-black uppercase tracking-widest eaa-radius hover:bg-slate-900 hover:text-white transition-all">
                                Extend Membership
                            </button>
                        </div>
                    </div>
                </div>

                <div class="lg:col-span-2">
                    <div class="console-card">
                        <div class="flex justify-between items-center mb-8">
                            <h3 class="font-druk text-xl">Event Agenda</h3>
                            <a href="calendar.php" class="text-[8px] font-black uppercase tracking-widest border-b border-slate-900 pb-1">Full Calendar</a>
                        </div>

                        <div class="space-y-4">
                            <?php for ($i = 0; $i < 2; $i++): ?>
                                <div class="flex items-center gap-6 p-4 border border-slate-100 eaa-radius hover:border-slate-300 transition-all">
                                    <div class="w-16 h-16 bg-slate-50 eaa-radius flex flex-col items-center justify-center border border-slate-100">
                                        <span class="text-lg font-black leading-none"><?= $i === 0 ? '15' : '05' ?></span>
                                        <span class="text-[7px] font-bold text-slate-400"><?= $i === 0 ? 'JAN' : 'FEB' ?></span>
                                    </div>
                                    <div class="flex-1">
                                        <span class="tech-label">Summit / Workshop</span>
                                        <h4 class="text-[11px] font-bold text-slate-900 uppercase tracking-tight">
                                            <?= $i === 0 ? 'Urban Planning Biennale 2026' : 'BIM Architecture Workshop' ?>
                                        </h4>
                                    </div>
                                    <span class="status-badge badge-published">Registered</span>
                                </div>
                            <?php endfor; ?>
                        </div>
                    </div>
                </div>

                <div class="lg:col-span-3 mt-4">
                    <div class="console-card">
                        <h3 class="font-druk text-xl mb-8 uppercase">History & <span class="text-slate-400 italic">Attended</span></h3>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left">
                                <thead class="border-b border-slate-100">
                                    <tr>
                                        <th class="pb-5 tech-label">Event Title</th>
                                        <th class="pb-5 tech-label">Date</th>
                                        <th class="pb-5 tech-label text-right">Status</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-50">
                                    <?php
                                    $history = [
                                        ['title' => 'Residential Design Meetup', 'date' => 'Oct 20, 2025', 'status' => 'Attended'],
                                        ['title' => 'Sustainable Materials Expo', 'date' => 'Aug 12, 2025', 'status' => 'Attended'],
                                        ['title' => 'Heritage Restoration Talk', 'date' => 'Jun 05, 2025', 'status' => 'Missed']
                                    ];
                                    foreach ($history as $row):
                                    ?>
                                        <tr class="group hover:bg-slate-50 transition-colors">
                                            <td class="py-6 text-[10px] font-bold uppercase tracking-widest text-slate-900"><?= e($row['title']) ?></td>
                                            <td class="py-6 text-[9px] font-bold uppercase tracking-widest text-slate-400"><?= e($row['date']) ?></td>
                                            <td class="py-6 text-right">
                                                <span class="status-badge <?= $row['status'] === 'Attended' ? 'badge-published' : 'badge-missed' ?>">
                                                    <?= e($row['status']) ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- COUNCIL PROFILE -->
                <div class="lg:col-span-3 mt-4">
                    <div class="console-card">
                        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-6 mb-8">
                            <h3 class="font-druk text-xl uppercase">Council <span class="text-slate-400 italic">Profile</span></h3>
                            <span class="text-[8px] font-black uppercase tracking-[0.3em] text-slate-400">
                                Approval status:
                                <span class="text-slate-900"><?= e($teamProfile['approval_status'] ?? 'not_submitted') ?></span>
                            </span>
                        </div>

                        <?php if ($teamProfileStatus): ?>
                            <div class="mb-6 notice notice-ok"><?= e($teamProfileStatus) ?></div>
                        <?php endif; ?>
                        <?php if ($teamProfileError): ?>
                            <div class="mb-6 notice notice-warn"><?= e($teamProfileError) ?></div>
                        <?php endif; ?>

                        <form class="grid grid-cols-1 lg:grid-cols-3 gap-6" action="accountpage.php" method="post" enctype="multipart/form-data">
                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="action" value="team_profile_update">

                            <div class="lg:col-span-2 space-y-6">
                                <div>
                                    <label class="tech-label">Professional Title</label>
                                    <input type="text" name="title" class="tech-input" placeholder="Principal Architect" value="<?= e($teamProfile['title'] ?? '') ?>" required>
                                </div>
                                <div>
                                    <label class="tech-label">Council Category</label>
                                    <select name="category" class="tech-input" required>
                                        <?php
                                        $categories = ['Principal', 'Senior', 'Associate', 'Advisor', 'Staff'];
                                        $selectedCategory = $teamProfile['category'] ?? 'Principal';
                                        foreach ($categories as $cat):
                                        ?>
                                            <option value="<?= e($cat) ?>" <?= $selectedCategory === $cat ? 'selected' : '' ?>><?= e($cat) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="flex items-center gap-4">
                                    <label class="flex items-center gap-3 text-[9px] font-black uppercase tracking-widest text-slate-500">
                                        <input type="checkbox" name="visible" class="accent-slate-900" <?= !empty($teamProfile['visible']) ? 'checked' : '' ?>>
                                        Ready to publish my profile
                                    </label>
                                </div>
                            </div>

                            <div class="space-y-6">
                                <div class="border border-slate-100 eaa-radius p-4 bg-slate-50">
                                    <span class="tech-label">Profile Photo</span>
                                    <div class="mt-3 flex items-center gap-4">
                                        <?php
                                        $photoPreview = $teamProfile['photo_path'] ?? null;
                                        $photoUrl = $photoPreview ? asset($photoPreview) : 'https://via.placeholder.com/120x160?text=EAA';
                                        ?>
                                        <img src="<?= e($photoUrl) ?>" alt="Profile preview" class="w-20 h-28 object-cover eaa-radius border border-slate-200">
                                        <div class="flex-1">
                                            <input type="file" name="photo" accept="image/png,image/jpeg,image/webp" class="text-[9px] font-bold text-slate-500">
                                            <p class="text-[8px] text-slate-400 mt-2 uppercase tracking-widest">Portrait orientation recommended</p>
                                        </div>
                                    </div>
                                </div>

                                <button type="submit" class="w-full py-4 bg-slate-900 text-white text-[9px] font-black uppercase tracking-widest eaa-radius hover:bg-slate-700 transition-all">
                                    Submit for Review
                                </button>

                                <p class="text-[8px] text-slate-400 uppercase tracking-widest">
                                    Admin approval required before appearing on the council page.
                                </p>
                            </div>
                        </form>
                    </div>
                </div>

            </div>
        </div>

        <!-- TAB: JOURNAL -->
        <div id="journal" class="tab-content reveal">
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-12">

                <!-- History -->
                <div class="lg:col-span-4 space-y-6">
                    <h3 class="font-druk text-xl mb-8">Drafts & <span class="text-slate-400 italic">Archive</span></h3>

                    <?php if ($blogStatus): ?>
                        <div class="notice notice-ok"><?= e($blogStatus) ?></div>
                    <?php endif; ?>
                    <?php if ($blogError): ?>
                        <div class="notice notice-bad"><?= e($blogError) ?></div>
                    <?php endif; ?>

                    <?php if (empty($blogs)): ?>
                        <div class="p-6 bg-white border border-slate-100 eaa-radius">
                            <span class="tech-label">No submissions</span>
                            <p class="text-[9px] text-slate-500 font-semibold">Create your first journal post on the right.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($blogs as $b): ?>
                            <?php
                            $badgeClass = 'badge-pending';
                            if ($b['status'] === 'published') $badgeClass = 'badge-published';
                            if ($b['status'] === 'rejected')  $badgeClass = 'badge-missed';
                            ?>
                            <div class="p-6 bg-white border border-slate-100 eaa-radius">
                                <div class="flex justify-between items-start mb-4">
                                    <span class="tech-label">REF: BLOG-<?= e((string)$b['id']) ?></span>
                                    <span class="status-badge <?= $badgeClass ?>"><?= e($b['status']) ?></span>
                                </div>
                                <h4 class="text-xs font-bold text-slate-900 uppercase leading-snug mb-2"><?= e($b['title']) ?></h4>
                                <p class="text-[9px] text-slate-400 font-medium">Last modified: <?= e(date('d M Y', strtotime($b['updated_at']))) ?></p>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- Create blog -->
                <div class="lg:col-span-8">
                    <div class="console-card">
                        <h3 class="font-druk text-xl mb-8">Submit <span class="text-slate-400 italic">Manuscript</span></h3>

                        <form class="space-y-8" action="accountpage.php" method="post" enctype="multipart/form-data" id="blogForm">
                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="action" value="blog_create">
                            <input type="hidden" name="content_html" id="content_html">

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label class="tech-label">Journal Title</label>
                                    <input type="text" name="blog_title" class="tech-input" placeholder="Enter post title" required>
                                </div>
                                <div>
                                    <label class="tech-label">Category</label>
                                    <select name="blog_category" class="tech-input">
                                        <option value="Urban Sustainability">Urban Sustainability</option>
                                        <option value="Residential Detail">Residential Detail</option>
                                        <option value="Heritage Preservation">Heritage Preservation</option>
                                        <option value="General">General</option>
                                    </select>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label class="tech-label">Featured Image</label>
                                    <input type="file" name="featured_image" accept="image/png,image/jpeg,image/webp" class="tech-input pt-2">
                                    <span class="text-[7px] font-bold text-slate-400 mt-2 block uppercase tracking-widest">Optional. JPG/PNG/WebP only.</span>
                                </div>
                                <div>
                                    <label class="tech-label">Quick Tip</label>
                                    <div class="p-4 bg-slate-50 border border-slate-100 eaa-radius text-[9px] font-semibold text-slate-600">
                                        Use the toolbar to format text and insert images inside the article body.
                                    </div>
                                </div>
                            </div>

                            <div>
                                <label class="tech-label">Article Narrative</label>

                                <div class="editor-toolbar">
                                    <button type="button" class="toolbar-btn" data-cmd="bold" title="Bold">
                                        <i class="fa-solid fa-bold"></i>
                                    </button>
                                    <button type="button" class="toolbar-btn" data-cmd="italic" title="Italic">
                                        <i class="fa-solid fa-italic"></i>
                                    </button>

                                    <div style="width:1px;height:18px;background:#e2e8f0;margin:0 6px;align-self:center;"></div>

                                    <button type="button" class="toolbar-btn" data-cmd="justifyLeft" title="Align Left">
                                        <i class="fa-solid fa-align-left"></i>
                                    </button>
                                    <button type="button" class="toolbar-btn" data-cmd="justifyCenter" title="Align Center">
                                        <i class="fa-solid fa-align-center"></i>
                                    </button>
                                    <button type="button" class="toolbar-btn" data-cmd="justifyRight" title="Align Right">
                                        <i class="fa-solid fa-align-right"></i>
                                    </button>
                                    <button type="button" class="toolbar-btn" data-cmd="justifyFull" title="Justify">
                                        <i class="fa-solid fa-align-justify"></i>
                                    </button>

                                    <div style="width:1px;height:18px;background:#e2e8f0;margin:0 6px;align-self:center;"></div>

                                    <button type="button" class="toolbar-btn" id="insertImageBtn" title="Insert Image">
                                        <i class="fa-regular fa-image"></i>
                                    </button>
                                    <input type="file" id="inlineImageInput" accept="image/png,image/jpeg,image/webp" style="display:none;">
                                </div>

                                <div id="editor" class="editor-surface" contenteditable="true" spellcheck="true">
                                    <p style="margin:0 0 10px 0;">Draft your professional insights here...</p>
                                </div>

                                <span class="text-[7px] font-bold text-slate-400 mt-2 block uppercase tracking-widest">
                                    Editor saves HTML. Admin can review before publishing.
                                </span>
                            </div>

                            <div class="pt-6 border-t border-slate-50 flex justify-end gap-4">
                                <button type="submit" name="save_draft" value="1" class="px-8 py-4 text-[9px] font-black uppercase tracking-widest text-slate-500 border border-slate-200 eaa-radius hover:bg-slate-50">
                                    Save Draft
                                </button>
                                <button type="submit" class="px-10 py-4 bg-slate-900 text-white text-[9px] font-black uppercase tracking-widest eaa-radius shadow-xl hover:bg-slate-700 transition-all">
                                    Submit for Review
                                </button>
                            </div>
                        </form>

                    </div>
                </div>

            </div>
        </div>

        <!-- TAB: CAREERS -->
        <div id="careers" class="tab-content reveal">
            <div class="max-w-5xl mx-auto">
                <div class="console-card">

                    <div class="flex flex-col md:flex-row md:items-end justify-between gap-6 mb-10">
                        <div>
                            <h3 class="font-druk text-2xl lg:text-3xl">Firm <span class="text-slate-400 italic">Recruitment</span></h3>
                            <p class="text-[10px] font-bold uppercase tracking-widest text-slate-400 mt-2">Post openings and track applications</p>
                        </div>
                    </div>

                    <?php if (!empty($jobStatus)): ?>
                        <div class="mb-6 notice notice-ok"><?= e($jobStatus) ?></div>
                    <?php endif; ?>
                    <?php if (!empty($jobError)): ?>
                        <div class="mb-6 notice notice-bad"><?= e($jobError) ?></div>
                    <?php endif; ?>

                    <!-- Create Job -->
                    <form method="post" action="accountpage.php#careers" class="border border-slate-100 bg-slate-50 eaa-radius p-6 mb-10">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="action" value="job_create">

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="tech-label">Job Title</label>
                                <input name="job_title" class="tech-input" placeholder="Senior Project Architect" required>
                            </div>
                            <div>
                                <label class="tech-label">Job Type</label>
                                <select name="job_type" class="tech-input">
                                    <option>Full-Time</option>
                                    <option>Part-Time</option>
                                    <option>Contract</option>
                                    <option>Internship</option>
                                </select>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6">
                            <div>
                                <label class="tech-label">Location (optional)</label>
                                <input name="job_location" class="tech-input" placeholder="Erode / Chennai / Remote">
                            </div>
                            <div>
                                <label class="tech-label">Deadline (optional)</label>
                                <input type="date" name="job_deadline" class="tech-input">
                            </div>
                        </div>

                        <div class="mt-6">
                            <label class="tech-label">Description</label>
                            <textarea name="job_description" class="tech-input" style="min-height:140px;text-transform:none;letter-spacing:normal;" placeholder="Write responsibilities, requirements, and how to apply..." required></textarea>
                        </div>

                        <div class="mt-6 flex justify-end">
                            <button class="px-10 py-4 bg-slate-900 text-white text-[9px] font-black uppercase tracking-widest eaa-radius shadow-lg hover:bg-slate-700 transition-all">
                                Post New Position
                            </button>
                        </div>
                    </form>

                    <!-- Jobs List -->
                    <div>
                        <span class="tech-label">Your Posted Jobs</span>

                        <?php if (empty($jobs)): ?>
                            <div class="p-6 bg-white border border-slate-100 eaa-radius mt-4">
                                <p class="text-[9px] font-semibold text-slate-500">No jobs posted yet.</p>
                            </div>
                        <?php else: ?>
                            <div class="space-y-4 mt-4">
                                <?php foreach ($jobs as $job): ?>
                                    <div class="flex flex-col md:flex-row items-center gap-6 p-6 border border-slate-100 eaa-radius hover:border-slate-300 transition-all">
                                        <div class="flex-1">
                                            <h4 class="text-sm font-black text-slate-900 uppercase tracking-tight mb-2"><?= e($job['title']) ?></h4>
                                            <div class="flex gap-3 flex-wrap">
                                                <span class="text-[8px] font-black text-slate-400 uppercase tracking-widest"><?= e($job['job_type']) ?></span>
                                                <?php if (!empty($job['deadline'])): ?>
                                                    <span class="text-[8px] font-black text-slate-400 uppercase tracking-widest">Deadline: <?= e(date('d M Y', strtotime($job['deadline']))) ?></span>
                                                <?php endif; ?>
                                                <span class="text-[8px] font-black text-slate-400 uppercase tracking-widest">Status: <?= e($job['status']) ?></span>
                                            </div>
                                        </div>

                                        <a href="job_applications.php?job_id=<?= e((string)$job['id']) ?>"
                                           class="px-6 py-2 bg-slate-100 text-slate-900 text-[8px] font-black uppercase tracking-widest eaa-radius hover:bg-slate-200 transition-all">
                                            View Applications
                                        </a>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                </div>
            </div>
        </div>

    </div>
</main>

<?php require_once __DIR__ . "/partials/footer.php"; ?>

<script>
    // Tabs
    function activateTab(tabId) {
        document.querySelectorAll('.console-tab').forEach(t => t.classList.remove('active'));
        document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));

        const tabBtn = document.querySelector('.console-tab[data-tab="' + tabId + '"]');
        if (tabBtn) tabBtn.classList.add('active');

        const content = document.getElementById(tabId);
        if (content) content.classList.add('active');

        if (content) content.querySelectorAll('.reveal').forEach(el => el.classList.add('active'));
    }

    document.querySelectorAll('.console-tab').forEach(tab => {
        tab.addEventListener('click', () => activateTab(tab.getAttribute('data-tab')));
    });

    // Hash open
    if (location.hash === '#journal') activateTab('journal');
    if (location.hash === '#careers') activateTab('careers');

    // Reveal observer
    document.addEventListener('DOMContentLoaded', () => {
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(e => { if (e.isIntersecting) e.target.classList.add('active'); });
        }, { threshold: 0.1 });
        document.querySelectorAll('.reveal').forEach(el => observer.observe(el));
    });

    // Editor toolbar
    const editor = document.getElementById('editor');

    document.querySelectorAll('.toolbar-btn[data-cmd]').forEach(btn => {
        btn.addEventListener('click', () => {
            const cmd = btn.getAttribute('data-cmd');
            document.execCommand(cmd, false, null);
            editor && editor.focus();
        });
    });

    // Inline image insert (base64)
    const insertImageBtn = document.getElementById('insertImageBtn');
    const inlineImageInput = document.getElementById('inlineImageInput');

    if (insertImageBtn && inlineImageInput) {
        insertImageBtn.addEventListener('click', () => inlineImageInput.click());

        inlineImageInput.addEventListener('change', async (e) => {
            const file = e.target.files && e.target.files[0];
            if (!file) return;

            const ok = ['image/jpeg', 'image/png', 'image/webp'].includes(file.type);
            if (!ok) {
                alert('Only JPG/PNG/WebP allowed.');
                inlineImageInput.value = '';
                return;
            }

            const reader = new FileReader();
            reader.onload = () => {
                const imgHtml = `
                    <p style="margin:14px 0;">
                        <img src="${reader.result}" alt="inline" style="max-width:100%;border-radius:5px;border:1px solid #e2e8f0;" />
                    </p>`;
                document.execCommand('insertHTML', false, imgHtml);
                editor && editor.focus();
            };
            reader.readAsDataURL(file);
            inlineImageInput.value = '';
        });
    }

    // Before submit: put editor HTML into hidden field
    const blogForm = document.getElementById('blogForm');
    if (blogForm) {
        blogForm.addEventListener('submit', () => {
            const hidden = document.getElementById('content_html');
            if (hidden && editor) hidden.value = editor.innerHTML.trim();
        });
    }
</script>
