<?php
/**
 * WebsiteTailors — Work & Portfolio Showcase Page
 */

declare(strict_types=1);

define('WebsiteTailors_INIT', true);
require_once __DIR__ . '/includes/init.php';

$settings = get_all_settings();
$projects = get_projects();

$pageTitle = 'Our Work — Business Website Portfolio | Website Tailors';
$pageDescription = 'Explore Website Tailors portfolio of business website designs, website redesigns, and WhatsApp automation concepts in Bangalore.';
$activePage = 'work';

require_once __DIR__ . '/includes/header.php';
?>

  <main id="main-content" style="padding-top: 140px;">
    <section class="work" style="padding-top: 40px; padding-bottom: 120px;">
      <div class="container">
        <div class="eyebrow" style="margin-bottom: 20px;">Portfolio</div>
        <h1 class="section-title" style="margin-bottom: 30px;">
          SELECTED<br>
          <span class="outline">PROJECTS.</span>
        </h1>
        <p class="work-note" style="max-width: 580px; width: 100%; font-size: 16px; margin-bottom: 60px;">
          A collection of digital solutions built around the exact operational needs of our clients. Designed for speed, conversion, and effortless user flow.
        </p>

        <?php if (empty($projects)): ?>
          <div class="empty-state-public">
            <div class="empty-state-icon">✦</div>
            <h3>Portfolio Updating</h3>
            <p>Our recent product launches and case studies are being prepared. Contact us to request private project briefs and architectural references.</p>
            <a href="contact.php" class="button button-primary magnetic">Request Portfolio Deck ↗</a>
          </div>
        <?php else: ?>
          <div class="projects" style="gap: 40px;">
            <?php foreach ($projects as $index => $project): ?>
              <?php 
                $projNum = str_pad((string)($index + 1), 2, '0', STR_PAD_LEFT); 
                $totalCount = str_pad((string)count($projects), 2, '0', STR_PAD_LEFT);
                $projectImg = get_image_url($project['image'] ?? $project['image_path'] ?? null, 'project');
                $hasCustomImage = !empty($project['image']) && file_exists(ROOT_PATH . '/' . ltrim($project['image'], '/\\'));
              ?>
              <article class="project reveal" style="position: relative; top: 0; min-height: auto; padding: 48px; gap: 40px;">
                <div class="project-top">
                  <div>
                    <span class="project-number"><?= e($projNum) ?> / <?= e($totalCount) ?></span>
                    <?php if (!empty($project['client_name'])): ?>
                      <span style="margin-left: 14px; font-family: 'DM Mono', monospace; font-size: 11px; color: var(--muted);">
                        Client: <?= e($project['client_name']) ?>
                      </span>
                    <?php endif; ?>
                  </div>

                  <div class="project-type">
                    <?= e($project['category']) ?>
                  </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 40px; align-items: center;">
                  <div>
                    <h2 class="project-title" style="font-size: clamp(2.4rem, 4.5vw, 4rem);">
                      <?= e($project['title']) ?>
                    </h2>
                    <p style="color: #4b4b45; font-size: 15px; margin-top: 16px; line-height: 1.7;">
                      <?= e($project['description']) ?>
                    </p>

                    <?php if (!empty($project['tags'])): ?>
                      <div style="margin-top: 24px; display: flex; flex-wrap: wrap; gap: 8px;">
                        <?php foreach (explode(',', (string)$project['tags']) as $tag): ?>
                          <span style="background: rgba(17,17,17,0.06); border: 1px solid rgba(17,17,17,0.1); padding: 4px 10px; border-radius: 6px; font-family: 'DM Mono', monospace; font-size: 11px;">
                            <?= e(trim($tag)) ?>
                          </span>
                        <?php endforeach; ?>
                      </div>
                    <?php endif; ?>

                    <div style="margin-top: 32px; display: flex; gap: 16px; flex-wrap: wrap;">
                      <a href="contact.php?project=<?= urlencode($project['title']) ?>" class="button button-primary magnetic">
                        Request Similar Project ↗
                      </a>
                      <?php if (!empty($project['project_url'])): ?>
                        <a href="<?= e($project['project_url']) ?>" target="_blank" rel="noopener noreferrer" class="button button-secondary magnetic">
                          Live Demo ↗
                        </a>
                      <?php endif; ?>
                    </div>
                  </div>

                  <!-- Visual browser mock or image -->
                  <div class="browser" style="box-shadow: 0 25px 60px rgba(0,0,0,0.12); transform: none; min-height: 280px;">
                    <div class="browser-top">
                      <div class="browser-dot"></div>
                      <div class="browser-dot"></div>
                      <div class="browser-dot"></div>
                    </div>
                    <?= render_project_preview_html($project) ?>
                  </div>
                </div>
              </article>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </section>
  </main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
