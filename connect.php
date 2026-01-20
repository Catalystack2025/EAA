<?php
/* =========================================================
   connect.php — ARCHITECTURAL MATERIAL MARKETPLACE
   ✅ Front-facing catalog for EAA members to find vendors
   ✅ Exclusive Montserrat Typography
   ✅ High-density Technical Grid for Materials
   ✅ Unit Pricing (SQFT) and Technical Indexing
   ✅ Standardized 5px Radius & Smoke Grey Palette
   ========================================================= */

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);


require_once __DIR__ . '/lib/helpers.php';
require_once __DIR__ . '/config/db.php';

start_session();

$pageTitle = 'Connect / Material Catalog | EAA';
require_once __DIR__ . "/partials/header.php";

$filters = [
    'category' => trim($_GET['category'] ?? ''),
    'location' => trim($_GET['location'] ?? ''),
    'search' => trim($_GET['search'] ?? ''),
    'min_price' => $_GET['min_price'] ?? '',
    'max_price' => $_GET['max_price'] ?? '',
];

$minPrice = filter_var($filters['min_price'], FILTER_VALIDATE_FLOAT);
$maxPrice = filter_var($filters['max_price'], FILTER_VALIDATE_FLOAT);

$categoryOptions = db()->query("SELECT DISTINCT category FROM vendor_products WHERE status = 'active' ORDER BY category")
    ->fetchAll(PDO::FETCH_COLUMN);
$locationOptions = db()->query(
    "SELECT DISTINCT location FROM vendor_products
     WHERE status = 'active' AND location IS NOT NULL AND location <> ''
     ORDER BY location"
)->fetchAll(PDO::FETCH_COLUMN);

$conditions = ["vendor_products.status = 'active'"];
$params = [];

if ($filters['category'] !== '') {
    $conditions[] = 'vendor_products.category = :category';
    $params['category'] = $filters['category'];
}

if ($filters['location'] !== '') {
    $conditions[] = 'vendor_products.location = :location';
    $params['location'] = $filters['location'];
}

if ($minPrice !== false) {
    $conditions[] = 'vendor_products.price >= :min_price';
    $params['min_price'] = $minPrice;
}

if ($maxPrice !== false) {
    $conditions[] = 'vendor_products.price <= :max_price';
    $params['max_price'] = $maxPrice;
}

if ($filters['search'] !== '') {
    $conditions[] = '(vendor_products.name LIKE :search OR vendor_profile.company_name LIKE :search)';
    $params['search'] = '%' . $filters['search'] . '%';
}

$query = sprintf(
    "SELECT vendor_products.id,
            vendor_products.name,
            vendor_products.category,
            vendor_products.price,
            vendor_products.unit,
            vendor_products.location,
            vendor_products.image_url,
            vendor_profile.company_name,
            vendor_profile.phone,
            users.email
     FROM vendor_products
     JOIN vendor_profile ON vendor_products.vendor_id = vendor_profile.id
     JOIN users ON vendor_profile.user_id = users.id
     WHERE %s
     ORDER BY vendor_products.created_at DESC",
    implode(' AND ', $conditions)
);

$stmt = db()->prepare($query);
$stmt->execute($params);
$products = $stmt->fetchAll();

$canViewContact = can_view_vendor_contact($_SESSION['role'] ?? null);
?>

<style>
    :root {
        --eaa-smoke: #475569;
        --eaa-border: #e2e8f0;
        --eaa-radius: 5px;
        --eaa-accent: #1e293b;
    }

    body {
        background-color: #f8fafc;
        color: #1e293b;
        font-family: 'Montserrat', sans-serif;
    }

    .font-druk {
        font-family: 'Montserrat', sans-serif !important;
        font-weight: 900;
        text-transform: uppercase;
        letter-spacing: -0.05em;
        line-height: 0.85;
    }

    .eaa-radius { border-radius: var(--eaa-radius) !important; }

    /* Blueprint Background */
    .blueprint-grid {
        background-image: linear-gradient(rgba(71, 85, 105, 0.05) 1px, transparent 1px),
                          linear-gradient(90deg, rgba(71, 85, 105, 0.05) 1px, transparent 1px);
        background-size: 40px 40px;
    }

    /* Product Card Styling */
    .product-card {
        background: #ffffff;
        border: 1px solid var(--eaa-border);
        transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
        overflow: hidden;
        display: flex;
        flex-direction: column;
    }

    .product-card:hover {
        border-color: var(--eaa-smoke);
        transform: translateY(-5px);
        box-shadow: 0 15px 40px rgba(71, 85, 105, 0.1);
    }

    .product-image {
        aspect-ratio: 4/3;
        overflow: hidden;
        background: #f1f5f9;
        position: relative;
    }

    .product-image img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        transition: transform 0.6s ease;
    }

    .product-card:hover .product-image img {
        transform: scale(1.05);
    }

    .tech-label {
        font-size: 8px;
        font-weight: 900;
        text-transform: uppercase;
        letter-spacing: 0.2em;
        color: #94a3b8;
        display: block;
        margin-bottom: 6px;
    }

    /* Custom Inlay Labels */
    .inlay-label {
        position: absolute;
        top: 10px;
        left: 10px;
        background: rgba(255, 255, 255, 0.9);
        padding: 4px 10px;
        font-size: 7px;
        font-weight: 900;
        text-transform: uppercase;
        letter-spacing: 0.1em;
        border-radius: 2px;
        backdrop-filter: blur(4px);
        border: 1px solid var(--eaa-border);
    }

    /* Modal Overlay */
    #contactModal {
        display: none;
        position: fixed;
        inset: 0;
        z-index: 1000;
        background: rgba(15, 23, 42, 0.8);
        backdrop-filter: blur(8px);
        align-items: center;
        justify-content: center;
        padding: 20px;
    }

    .modal-content {
        background: white;
        width: 100%;
        max-width: 450px;
        padding: 40px;
        border-radius: var(--eaa-radius);
        position: relative;
    }

    /* Animation Reveal */
    .reveal { opacity: 0; transform: translateY(20px); transition: all 0.8s cubic-bezier(0.2, 0.8, 0.2, 1); }
    .reveal.active { opacity: 1; transform: translateY(0); }

</style>

<!-- CONNECT HEADER -->
<!-- <section class="pt-44 pb-16 relative overflow-hidden bg-white border-b border-slate-100">
    <div class="absolute inset-0 blueprint-grid opacity-20 pointer-events-none"></div>
    <div class="container mx-auto px-6 relative z-10">
        <div class="max-w-4xl">
            <span class="text-[8px] font-black uppercase tracking-[0.5em] text-slate-400 block border-l-2 border-slate-400 pl-4 mb-6">Marketplace / 2026</span>
            <h1 class="font-druk text-5xl md:text-7xl lg:text-8xl text-slate-900 leading-none mb-10">
                Material <br><span class="text-slate-400 italic">Connect</span>
            </h1>
            <p class="max-w-2xl text-slate-500 text-xs md:text-sm font-bold uppercase tracking-widest leading-loose text-justify">
                Direct access to high-performance materials and specialized vendors vetted by the Erode Architect Association. Navigate regional pricing, technical specs, and verified procurement channels.
            </p>
        </div>
    </div>
</section> -->

<!-- FILTERING & SEARCH -->
<section class="py-10 bg-slate-50 border-b border-slate-100 sticky top-[80px] z-40">
    <div class="container mx-auto px-6">
        <form method="get" class="grid grid-cols-1 lg:grid-cols-5 gap-4 items-end">
            <div>
                <label class="tech-label">Category</label>
                <select name="category" class="w-full bg-white border border-slate-200 eaa-radius px-4 py-3 text-[10px] font-bold uppercase tracking-widest outline-none focus:border-slate-900 transition-all">
                    <option value="">All Materials</option>
                    <?php foreach ($categoryOptions as $category): ?>
                        <option value="<?= e($category) ?>" <?= $filters['category'] === $category ? 'selected' : '' ?>><?= e($category) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="tech-label">Location</label>
                <select name="location" class="w-full bg-white border border-slate-200 eaa-radius px-4 py-3 text-[10px] font-bold uppercase tracking-widest outline-none focus:border-slate-900 transition-all">
                    <option value="">All Locations</option>
                    <?php foreach ($locationOptions as $location): ?>
                        <option value="<?= e($location) ?>" <?= $filters['location'] === $location ? 'selected' : '' ?>><?= e($location) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="tech-label">Min Price</label>
                <input type="number" step="0.01" name="min_price" value="<?= e((string) $filters['min_price']) ?>" placeholder="Min" class="w-full bg-white border border-slate-200 eaa-radius px-4 py-3 text-[10px] font-bold uppercase tracking-widest outline-none focus:border-slate-900 transition-all">
            </div>
            <div>
                <label class="tech-label">Max Price</label>
                <input type="number" step="0.01" name="max_price" value="<?= e((string) $filters['max_price']) ?>" placeholder="Max" class="w-full bg-white border border-slate-200 eaa-radius px-4 py-3 text-[10px] font-bold uppercase tracking-widest outline-none focus:border-slate-900 transition-all">
            </div>
            <div class="relative">
                <label class="tech-label">Search</label>
                <input type="text" name="search" value="<?= e($filters['search']) ?>" placeholder="Search Materials..." class="w-full bg-white border border-slate-200 eaa-radius px-5 py-3 text-[10px] font-bold uppercase tracking-widest outline-none focus:border-slate-900 transition-all">
                <i class="fa-solid fa-magnifying-glass absolute right-5 top-[52px] -translate-y-1/2 text-slate-300 text-xs"></i>
            </div>
            <div class="lg:col-span-5 flex flex-wrap gap-3 pt-2">
                <button type="submit" class="px-6 py-2 bg-slate-900 text-white text-[9px] font-black uppercase tracking-widest eaa-radius">Apply Filters</button>
                <a href="<?= e(url('vendors.php')) ?>" class="px-6 py-2 border border-slate-200 text-slate-400 text-[9px] font-black uppercase tracking-widest eaa-radius">Reset</a>
            </div>
        </form>
    </div>
</section>

<!-- PRODUCT CATALOG GRID -->
<main class="py-24 bg-white relative overflow-hidden">
    <div class="container mx-auto px-6 relative z-10">
        <?php if (empty($products)): ?>
            <div class="bg-slate-50 border border-slate-100 eaa-radius p-8 text-center text-xs font-bold uppercase tracking-widest text-slate-400">
                No products match your filters right now.
            </div>
        <?php else: ?>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-8">
            <?php foreach($products as $index => $p): ?>
            <div class="product-card eaa-radius reveal" style="transition-delay: <?= ($index % 4) * 100 ?>ms;">
                <div class="product-image">
                    <img src="<?= e($p['image_url'] ?? '') ?>" alt="<?= e($p['name']) ?>" onerror="this.src='https://images.unsplash.com/photo-1486718448742-163732cd1544?w=800&q=80'">
                    <div class="inlay-label"><?= e($p['category']) ?></div>
                </div>
                
                <div class="p-8 flex flex-col flex-1">
                    <span class="tech-label">Ref: <?= e((string) $p['id']) ?></span>
                    <h3 class="font-bold text-sm text-slate-900 uppercase tracking-tight mb-2 flex-1"><?= e($p['name']) ?></h3>
                    <span class="text-[8px] font-bold uppercase tracking-widest text-slate-400"><?= e($p['location'] ?? 'Location on request') ?></span>
                    
                    <div class="mt-4 pt-4 border-t border-slate-50">
                        <span class="text-[7px] font-black text-slate-400 uppercase tracking-widest block mb-1">Supplied By</span>
                        <span class="text-[10px] font-black text-slate-900 uppercase italic"><?= e($p['company_name']) ?></span>
                    </div>

                    <div class="mt-8 flex items-center justify-between">
                        <div>
                            <span class="text-[7px] font-black text-slate-400 uppercase tracking-widest block">Unit Price</span>
                            <span class="text-base font-black text-slate-900">₹<?= e(number_format((float) $p['price'], 2)) ?> <small class="text-[8px]">/ <?= e($p['unit']) ?></small></span>
                        </div>
                        <?php if ($canViewContact): ?>
                            <button
                                onclick="openContactModal('<?= e(addslashes($p['company_name'])) ?>', '<?= e(addslashes($p['phone'])) ?>', '<?= e(addslashes($p['email'])) ?>')"
                                class="px-5 py-3 bg-slate-900 text-white text-[8px] font-black uppercase tracking-widest eaa-radius hover:bg-slate-700 transition-all shadow-lg shadow-slate-200">
                                Get Contact
                            </button>
                        <?php else: ?>
                            <span class="text-[8px] font-black uppercase tracking-widest text-slate-400">Members Only</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

    </div>
</main>

<!-- CONTACT MODAL -->
<div id="contactModal">
    <div class="modal-content shadow-2xl">
        <button onclick="closeContactModal()" class="absolute top-6 right-6 text-slate-300 hover:text-slate-900"><i class="fa-solid fa-xmark"></i></button>
        
        <span class="tech-label">Procurement details</span>
        <h2 id="modalVendorName" class="font-druk text-2xl text-slate-900 mt-4 mb-10">Vendor Name</h2>
        
        <div class="space-y-6">
            <div class="p-5 border border-slate-100 eaa-radius flex items-center gap-5 hover:border-slate-300 transition-all group">
                <div class="w-10 h-10 bg-slate-50 eaa-radius flex items-center justify-center text-slate-900 group-hover:bg-slate-900 group-hover:text-white transition-all"><i class="fa-solid fa-phone text-xs"></i></div>
                <div>
                    <span class="tech-label">Mobile Hotline</span>
                    <a id="modalPhone" href="#" class="text-xs font-black text-slate-900 tracking-widest">+91 00000 00000</a>
                </div>
            </div>

            <div class="p-5 border border-slate-100 eaa-radius flex items-center gap-5 hover:border-slate-300 transition-all group">
                <div class="w-10 h-10 bg-slate-50 eaa-radius flex items-center justify-center text-slate-900 group-hover:bg-slate-900 group-hover:text-white transition-all"><i class="fa-solid fa-envelope text-xs"></i></div>
                <div>
                    <span class="tech-label">Official Correspondence</span>
                    <a id="modalEmail" href="#" class="text-xs font-black text-slate-900 tracking-widest">EMAIL@VENDOR.COM</a>
                </div>
            </div>
        </div>

        <p class="text-[8px] font-bold text-slate-400 uppercase tracking-widest text-center mt-10">Please mention EAA Membership ID during inquiry.</p>
    </div>
</div>

<?php require_once __DIR__ . "/partials/footer.php"; ?>

<script>
    function openContactModal(name, phone, email) {
        document.getElementById('modalVendorName').innerText = name;
        document.getElementById('modalPhone').innerText = phone;
        document.getElementById('modalPhone').href = 'tel:' + phone.replace(/\s+/g, '');
        document.getElementById('modalEmail').innerText = email;
        document.getElementById('modalEmail').href = 'mailto:' + email;
        
        document.getElementById('contactModal').style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }

    function closeContactModal() {
        document.getElementById('contactModal').style.display = 'none';
        document.body.style.overflow = 'auto';
    }

    document.addEventListener('DOMContentLoaded', () => {
        const revealElements = document.querySelectorAll('.reveal');
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(e => { if (e.isIntersecting) e.target.classList.add('active'); });
        }, { threshold: 0.1 });
        revealElements.forEach(el => observer.observe(el));
    });

    // Close on outside click
    window.onclick = function(event) {
        if (event.target == document.getElementById('contactModal')) {
            closeContactModal();
        }
    }
</script>
