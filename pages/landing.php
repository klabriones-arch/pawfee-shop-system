<?php
/**
 * Landing page (route: ?page=home  — also the default page).
 * EDIT THE TEXT HERE: every sentence on the page lives in the $t array below.
 * Contact details come from config.php. Images: see the notes next to each <img>.
 */
$t = [
    'hero_kicker'  => 'pause and hand us your paws at',
    'hero_tagline' => 'a little pause for you and your paws!',
    'hero_sub'     => 'enjoy a wide variety of pastries, coffee, and drinks!',

    'strip'        => 'Fresh brews, warm bakes and wagging tails, all under one roof.',

    'about_label'  => '01 / About us',
    'about_title'  => 'A cozy corner for coffee people and their pets.',
    'about_p1'     => 'Pawfee Shop is a neighborhood café where every cup is made to order and every pastry is baked fresh. Pull up a chair, bring your four-legged bestie, and stay a while.',
    'about_p2'     => 'From our signature Pawfee latte to the paw-print brownie, everything on the menu is made with a little extra love. Order online for pickup or delivery, or drop by and say hi.',

    'hello_label'  => '02 / Say hello',
    'hello_title'  => 'Come say hi, we saved you a seat.',
    'hello_p'      => 'Questions, party orders or pet-friendly seating? Reach out and we will get back to you.',
    'hello_small'  => 'All paws welcome.',
    'closing_title'=> 'Good coffee. Great company.',
    'closing_text' => 'We are happy to take special requests for celebrations and gifts.',

    'cta_label'    => 'Make yourself at home',
    'cta_title'    => 'Your favorite cup is waiting.',
    'cta_text'     => 'Log in to your Pawfee account and pick up where you left off.',
];

/* Optional images. Drop a file with this name into assets/images/ to replace the placeholder:
 *   wordmark.png  -> the big "PAWFEE SHOP" lettering in the hero
 *   about.jpg     -> the picture in the About section */
function landing_img(string $name): ?string
{
    foreach (['png', 'svg', 'jpg', 'jpeg', 'webp'] as $ext)
        if (is_file(__DIR__ . "/../assets/images/$name.$ext")) return asset("images/$name.$ext");
    return null;
}
$wordmark = landing_img('wordmark');
$aboutImg = landing_img('about');
$user     = current_user();

page_header('Welcome', 'home', true);
?>
<section class="land-hero">
  <p class="land-kicker"><?= e($t['hero_kicker']) ?></p>
  <?php if ($wordmark): ?>
    <img class="land-wordmark" src="<?= $wordmark ?>" alt="Pawfee Shop">
  <?php else: ?>
    <h1 class="land-wordmark-text"><span>Pawfee</span><span>Shop</span></h1>
  <?php endif; ?>
  <p class="land-sub"><?= e($t['hero_tagline']) ?><br><?= e($t['hero_sub']) ?></p>
  <a class="btn" href="<?= url('shop') ?>">Get started</a>
  <a class="land-more" href="#about">Learn more about us below ↓</a>
</section>

<div class="land-strip"><?= e($t['strip']) ?></div>

<section class="land-section" id="about">
  <div class="land-wrap land-about">
    <?php if ($aboutImg): ?>
      <img class="land-photo" src="<?= $aboutImg ?>" alt="Inside Pawfee Shop">
    <?php else: ?>
      <div class="land-photo land-photo-empty">Image placeholder</div>
    <?php endif; ?>
    <div>
      <span class="land-label"><?= e($t['about_label']) ?></span>
      <h2 class="land-h2"><?= e($t['about_title']) ?></h2>
      <p class="muted"><?= e($t['about_p1']) ?></p>
      <p class="muted"><?= e($t['about_p2']) ?></p>
    </div>
  </div>
</section>

<section class="land-section land-sage" id="contact">
  <div class="land-wrap">
    <div class="land-hello">
      <div>
        <span class="land-label"><?= e($t['hello_label']) ?></span>
        <h2 class="land-h2"><?= e($t['hello_title']) ?></h2>
        <p class="muted"><?= e($t['hello_p']) ?></p>
        <p class="muted small"><?= e($t['hello_small']) ?></p>
      </div>
      <dl class="land-info">
        <div><dt>Visit us</dt><dd><?= e(STORE_ADDRESS) ?></dd></div>
        <div><dt>Get in touch</dt><dd><a href="mailto:<?= e(STORE_EMAIL) ?>"><?= e(STORE_EMAIL) ?></a><br><?= e(STORE_PHONE) ?></dd></div>
        <div><dt>Opening hours</dt><dd><?= e(STORE_HOURS) ?></dd></div>
      </dl>
    </div>
    <div class="land-closing">
      <strong><?= e($t['closing_title']) ?></strong>
      <p class="muted small"><?= e($t['closing_text']) ?></p>
    </div>
  </div>
</section>

<section class="land-cta">
  <span class="land-label"><?= e($t['cta_label']) ?></span>
  <h2><?= e($t['cta_title']) ?></h2>
  <p><?= e($t['cta_text']) ?></p>
  <a class="btn btn-light" href="<?= $user ? home_for($user) : url('login') ?>">Get started</a>
</section>

<footer class="land-footer">
  <div class="land-wrap land-footer-row">
    <div>
      <strong class="land-footer-brand">PAWFEE SHOP</strong>
      <p class="muted small">a little pause for you and your paws</p>
    </div>
    <nav>
      <a href="<?= url('home') ?>">Home</a><a href="<?= url('shop') ?>">Shop</a><a href="#about">About</a><a href="#contact">Contact</a>
    </nav>
  </div>
  <div class="land-wrap land-footer-bar muted small">© <?= date('Y') ?> Pawfee Shop. All rights reserved.</div>
</footer>
<?php page_footer(true);
