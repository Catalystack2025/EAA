<?php
/* =========================================================
   admin/partials/header.php — TERMINAL HEADER
   ✅ Technical Status Indicators
   ✅ Shared Meta Tags & Styles
   ========================================================= */
?>
<!DOCTYPE html>
<html lang="en" class="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? $pageTitle : 'Admin Console | EAA Root' ?></title>
    
    <!-- Google Fonts: Montserrat Only -->
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    
    <!-- Tailwind CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- FontAwesome for Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    

    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: { sans: ['Montserrat', 'sans-serif'] },
                    colors: {
                        eaa_smoke: '#475569',
                        eaa_border: '#e2e8f0',
                        eaa_accent: '#0f172a',
                    }
                }
            }
        }
    </script>

    <style>
        :root { --eaa-radius: 5px; }
        body { background-color: #f8fafc; color: #1e293b; font-family: 'Montserrat', sans-serif; margin: 0; }
        .eaa-radius { border-radius: var(--eaa-radius) !important; }
        .font-druk { font-weight: 900; text-transform: uppercase; letter-spacing: -0.04em; line-height: 0.85; }

        .dashboard-wrapper { display: grid; grid-template-columns: 280px 1fr; min-height: 100vh; }

        .admin-sidebar { background-color: #0f172a; color: #f1f5f9; padding: 40px 24px; display: flex; flex-direction: column; position: sticky; top: 0; height: 100vh; z-index: 100; }
        .nav-item { display: flex; align-items: center; gap: 12px; padding: 14px 18px; font-size: 10px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.1em; color: #94a3b8; border-radius: var(--eaa-radius); transition: all 0.3s ease; margin-bottom: 2px; }
        .nav-item:hover, .nav-item.active { color: #ffffff; background-color: rgba(255,255,255,0.05); }
        .nav-item.active { background-color: #1e293b; border-left: 2px solid #ffffff; }

        .terminal-viewport { padding: 40px; background-color: #f8fafc; position: relative; }
        .terminal-header { display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 40px; padding-bottom: 20px; border-bottom: 1px solid #e2e8f0; position: relative; z-index: 10; }
        
        .tech-label { font-size: 8px; font-weight: 900; text-transform: uppercase; letter-spacing: 0.25em; color: #94a3b8; display: block; margin-bottom: 6px; }
        .metric-card { background: #ffffff; border: 1px solid #e2e8f0; border-radius: var(--eaa-radius); padding: 24px; transition: all 0.3s ease; }
        .metric-value { font-size: 2.2rem; font-weight: 900; color: #0f172a; line-height: 1; }

        .blueprint-grid { background-image: linear-gradient(rgba(15, 23, 42, 0.03) 1px, transparent 1px), linear-gradient(90deg, rgba(15, 23, 42, 0.03) 1px, transparent 1px); background-size: 25px 25px; }
        .reveal { opacity: 0; transform: translateY(15px); transition: all 0.6s cubic-bezier(0.2, 0.8, 0.2, 1); }
        .reveal.active { opacity: 1; transform: translateY(0); }

        @media (max-width: 1024px) { .dashboard-wrapper { grid-template-columns: 1fr; } .admin-sidebar { display: none; } }
    </style>
</head>
<body class="antialiased">
<div class="dashboard-wrapper">
    <?php require_once 'sidebar.php'; ?>
    <main class="terminal-viewport relative">
        <div class="absolute inset-0 blueprint-grid opacity-30 pointer-events-none"></div>
        <!-- <header class="terminal-header">
            <div>
                <span class="tech-label">Security Protocol: AES_256 // ACTIVE</span>
                <h1 class="font-druk text-3xl text-slate-900">Administrator <span class="text-slate-400 italic">Terminal</span></h1>
            </div>
            <div class="flex items-center gap-8">
                <div class="text-right">
                    <span class="block text-[10px] font-black uppercase text-slate-900"><?= isset($admin['name']) ? $admin['name'] : 'Admin Node 01' ?></span>
                    <div class="flex items-center justify-end gap-2">
                        <div class="w-1.5 h-1.5 bg-green-500 rounded-full"></div>
                        <span class="text-[8px] font-bold uppercase tracking-widest text-slate-400">System Online</span>
                    </div>
                </div>
            </div>
        </header> -->