<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/helpers.php';

start_session();

$pageTitle = $pageTitle ?? 'EAA Portal';
$flashSuccess = flash_get('success');
$flashError = flash_get('error');
?>
<!DOCTYPE html>
<html lang="en" class="light">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?= e($pageTitle) ?></title>

  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;700;900&family=Montserrat:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">


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
    :root{
      --background: 210 25% 98%;
      --foreground: 220 20% 18%;
      --primary: 197 13% 43%;
      --card: 210 20% 96%;
      --border: 210 12% 86%;
      --charcoal: 220 20% 12%;
      --cream: 210 25% 98%;
      --blueprint: 198 12% 48%;
    }

    body{
      background-color: hsl(var(--background));
      color: hsl(var(--foreground));
      transition: background-color .3s ease, color .3s ease;
    }

    .font-druk{
      font-family: 'Inter', sans-serif;
      font-weight: 900;
      text-transform: uppercase;
      letter-spacing: -0.05em;
      line-height: 0.85;
    }

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

    #main-header{ transition: all .4s cubic-bezier(0.4,0,0.2,1); }
    #main-header.scrolled{
      background-color: hsl(var(--background) / 0.92);
      backdrop-filter: blur(12px);
      box-shadow: 0 10px 30px rgba(2,6,23,0.06);
      padding: .55rem 0;
      border-bottom: 1px solid hsl(var(--border));
    }

    @keyframes drawLine { from { stroke-dashoffset: 200; } to { stroke-dashoffset: 0; } }
    @keyframes marquee { 0% { transform: translateX(0); } 100% { transform: translateX(-50%); } }

    .reveal{ opacity:0; transform: translateY(26px); transition: all .8s ease-out; }
    .reveal.active{ opacity:1; transform: translateY(0); }

    .skyline-path{ stroke-dasharray: 200; stroke-dashoffset: 200; animation: drawLine 1.5s ease-out forwards 1.2s; }
    .scrollbar-hide::-webkit-scrollbar{ display:none; }
    .scrollbar-hide{ -ms-overflow-style:none; scrollbar-width:none; }

    .hero-img{ filter: blur(2px); }

    .measurement-line{ width: 1px; background: linear-gradient(to bottom, transparent, hsl(var(--primary) / 0.2), transparent); height: 60px; }

    .grid-panel{
      background: hsl(var(--background));
      border: 1px solid hsl(var(--border));
      border-radius: 24px;
      padding: 12px;
      box-shadow: 0 20px 45px rgba(2,6,23,0.08);
    }
    .member-card{
      position: relative;
      overflow: hidden;
      border-radius: 16px;
      border: 1px solid hsl(var(--border));
      background: hsl(var(--card));
      box-shadow: inset 0 0 0 1px hsl(var(--border));
      transition: transform .3s ease, border-color .3s ease;
    }
    .member-card:hover{
      transform: translateY(-4px);
      border-color: hsl(var(--primary) / 0.4);
    }
    .member-card img{
      width: 100%;
      height: 100%;
      object-fit: cover;
    }
    .card-label{
      position: absolute;
      padding: 6px 10px;
      font-size: 9px;
      letter-spacing: .3em;
      text-transform: uppercase;
      font-weight: 900;
      background: hsl(var(--charcoal) / 0.85);
      color: white;
      border: 1px solid hsl(var(--border));
      border-radius: 999px;
    }
    .label-tl{ top: 10px; left: 10px; }
    .label-bl{ bottom: 10px; left: 10px; }

    .team-card{ position: relative; }
    .team-actions{
      position: absolute;
      top: 16px;
      right: 16px;
      display: flex;
      flex-direction: column;
      gap: 10px;
      opacity: 0;
      transform: translateY(-6px);
      transition: opacity .25s ease, transform .25s ease;
      z-index: 2;
    }
    .team-card:hover .team-actions{
      opacity: 1;
      transform: translateY(0);
    }
    .action-btn{
      width: 38px;
      height: 38px;
      border-radius: 999px;
      display: flex;
      align-items: center;
      justify-content: center;
      background: hsl(var(--background) / 0.92);
      border: 1px solid hsl(var(--border));
      color: hsl(var(--foreground));
      box-shadow: 0 10px 18px rgba(2,6,23,0.12);
      backdrop-filter: blur(10px);
      transition: transform .2s ease, background .2s ease, border-color .2s ease;
    }
    .action-btn:hover{
      transform: translateY(-1px) scale(1.03);
      border-color: hsl(var(--primary) / 0.6);
      background: hsl(var(--background));
    }

    .nav-link{
      position: relative;
      display: inline-flex;
      align-items: center;
      gap: 0.35rem;
      padding: 0.5rem 0.25rem;
      font-size: 12px;
      font-weight: 800;
      letter-spacing: 0.32em;
      text-transform: uppercase;
      color: hsl(var(--foreground) / 0.7);
      transition: color .2s ease, opacity .2s ease, transform .2s ease;
      white-space: nowrap;
    }
    .nav-link:hover,
    .nav-link:focus-visible{
      color: hsl(var(--primary));
    }
    .nav-link:focus-visible{
      outline: 2px solid hsl(var(--primary));
      outline-offset: 4px;
      border-radius: 999px;
    }
    .nav-link-active{
      color: hsl(var(--primary));
      text-decoration: underline;
      text-decoration-color: hsl(var(--primary));
      text-underline-offset: 0.6em;
    }
    .brand-mark{
      display: inline-flex;
      align-items: center;
      gap: 0.75rem;
      padding: 0.55rem 0.85rem;
      border-radius: 18px;
      background: hsl(var(--background) / 0.9);
      border: 1px solid hsl(var(--border));
      box-shadow: 0 14px 30px rgba(2,6,23,0.08);
      backdrop-filter: blur(10px);
      transition: transform .25s ease, box-shadow .25s ease, border-color .25s ease;
    }
    .brand-mark:hover{
      transform: translateY(-1px);
      border-color: hsl(var(--primary) / 0.45);
      box-shadow: 0 16px 36px rgba(2,6,23,0.12);
    }
    .brand-icon{
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 44px;
      height: 44px;
      border-radius: 14px;
      background: hsl(var(--primary) / 0.08);
      border: 1px solid hsl(var(--border));
      box-shadow: inset 0 0 0 1px hsl(var(--primary) / 0.08);
    }
    .brand-text{
      display: flex;
      flex-direction: column;
      line-height: 1.05;
    }
    .brand-kicker{
      font-size: 9px;
      font-weight: 900;
      letter-spacing: 0.32em;
      text-transform: uppercase;
      color: hsl(var(--primary));
    }
    .brand-name{
      font-size: 13px;
      font-weight: 800;
      letter-spacing: 0.08em;
      color: hsl(var(--foreground));
    }
    .brand-tight{
      transform: translateY(-2px) scale(0.96);
      box-shadow: 0 12px 26px rgba(2,6,23,0.10);
    }
    .nav-rail{
      display: inline-flex;
      align-items: center;
      gap: 1.3rem;
      padding: 0.4rem 0.85rem;
      border-radius: 999px;
      border: 1px solid hsl(var(--border));
      background: hsl(var(--background) / 0.85);
      box-shadow: 0 12px 28px rgba(2,6,23,0.06);
      backdrop-filter: blur(10px);
    }
    .logo-lockup{
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 0.35rem;
      width: 100%;
      min-height: 40px;
      padding: 0.35rem 0.5rem;
      border-radius: 14px;
      background: hsl(var(--background) / 0.7);
      border: 1px solid hsl(var(--border));
      box-shadow: 0 10px 35px rgba(2,6,23,0.08);
      backdrop-filter: blur(10px);
    }
    .logo-fallback{
      font-size: 12px;
      font-weight: 900;
      letter-spacing: 0.35em;
      text-transform: uppercase;
      color: hsl(var(--primary));
      white-space: nowrap;
    }
  </style>
</head>

<body class="antialiased font-body">
  <?php require __DIR__ . '/navbar.php'; ?>

  <?php if ($flashSuccess): ?>
    <div class="container mx-auto px-6 mt-28">
      <div class="mb-6 border border-primary/30 bg-primary/10 text-primary px-4 py-3 rounded-xl text-xs font-bold uppercase tracking-widest">
        <?= e($flashSuccess) ?>
      </div>
    </div>
  <?php endif; ?>
  <?php if ($flashError): ?>
    <div class="container mx-auto px-6 mt-4">
      <div class="mb-6 border border-red-300 bg-red-50 text-red-500 px-4 py-3 rounded-xl text-xs font-bold uppercase tracking-widest">
        <?= e($flashError) ?>
      </div>
    </div>
  <?php endif; ?>
