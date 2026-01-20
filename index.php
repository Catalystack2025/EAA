<?php
/* =========================================================
   INDEX.PHP — READY TO PASTE (SMOKE GREY THEME)
   - Keeps your smoke-grey HSL theme tokens
   - Removes dark theme button
   - Login + Join Us aligned to the far right
   - Hero images brighter + clearer (brightness/contrast + higher opacity)
   - Team Members, Benefits, Projects sections in the upgraded style
   - Sponsor marquee + Footer preserved from your earlier smoke-theme version
   ========================================================= */

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/lib/helpers.php';
require_once __DIR__ . '/config/db.php';

start_session();

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

/* ---------- STATIC DATA (edit here) ---------- */
$members = [
  ['name' => 'Ar. Suresh Kumar', 'role' => 'Senior Architect', 'photo' => 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=600&q=80'],
  ['name' => 'Ar. Priya Sharma', 'role' => 'Principal Designer', 'photo' => 'https://images.unsplash.com/photo-1494790108377-be9c29b29330?w=600&q=80'],
  ['name' => 'Ar. Rajesh M.', 'role' => 'Design Lead', 'photo' => 'https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?w=600&q=80'],
  ['name' => 'Ar. Lakshmi N.', 'role' => 'Urban Planner', 'photo' => 'https://images.unsplash.com/photo-1438761681033-6461ffad8d80?w=600&q=80'],
];

$benefits = [
  ['title' => 'Professional Network', 'desc' => 'Build meaningful partnerships with 500+ architects.', 'icon' => 'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197'],
  ['title' => 'Recognition', 'desc' => 'Showcase standout projects through annual awards.', 'icon' => 'M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12'],
  ['title' => 'Learning', 'desc' => 'Access certification-led learning year-round.', 'icon' => 'M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253'],
  ['title' => 'Opportunities', 'desc' => 'Collaborate on civic and institutional initiatives.', 'icon' => 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3'],
  ['title' => 'Advocacy', 'desc' => 'Stay represented in policy discussions.', 'icon' => 'M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3'],
  ['title' => 'Innovation', 'desc' => 'Explore smart systems and emerging materials.', 'icon' => 'M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707'],
];

$projects = [
  ['title' => 'Waterfront Resort', 'budget' => '$120K - $560K', 'size' => '1200 sqft', 'year' => '2023', 'location' => 'Erode, TN', 'image' => 'https://images.unsplash.com/photo-1600596542815-ffad4c1539a9?w=1200&q=80', 'desc' => 'A waterfront resort concept blending modern architecture with natural surroundings.'],
  ['title' => 'Modern Dwelling', 'budget' => '$100K - $600K', 'size' => '1000 sqft', 'year' => '2022', 'location' => 'Erode, TN', 'image' => 'https://images.unsplash.com/photo-1600585154340-be6161a56a0c?w=1200&q=80', 'desc' => 'A contemporary residence designed for light, ventilation, and efficient space planning.'],
  ['title' => 'Urban Loft', 'budget' => '$150K - $400K', 'size' => '1500 sqft', 'year' => '2021', 'location' => 'Chennai, TN', 'image' => 'https://images.unsplash.com/photo-1600607687939-ce8a6c25118c?w=1200&q=80', 'desc' => 'Urban loft living with clean geometry and a warm material palette.'],
  ['title' => 'Sustainable Villa', 'budget' => '$200K - $800K', 'size' => '3000 sqft', 'year' => '2024', 'location' => 'Ooty, TN', 'image' => 'https://images.unsplash.com/photo-1600566753190-17f0baa2a6c3?w=1200&q=80', 'desc' => 'A sustainable hillside villa designed for passive cooling and panoramic views.'],
];

$showContact = can_view_vendor_contact($_SESSION['role'] ?? null);
$sponsors = [];
if (table_exists('sponsor_requests')) {
  $sponsorStmt = db()->prepare(
    'SELECT sr.company_name,
            sr.logo_path,
            sr.website,
            COALESCE(sr.phone, vp.phone) AS phone,
            u.email
     FROM sponsor_requests sr
     JOIN users u ON u.id = sr.vendor_user_id
     LEFT JOIN vendor_profile vp ON vp.user_id = u.id
     WHERE sr.status = :status
     ORDER BY sr.updated_at DESC'
  );
  $sponsorStmt->execute(['status' => 'approved']);
  $sponsors = $sponsorStmt->fetchAll();
}
$marquee_list = $sponsors ? array_merge($sponsors, $sponsors, $sponsors) : [];
?>
<!DOCTYPE html>
<html lang="en" class="light">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Erode Architect Association | Designing the Skyline</title>

  <!-- Google Fonts -->
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;700;900&family=Montserrat:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">

  <!-- Tailwind CDN -->
  <script src="https://cdn.tailwindcss.com"></script>

  <script>
    tailwind.config = {
      darkMode: 'class',
      theme: {
        extend: {
          fontFamily: {
            sans: ['Montserrat', 'sans-serif'],
            body: ['Montserrat', 'sans-serif'],
            display: ['Montserrat', 'sans-serif'],
          },
          colors: {
            background: 'hsl(var(--background))',
            foreground: 'hsl(var(--foreground))',
            primary: 'hsl(var(--primary))',
            card: 'hsl(var(--card))',
            border: 'hsl(var(--border))',
            charcoal: 'hsl(var(--charcoal))',
            cream: 'hsl(var(--cream))',
          }
        }
      }
    }
  </script>

  <style>
    :root {
      /* Smoke Light Theme Variables */
      --background: 210 25% 98%;
      --foreground: 220 20% 18%;
      --primary: 197 13% 43%;
      --card: 210 20% 96%;
      --border: 210 12% 86%;
      --charcoal: 220 20% 12%;
      --cream: 210 25% 98%;
      --cream-dark: 210 20% 94%;
      --blueprint: 198 12% 48%;
    }

    body{
      background-color: hsl(var(--background));
      color: hsl(var(--foreground));
      transition: background-color .3s ease, color .3s ease;
    }

    /* Druk Style Headings */
    .font-druk{
      font-family: 'Inter', sans-serif;
      font-weight: 900;
      text-transform: uppercase;
      letter-spacing: -0.05em;
      line-height: 0.85;
    }

    /* Blueprint & Grids */
    .blueprint-grid{
      background-image:
        linear-gradient(hsl(var(--blueprint) / 0.10) 1px, transparent 1px),
        linear-gradient(90deg, hsl(var(--blueprint) / 0.10) 1px, transparent 1px);
      background-size: 40px 40px;
    }
    .dot-grid{
      background-image: radial-gradient(hsl(var(--charcoal) / 0.10) 1px, transparent 1px);
      background-size: 24px 24px;
    }

    /* Navbar transitions */
    #main-header{ transition: all .4s cubic-bezier(0.4,0,0.2,1); }
    #main-header.scrolled{
      background-color: hsl(var(--background) / 0.92);
      backdrop-filter: blur(12px);
      box-shadow: 0 10px 30px rgba(2,6,23,0.06);
      padding: .55rem 0;
      border-bottom: 1px solid hsl(var(--border));
    }
    #sticky-logo-container{ transition: all .5s cubic-bezier(0.175,0.885,0.32,1.275); }

    /* Animation */
    @keyframes drawLine { from { stroke-dashoffset: 200; } to { stroke-dashoffset: 0; } }
    @keyframes marquee { 0% { transform: translateX(0); } 100% { transform: translateX(-50%); } }

    .reveal{ opacity:0; transform: translateY(26px); transition: all .8s ease-out; }
    .reveal.active{ opacity:1; transform: translateY(0); }

    .skyline-path{ stroke-dasharray: 200; stroke-dashoffset: 200; animation: drawLine 1.5s ease-out forwards 1.2s; }
    .scrollbar-hide::-webkit-scrollbar{ display:none; }
    .scrollbar-hide{ -ms-overflow-style:none; scrollbar-width:none; }

    /* HERO image clarity boost */
    .hero-img{
      filter:  blur(2px);
    }

    /* Preloader */
    #preloader{
      position: fixed; inset: 0; z-index: 9999;
      display:flex; align-items:center; justify-content:center;
      background: hsl(var(--charcoal));
      color: white;
      transition: opacity .45s ease, visibility .45s ease;
    }
    #preloader.hide{
      opacity: 0; visibility: hidden; pointer-events:none;
    }
    .loader-container{ display:flex; align-items:center; gap:32px; }
    .eaa-brand{
      font-family: 'Inter', sans-serif;
      font-size: 5.2rem;
      font-weight: 900;
      letter-spacing: -2px;
      line-height: 0.85;
      opacity: 0;
      filter: blur(10px);
      transform: rotateX(-90deg) translateZ(50px);
      animation: flipIn 1.15s cubic-bezier(0.19, 1, 0.22, 1) forwards;
    }
    .info-stack{ display:flex; flex-direction:column; gap:4px; }
    .text-item{
      text-transform: uppercase;
      white-space: nowrap;
      opacity: 0;
      filter: blur(8px);
      transform: translateY(20px) rotateX(-20deg);
    }
    .erode{
      font-size: 2.0rem; font-weight: 800; letter-spacing: .16em;
      animation: structuralReveal .85s cubic-bezier(0.19, 1, 0.22, 1) .55s forwards;
    }
    .architect,.association{
      font-size: 1.05rem; font-weight: 400; letter-spacing: .28em;
      color: rgba(255,255,255,.62);
    }
    .architect{ animation: structuralReveal .85s cubic-bezier(0.19, 1, 0.22, 1) .75s forwards; }
    .association{ animation: structuralReveal .85s cubic-bezier(0.19, 1, 0.22, 1) .95s forwards; }

    @keyframes flipIn{
      0%{ opacity:0; filter: blur(15px); transform: rotateX(-90deg) translateZ(50px); }
      100%{ opacity:1; filter: blur(0); transform: rotateX(0) translateZ(0); }
    }
    @keyframes structuralReveal{
      0%{ opacity:0; filter: blur(10px); transform: translateY(20px) rotateX(-20deg); }
      100%{ opacity:1; filter: blur(0); transform: translateY(0) rotateX(0); }
    }
  </style>
</head>

<body class="antialiased font-body">

  <!-- PRELOADER (AUTO HIDES ON LOAD) -->
  <div id="preloader">
    <div class="loader-container">
      <div class="eaa-brand">EAA</div>
      <div class="info-stack">
        <div class="text-item erode">Erode</div>
        <div class="text-item architect">Architect</div>
        <div class="text-item association">Association</div>
      </div>
    </div>
  </div>

  <!-- NAVBAR -->
  <header id="main-header" class="fixed top-0 left-0 right-0 z-50 py-6 bg-transparent">
    <nav class="container mx-auto px-6">
      <div class="hidden lg:flex items-center justify-between">
        <!-- Left nav -->
        <div id="left-nav" class="flex items-center gap-8 transition-transform duration-500">
          <a href="index.php" class="text-xs font-bold tracking-widest uppercase text-slate-400 hover:text-primary">Home</a>
          <a href="about.php" class="text-xs font-bold tracking-widest uppercase text-slate-400 hover:text-primary">About</a>
          <a href="teams.php" class="text-xs font-bold tracking-widest uppercase text-slate-400 hover:text-primary">Teams</a>
          <a href="calendar.php" class="text-xs font-bold tracking-widest uppercase text-slate-400 hover:text-primary">Events</a>
          <a href="contact.php" class="text-xs font-bold tracking-widest uppercase text-slate-400 hover:text-primary">Contact</a>
        </div>

        <!-- Right actions (FULL RIGHT) -->
        <div class="flex items-center gap-3">
          <a href="/auth/login.php" class="px-5 py-2 text-xs font-bold border border-border rounded-xl hover:bg-primary hover:text-white transition-all uppercase tracking-widest">
            Login
          </a>
          <a href="/auth/register.php" class="bg-primary text-white px-6 py-2.5 rounded-xl text-xs font-black uppercase tracking-widest hover:scale-105 transition-all shadow-lg shadow-primary/20">
            Join Us
          </a>
        </div>
      </div>

      <!-- Mobile -->
      <div class="flex items-center justify-between lg:hidden text-foreground">
        <a href="index.php" class="flex items-center gap-3">
          <img src="logo.svg" alt="EAA" class="h-8 w-auto">
          <span class="text-xs font-black tracking-widest uppercase text-foreground/80">EAA</span>
        </a>
        <button class="p-2 border border-border rounded-lg hover:bg-primary/5 transition-all" aria-label="menu">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M4 6h16M4 12h16m-7 6h7" stroke-width="2"/></svg>
        </button>
      </div>
    </nav>
  </header>

  <!-- HERO SECTION -->
  <section class="relative min-h-screen flex items-center justify-center overflow-hidden">
    <!-- Background Slideshow -->
    <div class="absolute inset-0 z-0">
      <div id="hero-slides">
        <img src="https://images.unsplash.com/photo-1486718448742-163732cd1544?w=1920&q=80" class="hero-img absolute inset-0 w-full h-full object-cover opacity-55 slide-img transition-opacity duration-1000">
        <img src="https://images.unsplash.com/photo-1487958449943-2429e8be8625?w=1920&q=80" class="hero-img absolute inset-0 w-full h-full object-cover opacity-0 slide-img transition-opacity duration-1000">
        <img src="https://images.unsplash.com/photo-1518005020951-eccb494ad742?w=1920&q=80" class="hero-img absolute inset-0 w-full h-full object-cover opacity-0 slide-img transition-opacity duration-1000">
      </div>

      <!-- Lighter overlay for clarity -->
      <!-- <div class="absolute inset-0 bg-gradient-to-b from-background/35 via-background/15 to-background"></div> -->
    </div>

    <!-- Blueprint grid -->
    <div class="absolute inset-0 blueprint-grid opacity-30 pointer-events-none"></div>

    <!-- Decorative corners -->
    <div class="absolute inset-0 overflow-hidden pointer-events-none">
      <div class="absolute top-32 left-10 w-24 h-24 border-l-2 border-t-2 border-primary/20"></div>
      <div class="absolute top-32 right-10 w-24 h-24 border-r-2 border-t-2 border-primary/20"></div>
      <div class="absolute bottom-10 left-10 w-24 h-24 border-l-2 border-b-2 border-primary/20"></div>
      <div class="absolute bottom-10 right-10 w-24 h-24 border-r-2 border-b-2 border-primary/20"></div>
    </div>

    <div class="container mx-auto px-6 relative z-10 text-center">
      <div class="reveal">
        <img src="public/EAA_logo.png" class="h-20 mx-auto mb-8 drop-shadow-2xl" alt="EAA Logo" >
        <span class="inline-block px-6 py-2 border border-border text-[10px] font-black uppercase tracking-[0.4em] text-primary rounded-full bg-primary/5 backdrop-blur-sm mb-10">
          Established 1985 • Serving Erode
        </span>
      </div>

      <h1 class="font-druk text-6xl md:text-8xl lg:text-9xl mb-12 text-foreground reveal" style="transition-delay:200ms;">
        Designing the<br>
        <span class="relative inline-block text-primary">
          Skyline
          <svg class="absolute -bottom-4 left-0 w-full h-4" viewBox="0 0 200 10">
            <path class="skyline-path" d="M0 5 Q 50 0, 100 5 T 200 5" fill="none" stroke="hsl(var(--primary))" stroke-width="3"/>
          </svg>
        </span>
        of Erode
      </h1>

      <p class="max-w-xl mx-auto text-slate-500 text-sm md:text-base font-medium tracking-wide mb-14 reveal" style="transition-delay:400ms;">
        A collaborative network of architects advancing sustainable, context-driven design across Tamil Nadu.
      </p>

      <!-- Slide Indicators -->
      <div class="flex justify-center gap-3 mb-16">
        <button onclick="setSlide(0)" class="w-2 h-2 rounded-full bg-primary dot-indicator transition-all duration-300 w-8" aria-label="slide 1"></button>
        <button onclick="setSlide(1)" class="w-2 h-2 rounded-full bg-slate-300 dot-indicator transition-all duration-300" aria-label="slide 2"></button>
        <button onclick="setSlide(2)" class="w-2 h-2 rounded-full bg-slate-300 dot-indicator transition-all duration-300" aria-label="slide 3"></button>
      </div>

      <!-- Stats -->
      <div class="grid grid-cols-2 md:grid-cols-4 gap-8 max-w-4xl mx-auto pt-16 border-t border-border reveal" style="transition-delay:600ms;">
        <div><div class="text-4xl font-black mb-1">500+</div><div class="text-[9px] font-black uppercase tracking-widest text-slate-400">Members</div></div>
        <div><div class="text-4xl font-black mb-1">1000+</div><div class="text-[9px] font-black uppercase tracking-widest text-slate-400">Projects</div></div>
        <div><div class="text-4xl font-black mb-1">38</div><div class="text-[9px] font-black uppercase tracking-widest text-slate-400">Years</div></div>
        <div><div class="text-4xl font-black mb-1">50+</div><div class="text-[9px] font-black uppercase tracking-widest text-slate-400">Teams</div></div>
      </div>
    </div>
  </section>

  <!-- MEMBERS SECTION (UPDATED STYLE + SAME THEME) -->
  <section class="py-24 relative overflow-hidden bg-card/30 border-y border-border">
    <div class="absolute inset-0 dot-grid pointer-events-none opacity-35"></div>
    <div class="container mx-auto px-6 relative z-10 text-center">
      <div class="mb-16 reveal">
        <span class="inline-block px-4 py-2 bg-primary/10 text-primary text-[10px] font-black uppercase tracking-[0.2em] mb-4 rounded">Our Community</span>
        <h2 class="font-druk text-4xl md:text-6xl mb-4 text-foreground">Team Members</h2>
        <p class="text-slate-400 text-[10px] font-black uppercase tracking-widest">Home / Team Members</p>
      </div>

      <div class="grid grid-cols-2 md:grid-cols-4 gap-8">
        <?php foreach ($members as $index => $m): ?>
          <div class="reveal group relative" style="transition-delay: <?= $index * 100 ?>ms;">
            <div class="relative aspect-[3/4] overflow-hidden bg-background rounded-2xl shadow-xl border border-border hover:border-primary/40 transition-all duration-500">
              <img src="<?= htmlspecialchars($m['photo']) ?>" class="w-full h-full object-cover filter grayscale group-hover:grayscale-0 transition-all duration-700 group-hover:scale-110" alt="<?= htmlspecialchars($m['name']) ?>">
              <div class="absolute inset-0 bg-gradient-to-t from-charcoal/80 via-transparent to-transparent opacity-55"></div>

              <div class="absolute bottom-0 left-0 right-0 p-6 text-left">
                <h4 class="font-druk text-lg mb-1 text-white group-hover:text-primary transition-colors"><?= htmlspecialchars($m['name']) ?></h4>
                <p class="text-[9px] font-black uppercase tracking-widest text-primary"><?= htmlspecialchars($m['role']) ?></p>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>

      <div class="text-center mt-16 reveal">
        <a href="teams.php" class="px-8 py-3 border border-border text-[10px] font-black uppercase tracking-widest text-foreground hover:bg-primary/5 transition-all rounded-full">
          View More
        </a>
      </div>
    </div>
  </section>

  <!-- WHY JOIN US (UPDATED STYLE + SAME THEME) -->
  <section class="py-24 relative overflow-hidden bg-background border-b border-border">
    <div class="container mx-auto px-6 relative z-10">
      <div class="text-center mb-20 reveal">
        <span class="inline-block px-4 py-2 bg-primary/10 text-primary text-[10px] font-black uppercase tracking-[0.2em] mb-4 rounded">Benefits</span>
        <h2 class="font-druk text-4xl md:text-6xl mb-4">Why Join Our Association?</h2>
        <p class="text-slate-500 text-[10px] font-black uppercase tracking-widest max-w-2xl mx-auto leading-loose">
          Supporting better buildings, stronger practices, and a shared regional vision for Erode.
        </p>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
        <?php foreach ($benefits as $index => $b): ?>
          <div class="reveal p-10 bg-card/50 border border-border rounded-3xl hover:border-primary/50 transition-all duration-500 group relative overflow-hidden"
               style="transition-delay: <?= $index * 100 ?>ms;">
            <div class="absolute top-0 right-0 w-12 h-12 border-t border-r border-border group-hover:border-primary transition-colors"></div>

            <div class="w-14 h-14 bg-primary/10 rounded-2xl flex items-center justify-center text-primary mb-8 group-hover:rotate-12 transition-all">
              <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path d="<?= $b['icon'] ?>" stroke-width="2" stroke-linecap="round"/>
              </svg>
            </div>

            <h3 class="font-druk text-xl mb-4 group-hover:text-primary transition-colors uppercase"><?= htmlspecialchars($b['title']) ?></h3>
            <p class="text-[11px] font-semibold text-slate-500 leading-relaxed"><?= htmlspecialchars($b['desc']) ?></p>

            <div class="absolute bottom-0 left-0 w-0 h-1 bg-primary group-hover:w-full transition-all duration-700"></div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <!-- FEATURED PROJECTS (UPDATED STYLE + SAME THEME) -->
  <section class="py-24 relative overflow-hidden bg-card/10">
    <div class="absolute inset-0 blueprint-grid pointer-events-none opacity-30"></div>
    <div class="container mx-auto px-6 relative z-10">
      <div class="flex flex-col md:flex-row items-end justify-between mb-16 reveal">
        <div>
          <span class="inline-block px-4 py-2 bg-primary/10 text-primary text-[10px] font-black uppercase tracking-[0.2em] mb-4 rounded">Portfolio</span>
          <h2 class="font-druk text-4xl md:text-6xl text-foreground">Featured Projects</h2>
        </div>

        <div class="hidden md:flex gap-4 mt-8 md:mt-0">
          <button onclick="scrollProj('left')" class="w-12 h-12 border border-border rounded-full flex items-center justify-center hover:bg-primary hover:text-white transition-all" aria-label="left">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M15 19l-7-7 7-7" stroke-width="2" stroke-linecap="round"/></svg>
          </button>
          <button onclick="scrollProj('right')" class="w-12 h-12 border border-border rounded-full flex items-center justify-center hover:bg-primary hover:text-white transition-all" aria-label="right">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M9 5l7 7-7 7" stroke-width="2" stroke-linecap="round"/></svg>
          </button>
        </div>
      </div>

      <div id="proj-carousel" class="flex gap-8 overflow-x-auto snap-x snap-mandatory scrollbar-hide pb-8">
        <?php foreach ($projects as $p): ?>
          <div class="flex-shrink-0 w-[80vw] md:w-[40vw] lg:w-[30vw] snap-start reveal">
            <div onclick='openProjModal(<?= htmlspecialchars(json_encode($p), ENT_QUOTES, "UTF-8") ?>)'
                 class="group cursor-pointer bg-background border border-border rounded-3xl overflow-hidden hover:shadow-2xl transition-all duration-500">
              <div class="relative aspect-[4/3] overflow-hidden">
                <img src="<?= htmlspecialchars($p['image']) ?>" class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-700" alt="<?= htmlspecialchars($p['title']) ?>">
                <div class="absolute inset-0 bg-gradient-to-t from-charcoal/55 via-transparent to-transparent opacity-0 group-hover:opacity-100 transition-opacity"></div>
              </div>

              <div class="p-8 text-left">
                <h3 class="font-druk text-xl mb-4 group-hover:text-primary transition-colors"><?= htmlspecialchars($p['title']) ?></h3>
                <div class="text-[9px] font-black uppercase tracking-widest text-slate-400 space-y-2">
                  <div class="flex justify-between border-b border-border pb-2">
                    <span>Budget</span><span class="text-foreground"><?= htmlspecialchars($p['budget']) ?></span>
                  </div>
                  <div class="flex justify-between">
                    <span>Location</span><span class="text-foreground"><?= htmlspecialchars($p['location']) ?></span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <!-- SPONSORS MARQUEE (UNCHANGED STYLE FROM YOUR EARLIER THEME) -->
  <section class="py-16 bg-charcoal overflow-hidden border-y border-white/5">
    <div class="animate-marquee flex whitespace-nowrap" style="display:flex; width:max-content; animation: marquee 30s linear infinite;">
      <?php if (empty($marquee_list)): ?>
        <div class="flex items-center px-16 gap-6">
          <span class="text-[10px] font-black text-white/30 uppercase tracking-[0.3em]">Sponsors coming soon</span>
        </div>
      <?php else: ?>
        <?php foreach ($marquee_list as $sponsor): ?>
          <div class="flex items-center px-16 gap-6 group cursor-pointer">
            <div class="w-12 h-12 border border-white/10 rotate-45 flex items-center justify-center transition-colors group-hover:border-primary">
              <img src="<?= e(asset($sponsor['logo_path'])) ?>" alt="<?= e($sponsor['company_name']) ?>" class="h-7 w-7 object-contain -rotate-45" onerror="this.style.display='none'">
            </div>
            <div class="flex flex-col">
              <span class="text-[10px] font-black text-white/30 uppercase tracking-[0.3em] group-hover:text-white transition-colors"><?= e($sponsor['company_name']) ?></span>
              <?php if ($showContact && (!empty($sponsor['phone']) || !empty($sponsor['email']))): ?>
                <span class="text-[8px] font-bold text-white/20 uppercase tracking-[0.2em]">
                  <?= e($sponsor['phone']) ?><?= (!empty($sponsor['phone']) && !empty($sponsor['email'])) ? ' · ' : '' ?><?= e($sponsor['email']) ?>
                </span>
              <?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </section>

  <!-- FOOTER (UNCHANGED) -->
  <footer class="bg-charcoal text-white pt-24 pb-12">
    <div class="container mx-auto px-6 grid grid-cols-1 md:grid-cols-4 gap-16 mb-20 text-left">
      <div class="reveal">
        <div class="flex items-center gap-4 mb-8">
          <div class="relative w-12 h-12">
            <div class="absolute inset-0 border-2 border-primary rotate-45"></div>
            <div class="absolute inset-2 border border-white/10"></div>
          </div>
          <div>
            <span class="font-druk text-xl leading-none block">Erode</span>
            <span class="text-[7px] font-black uppercase tracking-widest text-white/30">Architect Association</span>
          </div>
        </div>
        <p class="text-[10px] font-medium text-white/40 uppercase tracking-widest leading-loose">
          Promoting regional design excellence and sustainable growth since 1985.
        </p>
      </div>

      <div class="reveal" style="transition-delay: 100ms;">
        <h4 class="font-druk text-sm mb-8 underline decoration-primary underline-offset-8">Links</h4>
        <ul class="space-y-3 text-[10px] font-black uppercase tracking-widest text-white/40">
          <li><a href="index.php" class="hover:text-white transition-colors">Home</a></li>
          <li><a href="about.php" class="hover:text-white transition-colors">About</a></li>
          <li><a href="calendar.php" class="hover:text-white transition-colors">Events</a></li>
        </ul>
      </div>

      <div class="reveal" style="transition-delay: 200ms;">
        <h4 class="font-druk text-sm mb-8 underline decoration-primary underline-offset-8">Legal</h4>
        <ul class="space-y-3 text-[10px] font-black uppercase tracking-widest text-white/40">
          <li><a href="#" class="hover:text-white transition-colors">Privacy Policy</a></li>
          <li><a href="#" class="hover:text-white transition-colors">By-Laws</a></li>
        </ul>
      </div>

      <div class="reveal" style="transition-delay: 300ms;">
        <div class="bg-white/5 p-8 rounded-[2rem] border border-white/5">
          <h4 class="text-[9px] font-black uppercase tracking-widest text-white/60 mb-4">Newsletter</h4>
          <div class="flex p-1 bg-charcoal rounded-xl border border-white/10">
            <input type="text" placeholder="EMAIL" class="bg-transparent text-[8px] p-3 flex-1 outline-none text-white">
            <button class="bg-primary px-5 rounded-lg text-[9px] font-black uppercase tracking-widest">OK</button>
          </div>
        </div>
      </div>
    </div>

    <div class="border-t border-white/5 pt-10 text-center text-[9px] font-black uppercase tracking-widest text-white/20">
      © 2026 Erode Architect Association.
    </div>
  </footer>

  <!-- MODAL -->
  <div id="proj-modal" class="fixed inset-0 z-[100] hidden items-center justify-center p-6 bg-slate-950/70 backdrop-blur-xl">
    <div class="absolute inset-0" onclick="closeProjModal()"></div>
    <div class="relative bg-background max-w-2xl w-full rounded-3xl border border-border overflow-hidden transition-all duration-500 scale-95 opacity-0" id="modal-box">
      <div class="relative aspect-video overflow-hidden">
        <img id="m-img" src="" class="w-full h-full object-cover" alt="">
      </div>
      <div class="p-10 text-left">
        <h3 id="m-title" class="font-druk text-3xl mb-2 text-foreground"></h3>
        <p id="m-sub" class="text-slate-500 text-xs leading-relaxed mb-8 uppercase tracking-widest"></p>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-5 pt-8 border-t border-border mb-8">
          <div><div class="text-[9px] font-black uppercase tracking-widest text-slate-400 mb-2">Budget</div><div id="m-budget" class="text-xs font-bold text-foreground"></div></div>
          <div><div class="text-[9px] font-black uppercase tracking-widest text-slate-400 mb-2">Area</div><div id="m-size" class="text-xs font-bold text-foreground"></div></div>
          <div><div class="text-[9px] font-black uppercase tracking-widest text-slate-400 mb-2">Year</div><div id="m-year" class="text-xs font-bold text-foreground"></div></div>
          <div><div class="text-[9px] font-black uppercase tracking-widest text-slate-400 mb-2">Location</div><div id="m-loc" class="text-xs font-bold text-foreground"></div></div>
        </div>
        <a href="contact.php" class="block w-full bg-primary py-5 rounded-2xl text-[10px] font-black uppercase tracking-widest text-white text-center hover:opacity-90 transition-all">
          Enquire Now
        </a>
      </div>
    </div>
  </div>

  <script>
    /* Preloader hide */
    window.addEventListener('load', () => {
      const p = document.getElementById('preloader');
      if (!p) return;
      setTimeout(() => p.classList.add('hide'), 250);
    });

    /* Navbar scroll */
    const header = document.getElementById('main-header');
    window.addEventListener('scroll', () => {
      if (window.scrollY > 120) header.classList.add('scrolled');
      else header.classList.remove('scrolled');
    });

    /* Hero slideshow (BRIGHTER) */
    let curSlide = 0;
    const slides = document.querySelectorAll('.slide-img');
    const indicators = document.querySelectorAll('.dot-indicator');

    function setSlide(index){
      slides[curSlide].style.opacity = '0';
      indicators[curSlide].classList.remove('w-8');
      indicators[curSlide].classList.add('bg-slate-300');

      curSlide = index;

      slides[curSlide].style.opacity = '0.55'; // brighter
      indicators[curSlide].classList.add('w-8');
      indicators[curSlide].classList.remove('bg-slate-300');
      indicators[curSlide].classList.add('bg-primary');
    }

    setInterval(() => {
      setSlide((curSlide + 1) % slides.length);
    }, 5200);

    /* Projects carousel */
    function scrollProj(dir){
      const c = document.getElementById('proj-carousel');
      c.scrollBy({ left: dir === 'left' ? -360 : 360, behavior: 'smooth' });
    }

    /* Modal */
    const modal = document.getElementById('proj-modal');
    const box = document.getElementById('modal-box');

    function openProjModal(p){
      document.getElementById('m-img').src = p.image || '';
      document.getElementById('m-img').alt = p.title || '';
      document.getElementById('m-title').innerText = p.title || '';
      document.getElementById('m-sub').innerText = p.desc || 'Excellence in regional architectural design in Erode.';
      document.getElementById('m-budget').innerText = p.budget || '-';
      document.getElementById('m-size').innerText = p.size || '-';
      document.getElementById('m-year').innerText = p.year || '-';
      document.getElementById('m-loc').innerText = p.location || '-';

      modal.style.display = 'flex';
      setTimeout(() => {
        box.classList.remove('scale-95','opacity-0');
        box.classList.add('scale-100','opacity-100');
      }, 10);
      document.body.style.overflow = 'hidden';
    }

    function closeProjModal(){
      box.classList.add('scale-95','opacity-0');
      setTimeout(() => {
        modal.style.display = 'none';
        document.body.style.overflow = 'auto';
      }, 250);
    }

    /* Reveal */
    const observer = new IntersectionObserver((entries) => {
      entries.forEach(e => { if (e.isIntersecting) e.target.classList.add('active'); });
    }, { threshold: 0.1 });
    document.querySelectorAll('.reveal').forEach(el => observer.observe(el));
  </script>
</body>
</html>
