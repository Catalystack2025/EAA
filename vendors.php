<?php
declare(strict_types=1);

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

/* =========================================================
   vendors.php — VENDOR DASHBOARD (FULL)
   ✅ Vendor profile (company + phone + location + website)
   ✅ Sponsor marquee application (logo upload + admin approval)
   ✅ Product add/manage (pending/active) -> connect.php uses this
   ✅ Auto upload dir create + writable check
   ✅ Same styling language (Montserrat + 5px)
   ========================================================= */

require_once __DIR__ . '/lib/helpers.php';
require_once __DIR__ . '/config/db.php';

start_session();

$currentUserId = $_SESSION['user_id'] ?? null;
$currentRole   = $_SESSION['role'] ?? null;

if ($currentUserId === null) {
    redirect('login.php');
}
if ($currentRole !== 'vendor') {
    // if you want allow admin also, change condition
    flash_set('error', 'Access denied. Vendor only.');
    redirect('index.php');
}

$pageTitle = 'Vendor Dashboard | EAA';

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

function ensure_upload_dir(string $dir): void
{
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    if (!is_writable($dir)) {
        throw new RuntimeException('Upload directory is not writable: ' . $dir);
    }
}

function upload_image(array $file, string $prefix, string $uploadDirAbs, string $uploadDirWeb, int $userId, int $maxBytes = 3145728): string
{
    if (empty($file['name'])) {
        throw new RuntimeException('No file selected.');
    }

    if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Upload failed (code ' . ($file['error'] ?? 'unknown') . ').');
    }

    if (!is_uploaded_file($file['tmp_name'])) {
        throw new RuntimeException('Upload tmp file is not valid.');
    }

    if (!empty($file['size']) && (int)$file['size'] > $maxBytes) {
        throw new RuntimeException('Image must be under 3MB.');
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

    $filename = sprintf('%s_%d_%s.%s', $prefix, $userId, bin2hex(random_bytes(6)), $allowed[$mimeType]);
    $targetAbs = rtrim($uploadDirAbs, '/') . '/' . $filename;

    if (!move_uploaded_file($file['tmp_name'], $targetAbs)) {
        throw new RuntimeException('Failed to store uploaded image.');
    }

    return rtrim($uploadDirWeb, '/') . '/' . $filename;
}

/* -----------------------------
   Ensure tables exist (safe create)
------------------------------ */
if (!table_exists('vendor_profile')) {
    db()->exec("
        CREATE TABLE vendor_profile (
          id INT AUTO_INCREMENT PRIMARY KEY,
          user_id INT NOT NULL UNIQUE,
          company_name VARCHAR(200) NOT NULL,
          phone VARCHAR(50) NULL,
          location VARCHAR(120) NULL,
          website VARCHAR(255) NULL,
          created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
}

if (!table_exists('sponsor_requests')) {
    db()->exec("
        CREATE TABLE sponsor_requests (
          id INT AUTO_INCREMENT PRIMARY KEY,
          vendor_user_id INT NOT NULL,
          company_name VARCHAR(255) NOT NULL,
          logo_path VARCHAR(255) NULL,
          website VARCHAR(255) NULL,
          phone VARCHAR(50) NULL,
          note TEXT NULL,
          status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
          reviewed_by INT NULL,
          reviewed_at DATETIME NULL,
          created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          INDEX (vendor_user_id),
          INDEX (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
} else {
    $alterStatements = [];
    if (!column_exists('sponsor_requests', 'logo_path')) $alterStatements[] = 'ADD COLUMN logo_path VARCHAR(255) NULL';
    if (!column_exists('sponsor_requests', 'website')) $alterStatements[] = 'ADD COLUMN website VARCHAR(255) NULL';
    if (!column_exists('sponsor_requests', 'phone')) $alterStatements[] = 'ADD COLUMN phone VARCHAR(50) NULL';
    if (!column_exists('sponsor_requests', 'note')) $alterStatements[] = 'ADD COLUMN note TEXT NULL';
    if (!column_exists('sponsor_requests', 'reviewed_by')) $alterStatements[] = 'ADD COLUMN reviewed_by INT NULL';
    if (!column_exists('sponsor_requests', 'reviewed_at')) $alterStatements[] = 'ADD COLUMN reviewed_at DATETIME NULL';
    if (!column_exists('sponsor_requests', 'updated_at')) $alterStatements[] = 'ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP';
    if ($alterStatements) {
        db()->exec('ALTER TABLE sponsor_requests ' . implode(', ', $alterStatements));
    }
}

if (!table_exists('vendor_products')) {
    db()->exec("
        CREATE TABLE vendor_products (
          id INT AUTO_INCREMENT PRIMARY KEY,
          vendor_id INT NOT NULL,
          name VARCHAR(200) NOT NULL,
          category VARCHAR(120) NOT NULL,
          price DECIMAL(10,2) NOT NULL DEFAULT 0,
          unit VARCHAR(50) NOT NULL DEFAULT 'SQFT',
          location VARCHAR(120) NULL,
          image_url VARCHAR(255) NULL,
          status ENUM('pending','active','inactive','rejected') NOT NULL DEFAULT 'pending',
          created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          INDEX (vendor_id),
          INDEX (status),
          INDEX (category),
          INDEX (location)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
}

/* -----------------------------
   Load vendor profile
------------------------------ */
$vpStmt = db()->prepare("SELECT * FROM vendor_profile WHERE user_id = ? LIMIT 1");
$vpStmt->execute([$currentUserId]);
$vendorProfile = $vpStmt->fetch(PDO::FETCH_ASSOC);

/* -----------------------------
   Load sponsor request (latest)
------------------------------ */
$sponsorStmt = db()->prepare("SELECT * FROM sponsor_requests WHERE vendor_user_id = ? ORDER BY id DESC LIMIT 1");
$sponsorStmt->execute([$currentUserId]);
$sponsorApp = $sponsorStmt->fetch(PDO::FETCH_ASSOC);

/* -----------------------------
   Load products (vendor only)
------------------------------ */
$vendorId = $vendorProfile['id'] ?? null;
$products = [];

if ($vendorId) {
    $prodStmt = db()->prepare("SELECT * FROM vendor_products WHERE vendor_id = ? ORDER BY updated_at DESC LIMIT 50");
    $prodStmt->execute([$vendorId]);
    $products = $prodStmt->fetchAll(PDO::FETCH_ASSOC);
}

/* -----------------------------
   Handle POST Actions
------------------------------ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        flash_set('error', 'Invalid session token. Please try again.');
        redirect('vendors.php');
    }

    // A) Save vendor profile
    if ($action === 'save_vendor_profile') {
        $company = trim($_POST['company_name'] ?? '');
        $phone   = trim($_POST['phone'] ?? '');
        $loc     = trim($_POST['location'] ?? '');
        $web     = trim($_POST['website'] ?? '');

        if ($company === '') {
            flash_set('vp_error', 'Company name is required.');
            redirect('vendors.php');
        }

        if ($vendorProfile) {
            $up = db()->prepare("UPDATE vendor_profile SET company_name=?, phone=?, location=?, website=? WHERE user_id=?");
            $up->execute([$company, $phone, $loc, $web, $currentUserId]);
        } else {
            $ins = db()->prepare("INSERT INTO vendor_profile (user_id, company_name, phone, location, website) VALUES (?,?,?,?,?)");
            $ins->execute([$currentUserId, $company, $phone, $loc, $web]);
        }

        flash_set('vp_status', 'Vendor profile saved.');
        redirect('vendors.php');
    }

    // Refresh vendor profile after save
    $vpStmt->execute([$currentUserId]);
    $vendorProfile = $vpStmt->fetch(PDO::FETCH_ASSOC);
    $vendorId = $vendorProfile['id'] ?? null;

    // B) Sponsor request submit
    if ($action === 'submit_sponsor') {
        $company = trim($_POST['s_company_name'] ?? ($vendorProfile['company_name'] ?? ''));
        $web     = trim($_POST['s_website'] ?? ($vendorProfile['website'] ?? ''));
        $phone   = trim($_POST['s_phone'] ?? ($vendorProfile['phone'] ?? ''));
        $note    = trim($_POST['s_note'] ?? '');

        if ($company === '') {
            flash_set('sponsor_error', 'Company name is required.');
            redirect('vendors.php#sponsor');
        }

        try {
            $uploadDirAbs = __DIR__ . '/public/uploads/sponsors';
            $uploadDirWeb = 'public/uploads/sponsors';
            $logoPath = $sponsorApp['logo_path'] ?? null;

            if (!empty($_FILES['s_logo']['name'])) {
                $logoPath = upload_image($_FILES['s_logo'] ?? [], 'sponsor', $uploadDirAbs, $uploadDirWeb, (int)$currentUserId);
                if (!empty($sponsorApp['logo_path']) && !str_contains($sponsorApp['logo_path'], '://')) {
                    $oldRelative = ltrim((string)$sponsorApp['logo_path'], '/');
                    $oldAbs = __DIR__ . '/' . $oldRelative;
                    if (is_file($oldAbs)) {
                        @unlink($oldAbs);
                    }
                }
            }

            if ($logoPath === null || $logoPath === '') {
                throw new RuntimeException('Please upload a logo.');
            }

            if ($sponsorApp) {
                $upd = db()->prepare(
                    "UPDATE sponsor_requests
                     SET company_name = :company_name,
                         logo_path = :logo_path,
                         website = :website,
                         phone = :phone,
                         note = :note,
                         status = 'pending',
                         reviewed_by = NULL,
                         reviewed_at = NULL
                     WHERE id = :id AND vendor_user_id = :vendor_user_id"
                );
                $upd->execute([
                    'company_name' => $company,
                    'logo_path' => $logoPath,
                    'website' => $web !== '' ? $web : null,
                    'phone' => $phone !== '' ? $phone : null,
                    'note' => $note !== '' ? $note : null,
                    'id' => $sponsorApp['id'],
                    'vendor_user_id' => $currentUserId,
                ]);
            } else {
                $ins = db()->prepare(
                    "INSERT INTO sponsor_requests (vendor_user_id, company_name, logo_path, website, phone, note, status)
                     VALUES (:vendor_user_id, :company_name, :logo_path, :website, :phone, :note, 'pending')"
                );
                $ins->execute([
                    'vendor_user_id' => $currentUserId,
                    'company_name' => $company,
                    'logo_path' => $logoPath,
                    'website' => $web !== '' ? $web : null,
                    'phone' => $phone !== '' ? $phone : null,
                    'note' => $note !== '' ? $note : null,
                ]);
            }

            flash_set('sponsor_status', 'Sponsor request submitted. Waiting for admin approval.');
            redirect('vendors.php#sponsor');
        } catch (Throwable $e) {
            flash_set('sponsor_error', 'Failed to submit sponsor request: ' . $e->getMessage());
            redirect('vendors.php#sponsor');
        }
    }

    // C) Add product
    if ($action === 'add_product') {
        if (!$vendorId) {
            flash_set('prod_error', 'Please save Vendor Profile first.');
            redirect('vendors.php#products');
        }

        $name     = trim($_POST['p_name'] ?? '');
        $category = trim($_POST['p_category'] ?? '');
        $price    = (float)($_POST['p_price'] ?? 0);
        $unit     = trim($_POST['p_unit'] ?? 'SQFT');
        $loc      = trim($_POST['p_location'] ?? ($vendorProfile['location'] ?? ''));

        if ($name === '' || $category === '') {
            flash_set('prod_error', 'Product Name and Category are required.');
            redirect('vendors.php#products');
        }

        try {
            $imgPath = null;
            if (!empty($_FILES['p_image']['name'])) {
                $uploadDirAbs = __DIR__ . '/public/uploads/products';
                $uploadDirWeb = 'public/uploads/products';
                $imgPath = upload_image($_FILES['p_image'], 'product', $uploadDirAbs, $uploadDirWeb, (int)$currentUserId);
            }

            $ins = db()->prepare("
                INSERT INTO vendor_products (vendor_id, name, category, price, unit, location, image_url, status)
                VALUES (:vendor_id, :name, :category, :price, :unit, :location, :image_url, 'pending')
            ");
            $ins->execute([
                'vendor_id' => $vendorId,
                'name' => $name,
                'category' => $category,
                'price' => $price,
                'unit' => $unit !== '' ? $unit : 'SQFT',
                'location' => $loc !== '' ? $loc : null,
                'image_url' => $imgPath,
            ]);

            flash_set('prod_status', 'Product submitted. Waiting for admin approval.');
            redirect('vendors.php#products');
        } catch (Throwable $e) {
            flash_set('prod_error', 'Failed to add product: ' . $e->getMessage());
            redirect('vendors.php#products');
        }
    }

    // D) Delete product (vendor can delete only own)
    if ($action === 'delete_product') {
        if (!$vendorId) redirect('vendors.php#products');

        $pid = (int)($_POST['product_id'] ?? 0);
        if ($pid > 0) {
            $del = db()->prepare("DELETE FROM vendor_products WHERE id = ? AND vendor_id = ?");
            $del->execute([$pid, $vendorId]);
            flash_set('prod_status', 'Product deleted.');
        }
        redirect('vendors.php#products');
    }
}

/* -----------------------------
   Reload sponsor + products for view
------------------------------ */
$sponsorStmt->execute([$currentUserId]);
$sponsorApp = $sponsorStmt->fetch(PDO::FETCH_ASSOC);

if ($vendorId) {
    $prodStmt = db()->prepare("SELECT * FROM vendor_products WHERE vendor_id = ? ORDER BY updated_at DESC LIMIT 50");
    $prodStmt->execute([$vendorId]);
    $products = $prodStmt->fetchAll(PDO::FETCH_ASSOC);
}

/* -----------------------------
   Flash
------------------------------ */
$globalError = flash_get('error');

$vpStatus = flash_get('vp_status');
$vpError  = flash_get('vp_error');

$sponsorStatus = flash_get('sponsor_status');
$sponsorError  = flash_get('sponsor_error');

$prodStatus = flash_get('prod_status');
$prodError  = flash_get('prod_error');

$logoRequired = empty($sponsorApp['logo_path']);
$sponsorUpdated = null;
if (!empty($sponsorApp['updated_at'])) {
    $sponsorUpdated = date('d M Y, h:i A', strtotime((string)$sponsorApp['updated_at']));
} elseif (!empty($sponsorApp['created_at'])) {
    $sponsorUpdated = date('d M Y, h:i A', strtotime((string)$sponsorApp['created_at']));
}

require_once __DIR__ . "/partials/header.php";
?>

<style>
:root{--eaa-smoke:#475569;--eaa-border:#e2e8f0;--eaa-radius:5px;--eaa-accent:#1e293b;}
body{background:#f8fafc;color:#1e293b;font-family:'Montserrat',sans-serif;}
.font-druk{font-family:'Montserrat',sans-serif!important;font-weight:900;text-transform:uppercase;letter-spacing:-.05em;line-height:.85;}
.eaa-radius{border-radius:var(--eaa-radius)!important;}
.tech-label{font-size:8px;font-weight:900;text-transform:uppercase;letter-spacing:.2em;color:#94a3b8;display:block;margin-bottom:8px;}
.tech-input{width:100%;background:#fff;border:1px solid var(--eaa-border);border-radius:var(--eaa-radius);padding:12px 16px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--eaa-accent);outline:none;transition:.2s;}
.tech-input:focus{border-color:var(--eaa-smoke);box-shadow:0 0 0 1px var(--eaa-smoke);}
.console-card{background:#fff;border:1px solid var(--eaa-border);border-radius:var(--eaa-radius);padding:26px;}
.notice{padding:12px 14px;border-radius:var(--eaa-radius);font-size:10px;font-weight:900;text-transform:uppercase;letter-spacing:.12em;}
.notice-ok{background:#ecfdf5;border:1px solid #a7f3d0;color:#065f46;}
.notice-warn{background:#fffbeb;border:1px solid #fde68a;color:#92400e;}
.notice-bad{background:#fef2f2;border:1px solid #fecaca;color:#991b1b;}
.badge{font-size:8px;font-weight:900;text-transform:uppercase;letter-spacing:.12em;padding:4px 10px;border-radius:2px;display:inline-block;}
.badge-pending{background:#fef3c7;color:#92400e;}
.badge-approved{background:#dcfce7;color:#166534;}
.badge-rejected{background:#fee2e2;color:#b91c1c;}
</style>

<div class="container mx-auto px-6 pt-36 pb-10 max-w-6xl">
    <div class="mb-10">
        <h1 class="font-druk text-5xl md:text-6xl text-slate-900">Vendor <span class="text-slate-400 italic">Console</span></h1>
        <p class="mt-4 text-[10px] font-black uppercase tracking-widest text-slate-400">Sponsor marquee + product listings for members</p>
    </div>

    <?php if ($globalError): ?><div class="notice notice-bad mb-6"><?= e($globalError) ?></div><?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">

        <!-- Vendor Profile -->
        <div class="lg:col-span-5">
            <div class="console-card">
                <h2 class="font-druk text-xl mb-6">Vendor <span class="text-slate-400 italic">Profile</span></h2>

                <?php if ($vpStatus): ?><div class="notice notice-ok mb-5"><?= e($vpStatus) ?></div><?php endif; ?>
                <?php if ($vpError): ?><div class="notice notice-warn mb-5"><?= e($vpError) ?></div><?php endif; ?>

                <form method="post" action="<?= e(url('vendors.php')) ?>">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="save_vendor_profile">

                    <div class="mb-5">
                        <label class="tech-label">Company Name</label>
                        <input name="company_name" class="tech-input" value="<?= e($vendorProfile['company_name'] ?? '') ?>" placeholder="Your firm / showroom name" required>
                    </div>

                    <div class="mb-5">
                        <label class="tech-label">Phone</label>
                        <input name="phone" class="tech-input" value="<?= e($vendorProfile['phone'] ?? '') ?>" placeholder="+91...">
                    </div>

                    <div class="mb-5">
                        <label class="tech-label">Location</label>
                        <input name="location" class="tech-input" value="<?= e($vendorProfile['location'] ?? '') ?>" placeholder="Erode / Coimbatore...">
                    </div>

                    <div class="mb-6">
                        <label class="tech-label">Website</label>
                        <input name="website" class="tech-input" value="<?= e($vendorProfile['website'] ?? '') ?>" placeholder="https://...">
                    </div>

                    <button type="submit" class="w-full py-4 bg-slate-900 text-white text-[9px] font-black uppercase tracking-widest eaa-radius hover:bg-slate-700 transition-all">
                        Save Profile
                    </button>
                </form>
            </div>
        </div>

        <!-- Sponsor Application -->
        <div class="lg:col-span-7" id="sponsor">
            <div class="console-card">
                <div class="flex items-center justify-between gap-6 mb-6">
                    <h2 class="font-druk text-xl">Sponsor <span class="text-slate-400 italic">Marquee</span></h2>
                    <?php if ($sponsorApp): ?>
                        <?php
                          $cls = $sponsorApp['status'] === 'approved' ? 'badge-approved' : ($sponsorApp['status'] === 'rejected' ? 'badge-rejected' : 'badge-pending');
                        ?>
                        <span class="badge <?= $cls ?>"><?= e($sponsorApp['status']) ?></span>
                    <?php else: ?>
                        <span class="badge badge-pending">not submitted</span>
                    <?php endif; ?>
                </div>

                <?php if ($sponsorStatus): ?><div class="notice notice-ok mb-5"><?= e($sponsorStatus) ?></div><?php endif; ?>
                <?php if ($sponsorError): ?><div class="notice notice-warn mb-5"><?= e($sponsorError) ?></div><?php endif; ?>

                <div class="mb-6 text-[10px] font-bold text-slate-600">
                    Upload your logo and apply. Admin will approve, then it will appear in sponsors marquee.
                    <?php if ($sponsorUpdated): ?>
                        <div class="text-[9px] font-bold uppercase tracking-widest text-slate-400 mt-2">Last update: <?= e($sponsorUpdated) ?></div>
                    <?php endif; ?>
                </div>

                <form method="post" action="<?= e(url('vendors.php#sponsor')) ?>" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="submit_sponsor">

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div>
                            <label class="tech-label">Company Name</label>
                            <input name="s_company_name" class="tech-input" value="<?= e($sponsorApp['company_name'] ?? ($vendorProfile['company_name'] ?? '')) ?>" required>
                        </div>
                        <div>
                            <label class="tech-label">Website</label>
                            <input name="s_website" class="tech-input" value="<?= e($sponsorApp['website'] ?? ($vendorProfile['website'] ?? '')) ?>" placeholder="https://...">
                        </div>
                        <div>
                            <label class="tech-label">Phone (optional)</label>
                            <input name="s_phone" class="tech-input" value="<?= e($sponsorApp['phone'] ?? ($vendorProfile['phone'] ?? '')) ?>" placeholder="+91...">
                        </div>
                        <div>
                            <label class="tech-label">Short Note (optional)</label>
                            <input name="s_note" class="tech-input" value="<?= e($sponsorApp['note'] ?? '') ?>" placeholder="Short intro or note">
                        </div>
                        <div class="md:col-span-2">
                            <label class="tech-label">Logo (JPG/PNG/WebP)</label>
                            <input type="file" name="s_logo" class="tech-input pt-2" accept="image/png,image/jpeg,image/webp" <?= $logoRequired ? 'required' : '' ?>>
                            <span class="text-[8px] font-bold text-slate-400 uppercase tracking-widest block mt-2">
                                Logo will be used in marquee after approval. Max 3MB.
                            </span>
                        </div>
                    </div>

                    <div class="pt-6">
                    <button type="submit" class="w-full py-4 bg-slate-900 text-white text-[9px] font-black uppercase tracking-widest eaa-radius hover:bg-slate-700 transition-all">
                            <?= $sponsorApp ? 'Resubmit Sponsor Request' : 'Submit Sponsor Request' ?>
                    </button>
                    </div>

                    <?php if ($sponsorApp && !empty($sponsorApp['logo_path'])): ?>
                        <div class="mt-8 p-4 border border-slate-100 eaa-radius bg-slate-50 flex items-center gap-4">
                            <img src="<?= e(asset($sponsorApp['logo_path'])) ?>" class="w-16 h-16 object-contain bg-white border border-slate-100 eaa-radius p-2" alt="Logo">
                            <div>
                                <div class="text-[10px] font-black uppercase tracking-widest text-slate-900"><?= e($sponsorApp['company_name']) ?></div>
                                <div class="text-[9px] font-bold uppercase tracking-widest text-slate-400"><?= e($sponsorApp['website'] ?? '') ?></div>
                                <?php if (!empty($sponsorApp['phone'])): ?>
                                    <div class="text-[9px] font-bold uppercase tracking-widest text-slate-400"><?= e($sponsorApp['phone']) ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </form>
            </div>
        </div>

        <!-- Products -->
        <div class="lg:col-span-12" id="products">
            <div class="console-card">
                <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-6 mb-6">
                    <h2 class="font-druk text-xl">Member <span class="text-slate-400 italic">Products</span></h2>
                    <a href="connect.php" class="text-[9px] font-black uppercase tracking-widest border-b border-slate-900 pb-1">View Public Connect</a>
                </div>

                <?php if ($prodStatus): ?><div class="notice notice-ok mb-5"><?= e($prodStatus) ?></div><?php endif; ?>
                <?php if ($prodError): ?><div class="notice notice-bad mb-5"><?= e($prodError) ?></div><?php endif; ?>

                <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">

                    <!-- Add product -->
                    <div class="lg:col-span-5">
                        <div class="p-6 border border-slate-100 eaa-radius bg-slate-50">
                            <h3 class="font-druk text-lg mb-5">Add <span class="text-slate-400 italic">Product</span></h3>

                            <form method="post" action="<?= e(url('vendors.php#products')) ?>" enctype="multipart/form-data">
                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="action" value="add_product">

                                <div class="mb-4">
                                    <label class="tech-label">Product Name</label>
                                    <input name="p_name" class="tech-input" placeholder="AAC Block / Marble / Tile..." required>
                                </div>

                                <div class="mb-4">
                                    <label class="tech-label">Category</label>
                                    <input name="p_category" class="tech-input" placeholder="Tiles / Cement / Paint..." required>
                                </div>

                                <div class="grid grid-cols-2 gap-4 mb-4">
                                    <div>
                                        <label class="tech-label">Price</label>
                                        <input name="p_price" type="number" step="0.01" class="tech-input" placeholder="120.00" required>
                                    </div>
                                    <div>
                                        <label class="tech-label">Unit</label>
                                        <input name="p_unit" class="tech-input" placeholder="SQFT / PCS / BAG" value="SQFT">
                                    </div>
                                </div>

                                <div class="mb-4">
                                    <label class="tech-label">Location</label>
                                    <input name="p_location" class="tech-input" placeholder="Erode" value="<?= e($vendorProfile['location'] ?? '') ?>">
                                </div>

                                <div class="mb-4">
                                    <label class="tech-label">Image (optional)</label>
                                    <input type="file" name="p_image" class="tech-input pt-2" accept="image/png,image/jpeg,image/webp">
                                </div>

                                <button type="submit" class="w-full py-4 bg-slate-900 text-white text-[9px] font-black uppercase tracking-widest eaa-radius hover:bg-slate-700 transition-all">
                                    Submit Product
                                </button>

                                <p class="mt-3 text-[8px] font-bold uppercase tracking-widest text-slate-400">
                                    Products go to admin for approval (pending → active).
                                </p>
                            </form>
                        </div>
                    </div>

                    <!-- Product list -->
                    <div class="lg:col-span-7">
                        <div class="overflow-x-auto border border-slate-100 eaa-radius">
                            <table class="w-full text-left bg-white">
                                <thead class="bg-slate-50 border-b border-slate-100">
                                    <tr>
                                        <th class="p-4 tech-label">Product</th>
                                        <th class="p-4 tech-label">Category</th>
                                        <th class="p-4 tech-label">Price</th>
                                        <th class="p-4 tech-label">Status</th>
                                        <th class="p-4 tech-label text-right">Action</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-50">
                                <?php if (empty($products)): ?>
                                    <tr><td class="p-5 text-[10px] font-bold text-slate-400 uppercase tracking-widest" colspan="5">No products yet.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($products as $pr): ?>
                                        <?php
                                          $cls = $pr['status'] === 'active' ? 'badge-approved' : ($pr['status'] === 'rejected' ? 'badge-rejected' : 'badge-pending');
                                        ?>
                                        <tr>
                                            <td class="p-4">
                                                <div class="text-[10px] font-black uppercase tracking-widest text-slate-900"><?= e($pr['name']) ?></div>
                                                <div class="text-[9px] font-bold uppercase tracking-widest text-slate-400"><?= e($pr['location'] ?? '') ?></div>
                                            </td>
                                            <td class="p-4 text-[10px] font-bold uppercase tracking-widest text-slate-600"><?= e($pr['category']) ?></td>
                                            <td class="p-4 text-[10px] font-black uppercase tracking-widest text-slate-900">₹<?= e(number_format((float)$pr['price'], 2)) ?> / <?= e($pr['unit']) ?></td>
                                            <td class="p-4"><span class="badge <?= $cls ?>"><?= e($pr['status']) ?></span></td>
                                            <td class="p-4 text-right">
                                                <form method="post" action="<?= e(url('vendors.php#products')) ?>" onsubmit="return confirm('Delete this product?');" style="display:inline;">
                                                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                                    <input type="hidden" name="action" value="delete_product">
                                                    <input type="hidden" name="product_id" value="<?= e((string)$pr['id']) ?>">
                                                    <button type="submit" class="px-4 py-2 border border-slate-200 text-slate-500 text-[9px] font-black uppercase tracking-widest eaa-radius hover:bg-slate-50">
                                                        Delete
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <div class="mt-4 text-[9px] font-bold uppercase tracking-widest text-slate-400">
                            Note: Only <b>active</b> products show in Connect page.
                        </div>
                    </div>

                </div>
            </div>
        </div>

    </div>
</div>

<?php require_once __DIR__ . "/partials/footer.php"; ?>
