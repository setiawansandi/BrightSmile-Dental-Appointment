<?php
// src/components/footer.php

// Tiny escape helper (if you don't already have one loaded)
if (!function_exists('e')) {
  function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}

/**
 * Overrides (optional)
 * You can set these BEFORE including this file:
 *   $brand         string   Site/brand name
 *   $logo_path     string   Path/URL to the logo
 *   $description   string   One or two sentences for the footer intro
 *   $links         array[]  List of ['href' => '/path', 'label' => 'Text']
 *
 * All paths below default to absolute URLs so they work from any directory.
 */
$brand       = $brand       ?? 'BrightSmile';
$logo_path   = $logo_path   ?? 'assets/icons/logo.svg';
$description = $description ?? 'BrightSmile offers gentle, modern dental care with clear guidance and advanced technology. Comfortable visits, from checkups to cosmetic treatments.';
$links       = $links       ?? [
  ['href' => 'index.php',              'label' => 'Home'],
  ['href' => 'services.php',  'label' => 'Services'],
  ['href' => 'clinics.php',   'label' => 'Clinics'],
  ['href' => 'about.php',     'label' => 'About'],
  ['href' => 'more.php',      'label' => 'More'],
];

$year = date('Y');
?>

<footer class="footer">
  <div class="footer-content">
    <!-- Left Column -->
    <div class="footer-left">
      <div class="footer-header">
        <img src="<?= e($logo_path) ?>" alt="Logo" class="footer-logo" />
        <div class="brand-name"><?= e($brand) ?></div>
      </div>
      <p><?= e($description) ?></p>
      <p class="copyright">© <?= e($year) ?> <?= e($brand) ?>. All Rights Reserved.</p>
    </div>

    <!-- Right Column -->
    <div class="footer-right">
      <div class="footer-header">
        <div class="links-title">Quick Links</div>
      </div>
      <ul>
        <?php foreach ($links as $link): ?>
          <li>
            <a href="<?= e($link['href'] ?? '#') ?>"><?= e($link['label'] ?? 'Link') ?></a>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>
  </div>
</footer>
