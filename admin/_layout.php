<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/helpers.php';
require_once __DIR__ . '/../auth/guard.php';

require_role('admin');
require_active_user();

$pageTitle = $pageTitle ?? 'Admin Panel';
$content = $content ?? '';

require_once __DIR__ . '/../partials/header.php';
?>
<section class="container mx-auto px-6 flex flex-col lg:flex-row gap-8">
  <aside class="w-full lg:w-64 bg-card border border-border rounded-3xl p-6 h-fit">
    <h2 class="font-druk text-2xl text-foreground mb-6">Admin</h2>
    <nav class="space-y-3 text-[10px] font-black uppercase tracking-widest text-slate-400">
      <a href="<?= e(url('admin/dashboard.php')) ?>" class="block hover:text-primary">Dashboard</a>
      <a href="<?= e(url('admin/users.php')) ?>" class="block hover:text-primary">Users</a>
      <a href="<?= e(url('admin/blogs.php')) ?>" class="block hover:text-primary">Blogs</a>
      <a href="<?= e(url('admin/jobs.php')) ?>" class="block hover:text-primary">Jobs</a>
      <a href="<?= e(url('admin/approve_sponsors.php')) ?>" class="block hover:text-primary">Sponsors</a>
      <a href="<?= e(url('admin/events.php')) ?>" class="block hover:text-primary">Events</a>
      <a href="<?= e(url('admin/settings.php')) ?>" class="block hover:text-primary">Settings</a>
    </nav>
  </aside>

  <div class="flex-1 space-y-8">
    <?= $content ?>
  </div>
</section>
<?php
require_once __DIR__ . '/../partials/footer.php';
?>
