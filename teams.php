<?php


ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

/* =========================================================
   teams.php — ARCHITECTURAL DESIGN COUNCIL
   ✅ Updated: 1-Column grid for mobile
   ✅ Added: Auto-hover social icons on scroll (Mobile only)
   ✅ Categorized hierarchy (Principals, Seniors, Associates)
   ✅ Montserrat exclusive typography
   ✅ 5px Radius & Smoke Grey palette
   ========================================================= */

require_once __DIR__ . '/lib/helpers.php';
require_once __DIR__ . '/config/db.php';

start_session();

$stmt = db()->query(
    'SELECT team_members.title, team_members.category, team_members.photo_path, team_members.featured,
            users.full_name, users.email
     FROM team_members
     JOIN users ON users.id = team_members.user_id
     WHERE team_members.visible = 1 AND team_members.approval_status = "approved"
     ORDER BY team_members.featured DESC, team_members.category ASC, users.full_name ASC'
);
$members = $stmt->fetchAll();

$pageTitle = 'Design Council | Erode Architect Association';
require_once __DIR__ . "/partials/header.php";
?>

<style>
    :root {
        --eaa-smoke: #475569;
        --eaa-border: #e2e8f0;
        --eaa-radius: 5px;
    }

    body {
        background-color: #f8fafc;
        color: #1e293b;
        transition: background-color 0.3s ease;
    }

    .font-druk {
        font-family: 'Montserrat', sans-serif;
        font-weight: 900;
        text-transform: uppercase;
        letter-spacing: -0.05em;
        line-height: 0.85;
    }

    .eaa-radius { border-radius: var(--eaa-radius) !important; }

    .blueprint-grid {
        background-image: linear-gradient(rgba(71, 85, 105, 0.05) 1px, transparent 1px),
                          linear-gradient(90deg, rgba(71, 85, 105, 0.05) 1px, transparent 1px);
        background-size: 40px 40px;
    }

    /* Team Card Styling */
    .team-card {
        background: #ffffff;
        border: 1px solid var(--eaa-border);
        transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
        position: relative;
        overflow: hidden;
    }

    .team-card:hover {
        border-color: var(--eaa-smoke);
        transform: translateY(-5px);
        box-shadow: 0 15px 35px rgba(71, 85, 105, 0.1);
    }

    .team-image-box {
        aspect-ratio: 3 / 4;
        width: 100%;
        overflow: hidden;
        background: #f1f5f9;
        position: relative;
    }

    .team-image-box img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        transition: transform 0.6s ease;
    }

    .team-card:hover .team-image-box img {
        transform: scale(1.08);
    }

    /* Social Icons Overlay */
    .team-overlay {
        position: absolute;
        top: 15px;
        right: 15px;
        display: flex;
        flex-direction: column;
        gap: 10px;
        opacity: 0;
        transform: translateX(15px);
        transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
        z-index: 30;
    }

    /* Desktop Hover Trigger */
    @media (min-width: 1024px) {
        .team-card:hover .team-overlay {
            opacity: 1;
            transform: translateX(0);
        }
    }

    /* Mobile Auto-Hover Trigger (Based on Intersection) */
    @media (max-width: 1023px) {
        .team-card.auto-hover .team-overlay {
            opacity: 1;
            transform: translateX(0);
        }
    }

    .social-icon-btn {
        width: 36px;
        height: 36px;
        background: #ffffff;
        color: #1e293b;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 4px;
        font-size: 14px;
        transition: all 0.2s ease;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        border: 1px solid var(--eaa-border);
    }

    .social-icon-btn:hover {
        background: var(--eaa-smoke);
        color: #ffffff;
        transform: scale(1.1);
    }

    .team-info {
        padding: 24px;
    }

    .team-role {
        font-size: 8px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.15em;
        color: #94a3b8;
        margin-bottom: 8px;
        display: block;
    }

    .team-name {
        font-family: 'Montserrat', sans-serif;
        font-weight: 800;
        font-size: 1rem;
        text-transform: uppercase;
        color: #1e293b;
        line-height: 1.2;
    }

    .card-label { 
        position: absolute; 
        background: #1e293b; 
        color: white; 
        padding: 3px 10px; 
        font-size: 7px; 
        font-weight: 800; 
        text-transform: uppercase; 
        z-index: 10; 
        border-radius: 2px; 
    }
    .label-tl { top: 15px; left: 15px; }

    .filter-btn {
        font-size: 10px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.1em;
        color: #94a3b8;
        padding: 8px 16px;
        border-bottom: 2px solid transparent;
        transition: all 0.3s ease;
    }

    .filter-btn.active, .filter-btn:hover {
        color: #1e293b;
        border-bottom-color: #1e293b;
    }

    /* Animation Reveal */
    .reveal { opacity: 0; transform: translateY(20px); transition: all 0.8s cubic-bezier(0.2, 0.8, 0.2, 1); }
    .reveal.active { opacity: 1; transform: translateY(0); }

</style>

<!-- HERO / HEADER SECTION -->
<section class="pt-44 pb-16 relative overflow-hidden bg-white border-b border-slate-100">
    <div class="absolute inset-0 blueprint-grid opacity-20 pointer-events-none"></div>
    <div class="container mx-auto px-6 relative z-10">
        <div class="flex flex-col md:flex-row md:items-end justify-between gap-10">
            <div class="max-w-2xl reveal">
                <span class="text-[8px] font-black uppercase tracking-[0.5em] text-slate-400 block border-l-2 border-slate-400 pl-4 mb-6">Council / 2026</span>
                <h1 class="font-druk text-5xl md:text-8xl text-slate-900 leading-none">
                    Design <br><span class="text-slate-400 italic">Council</span>
                </h1>
            </div>
            <div class="max-w-xs reveal" style="transition-delay: 200ms;">
                <p class="text-[10px] font-bold uppercase tracking-widest text-slate-500 leading-loose text-justify">
                    A collective of 20 specialized practitioners dedicated to regional excellence, urban sustainability, and the professional growth of the Erode architecture guild.
                </p>
            </div>
        </div>
    </div>
</section>

<!-- FILTERING SECTION -->
<section class="py-12 bg-slate-50 border-b border-slate-100">
    <div class="container mx-auto px-6 flex flex-wrap items-center justify-between gap-8">
        <div class="flex gap-4">
            <button class="filter-btn active">All Members</button>
            <button class="filter-btn">Principals</button>
            <button class="filter-btn">Seniors</button>
            <button class="filter-btn">Associates</button>
        </div>
        <div class="hidden lg:flex items-center gap-4 opacity-30">
            <span class="text-[8px] font-black uppercase tracking-widest">Scale: 1:20_Council</span>
            <div class="w-32 h-px bg-slate-900"></div>
        </div>
    </div>
</section>

<!-- MAIN TEAM GRID (Changed mobile to 1-column) -->
<section class="py-24 bg-white relative overflow-hidden">
    <div class="container mx-auto px-6 relative z-10">
        <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-8 lg:gap-6">
            <?php if (empty($members)): ?>
                <div class="col-span-full border border-dashed border-slate-200 eaa-radius p-10 text-center text-slate-400 uppercase tracking-widest text-[9px] font-black">
                    Council profiles are being curated. Check back soon.
                </div>
            <?php endif; ?>
            <?php foreach($members as $index => $m): ?>
                <?php
                $photoUrl = $m['photo_path'] ? asset($m['photo_path']) : 'https://via.placeholder.com/600x800?text=EAA';
                ?>
            <div class="team-card eaa-radius reveal" style="transition-delay: <?= ($index % 5) * 100 ?>ms;">
                <div class="team-image-box">
                    <img src="<?= e($photoUrl) ?>" alt="<?= e($m['full_name']) ?>">
                    
                    <div class="team-overlay">
                        <a href="mailto:<?= e($m['email']) ?>" class="social-icon-btn" title="Email">
                            <i class="fa-regular fa-envelope"></i>
                        </a>
                    </div>

                    <div class="card-label label-tl"><?= e($m['category']) ?></div>
                    <?php if (!empty($m['featured'])): ?>
                        <div class="card-label" style="bottom: 15px; left: 15px; background: #64748b;">Featured</div>
                    <?php endif; ?>
                </div>
                <div class="team-info">
                    <span class="team-role"><?= e($m['title']) ?></span>
                    <h3 class="team-name"><?= e($m['full_name']) ?></h3>
                    <div class="mt-6 flex items-center gap-3 opacity-10">
                        <div class="h-px flex-1 bg-slate-900"></div>
                        <span class="text-[6px] font-black uppercase">EAA-REF-0<?= sprintf('%02d', $index + 1) ?></span>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- JOIN CALLOUT -->
<section class="py-24 bg-slate-900 text-white relative overflow-hidden">
    <div class="absolute inset-0 blueprint-grid opacity-10 pointer-events-none"></div>
    <div class="container mx-auto px-6 text-center relative z-10">
        <div class="reveal">
            <h2 class="font-druk text-4xl md:text-6xl mb-8">Shape the <span class="text-slate-500 italic">Future</span></h2>
            <p class="text-[10px] font-black uppercase tracking-[0.3em] text-slate-400 mb-12 max-w-lg mx-auto leading-loose">
                Are you a licensed architect practicing in the Erode region? Join our council to advocate for better design standards.
            </p>
            <a href="contact.php" class="inline-block px-12 py-5 bg-white text-slate-900 text-[10px] font-black uppercase tracking-widest eaa-radius hover:bg-slate-200 transition-all">Apply for Membership</a>
        </div>
    </div>
</section>

<?php require_once __DIR__ . "/partials/footer.php"; ?>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const revealElements = document.querySelectorAll('.reveal');
        const teamCards = document.querySelectorAll('.team-card');
        
        // REVEAL OBSERVER (Existing)
        const revealObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('active');
                }
            });
        }, { threshold: 0.1 });

        revealElements.forEach(el => revealObserver.observe(el));

        // MOBILE AUTO-HOVER OBSERVER
        // Triggers the social icons when the card is in the vertical center of the mobile screen
        const hoverObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (window.innerWidth < 1024) { // Only for mobile/tablet
                    if (entry.isIntersecting) {
                        entry.target.classList.add('auto-hover');
                    } else {
                        entry.target.classList.remove('auto-hover');
                    }
                }
            });
        }, {
            threshold: 0.6, // Trigger when 60% of the card is visible
            rootMargin: "-10% 0px -10% 0px" // Focus trigger area
        });

        teamCards.forEach(card => hoverObserver.observe(card));
    });
</script>
