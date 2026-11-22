<?php
$user = require_login();
$pending = $_SESSION['pending_checkout'] ?? null;
if (!$pending) redirect(url('cart'));

if (is_post()) {
    csrf_check();
    $result = $_POST['result'] ?? '';
    unset($_SESSION['pending_checkout']);               // single use = no duplicate orders
    if ($result === 'success') {
        try {
            $pending['payment_status'] = 'paid';
            $oid = place_order($user, $pending);
            $no  = fetch_val('SELECT order_no FROM orders WHERE id=?', [$oid]);
            flash('success', "Payment successful. Order $no placed!");
            redirect(url('order', ['no' => $no]));
        } catch (RuntimeException $e) { flash('error', $e->getMessage()); redirect(url('cart')); }
    }
    flash($result === 'cancel' ? 'info' : 'error', $result === 'cancel' ? 'Payment cancelled. Your cart is still saved.' : 'Payment failed. No charge was made — please try again.');
    redirect(url('checkout'));
}
page_header('Mock payment');
?>
<div class="auth-card narrow"><h1>Mock payment gateway</h1>
  <p class="muted center">Demo only. Amount due: <strong><?= money($pending['total']) ?></strong></p>
  <form method="post" class="stack"><?= csrf_field() ?>
    <button class="btn btn-block" name="result" value="success">Simulate success</button>
    <button class="btn btn-block btn-ghost" name="result" value="failure">Simulate failure</button>
    <button class="btn btn-block btn-ghost" name="result" value="cancel">Cancel payment</button>
  </form></div>
<?php page_footer();
