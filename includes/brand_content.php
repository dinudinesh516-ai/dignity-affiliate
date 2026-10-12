<?php
/**
 * Brand / flyer content shown on the public pages.
 * Options (set before include):
 *   $bcProgram   'open' | 'collapsed' | 'hide'  — the "Join our exclusive referral program" block
 *   $bcFlyerLink bool — show "View our flyer" button
 */
$bcProgram = $bcProgram ?? 'open';
$bcFlyerLink = $bcFlyerLink ?? false;
$flyerUrl = 'https://dealersclubs.com/flyer/';

$programPoints = [
    ['globe2', 'Direct sourcing from many of the same global markets and suppliers used by major retailers.'],
    ['shop', 'No expensive showroom overhead or large corporate structure — allowing us to focus on exceptional value, quality, and service.'],
    ['people', 'Simply introduce your friends, family, clients, or associates — we manage everything from consultation to delivery.'],
    ['shield-check', 'Every referral is protected through our structured referral program, MOU, and documented agreement.'],
    ['cash-coin', 'Earn meaningful commissions through trusted introductions while continuing to focus on your own business and lifestyle.'],
    ['stars', 'Access exclusive opportunities, special offers, market insights, and private deals shared within our network.'],
    ['hand-thumbs-up', 'Built on honesty, transparency, integrity, and long-term partnerships.'],
    ['chat-dots', 'Interested in becoming a referral partner or using our services for yourself? Contact us by WhatsApp, text, email, or phone and mention the referral program. We will send you our terms and MOU.'],
    ['clipboard-check', 'To ensure accurate tracking, send us your referral\'s name and contact information by WhatsApp, text, or email — or use your personal QR code. We will maintain your referral account and keep your commissions properly recorded.'],
];
// Team names & contact details live in brand_data.php (not stored in git).
// brand_data.sample.php is used as a fallback so the page still renders.
$brandData = require (is_file(__DIR__ . '/brand_data.php') ? __DIR__ . '/brand_data.php' : __DIR__ . '/brand_data.sample.php');
$team = $brandData['team'] ?? [];
$bcTel = function ($p) { return '+1' . preg_replace('/\D/', '', $p); };
?>
<div class="brand-content">

<?php if ($bcProgram !== 'hide'): ?>
  <section class="bc-section">
    <?php if ($bcProgram === 'collapsed'): ?>
      <button class="bc-toggle" type="button" data-bs-toggle="collapse" data-bs-target="#bcProgram" aria-expanded="false">
        <span><i class="bi bi-gem me-2"></i>Join our exclusive referral program</span><i class="bi bi-chevron-down"></i>
      </button>
      <div class="collapse" id="bcProgram">
    <?php else: ?>
      <div class="bc-eyebrow">Join our exclusive referral program</div>
      <div>
    <?php endif; ?>
        <ul class="bc-points">
          <?php foreach ($programPoints as [$icon, $txt]): ?>
            <li><i class="bi bi-<?= $icon ?>"></i><span><?= e($txt) ?></span></li>
          <?php endforeach; ?>
        </ul>
        <div class="bc-tagline">Your referral. Your relationship. Your reward.</div>
      </div>
  </section>
<?php endif; ?>

  <section class="bc-section text-center">
    <div class="bc-script">Free Consultation</div>
    <div class="bc-script small-script">Serving the community with expert consultation</div>
    <div class="bc-years">35+ Years of Trusted Expertise</div>
    <img src="<?= asset('img/showcase.jpg') ?>" alt="Sapphire ring, peridot heart ring, ruby pendant, yellow sapphire ring, luxury handbag and luxury watch" class="bc-showcase" loading="lazy">
    <?php if ($bcFlyerLink): ?>
      <a href="<?= e($flyerUrl) ?>" target="_blank" rel="noopener" class="btn btn-outline-gold mt-3"><i class="bi bi-file-earmark-richtext me-1"></i>View our flyer</a>
    <?php endif; ?>
  </section>

  <section class="bc-section">
    <div class="row g-3">
      <?php foreach ($team as $t): ?>
      <div class="col-12">
        <div class="bc-person">
          <div class="bc-name"><?= e($t['name']) ?></div>
          <div class="bc-title"><?= e($t['title']) ?></div>
          <ul class="bc-list">
            <?php foreach ($t['items'] as $it): ?><li><?= e($it) ?></li><?php endforeach; ?>
          </ul>
          <div class="bc-contact">
            <a href="tel:<?= e($bcTel($t['phone'])) ?>"><i class="bi bi-telephone"></i><?= e($t['phone']) ?></a>
            <a href="<?= e('https://wa.me/' . ltrim($bcTel($t['phone']), '+')) ?>" target="_blank" rel="noopener"><i class="bi bi-whatsapp"></i>WhatsApp</a>
            <a href="mailto:<?= e($t['email']) ?>"><i class="bi bi-envelope"></i><?= e($t['email']) ?></a>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </section>

  <section class="bc-section bc-story">
    <p>Life has taken us on an incredible journey through the world of fine jewelry, diamonds, gemstones, and luxury watches. Decades ago, we set sail with a passion for beauty, craftsmanship, and trust. Along the way, we built lifelong relationships, learned invaluable lessons, and had the privilege of serving clients from around the world.</p>
    <p>Today, the compass has turned slightly. While we continue navigating the jewelry industry we love, we are also enjoying life's greatest treasures — spending quality time with our children and grandchildren, exploring new destinations, and creating memories across the globe.</p>
    <p>Working remotely from Los Angeles, New York, and Miami, we continue to help clients, friends, and family find the right path when it comes to diamonds, jewelry, colored gemstones, watches, and rare treasures.</p>
    <p>Before you spend thousands on a purchase from Blue Nile, Brilliant Earth, eBay, or any major retailer, give us a call, text, or WhatsApp message. Sometimes a small course correction can lead to a much better destination — better quality, better value, and significant savings.</p>
    <p>There is never any obligation to buy from us. We simply enjoy sharing decades of experience, insider knowledge, and trusted guidance to help people make smarter decisions.</p>
    <p>The destination has never changed: honesty, value, quality, and relationships. The journey continues, and we look forward to helping you navigate yours.</p>
    <blockquote>“Not every treasure is found by following the crowd. Sometimes the best discoveries come from having the right guide.”</blockquote>
  </section>

  <section class="bc-section bc-compare text-center">
    <h2>Dare to Compare</h2>
    <p class="mb-2"><strong>Direct from the source, often at prices well below major online and luxury retailers.</strong></p>
    <p class="mb-0">Feel free to send us any quote for comparison. We welcome the challenge and take pride in delivering exceptional value, quality, and service.</p>
  </section>

  <section class="bc-section text-center">
    <img src="<?= asset('img/vg-logo.png') ?>" alt="VG" class="bc-vg" width="84" height="84" loading="lazy">
    <div class="mt-2"><a href="https://www.dignityjewelry.com" target="_blank" rel="noopener" class="bc-site">www.dignityjewelry.com</a></div>
    <?php if ($bcFlyerLink): ?>
      <div class="mt-2"><a href="<?= e($flyerUrl) ?>" target="_blank" rel="noopener" class="small">View our flyer <i class="bi bi-box-arrow-up-right"></i></a></div>
    <?php endif; ?>
  </section>
</div>
