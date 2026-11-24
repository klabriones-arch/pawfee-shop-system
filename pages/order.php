<?php
$user = require_role('customer');
// Ownership check: customers can only ever load their own orders.
$o = fetch_one('SELECT * FROM orders WHERE order_no=? AND user_id=?', [$_GET['no'] ?? '', $user['id']]);
if (!$o) { http_response_code(404); page_header('Order'); echo '<div class="empty"><h1>Order not found</h1><a class="btn" href="' . url('account') . '">My orders</a></div>'; page_footer(); return; }

$activeReq = fetch_one('SELECT * FROM return_requests WHERE order_id=? AND status NOT IN ("Rejected","Closed") ORDER BY id DESC LIMIT 1', [$o['id']]);
$canCancel = in_array($o['status'], ['Pending', 'Confirmed', 'Processing'], true);
$canReturn = $o['status'] === 'Delivered';

if (is_post() && ($canCancel || $canReturn) && !$activeReq) {
    csrf_check();
    $type = $canCancel ? 'cancellation' : 'return';
    $reason = input('reason');
    if ($reason === '') flash('error', 'Please select a reason.');
    else {
        q('INSERT INTO return_requests (order_id,user_id,type,reason,description) VALUES (?,?,?,?,?)', [$o['id'], $user['id'], $type, $reason, input('description') ?: null]);
        notify((int)$user['id'], $user['email'], 'return_request', "We received your $type request", "Order {$o['order_no']}: we'll review your request shortly.");
        notify_staff('return_request', "New $type request: {$o['order_no']}", "Reason: $reason");
        flash('success', 'Request submitted. We’ll review it soon.');
    }
    redirect(($_GET['from']??'')==='returns' ? url('returns') : url('order', ['no' => $o['order_no']]));
}

$items = flow_order_items((int)$o['id']);
$history = fetch_all('SELECT h.*, u.first_name FROM order_status_history h LEFT JOIN users u ON u.id=h.updated_by WHERE order_id=? ORDER BY h.id', [$o['id']]);
$pay     = fetch_one('SELECT * FROM payments WHERE order_id=? ORDER BY id DESC LIMIT 1', [$o['id']]);
$ship    = json_decode($o['shipping_address'] ?? '[]', true) ?: [];
$reqs    = fetch_all('SELECT * FROM return_requests WHERE order_id=? ORDER BY id DESC', [$o['id']]);
page_header('Order ' . $o['order_no'], 'orders'); ?>
<div class="row-between crumbs"><span>Home / Account / Order history / Order details</span><a href="<?= url('orders') ?>">← Back to order history</a></div><div class="row-between"><h1>Order #<?= e($o['order_no']) ?></h1><?php if(($canCancel||$canReturn)&&!$activeReq): ?><a class="btn btn-ghost" href="<?= url('returns',['no'=>$o['order_no']]) ?>">Cancel / return order</a><?php endif; ?></div>
<div class="flow-account-layout"><?php account_nav($user,'orders',true); ?><div class="stack flow-account-content"><section class="card pad flow-sage"><h3>Your order is <?= e(strtolower($o['status'])) ?></h3><?php $stages=['Order confirmed'=>['Confirmed'],'Preparing'=>['Processing','Packed'],'Out for delivery'=>['Shipped','Out for Delivery'],'Delivered'=>['Delivered']]; $level=-1; foreach(array_values($stages) as $n=>$statuses) if(in_array($o['status'],$statuses,true)) $level=$n; ?><div class="flow-track"><?php foreach($stages as $label=>$statuses): $n=array_search($label,array_keys($stages));$event=null;foreach($history as $h) if(in_array($h['status'],$statuses,true)) $event=$h; ?><div class="<?= $level>=$n?'reached':'' ?>"><span><?= $level>=$n?'✓':$n+1 ?></span><strong><?= e($o['delivery_method']==='pickup'&&$n===2?'Ready / fulfillment':$label) ?></strong><small><?= $event?e(date('d M · g:i A',strtotime($event['created_at']))):($level>=$n?'Completed':'Pending') ?></small></div><?php endforeach; ?></div></section>
<div class="two-col wide-left"><section class="card pad"><div class="row-between"><h3>Purchased products</h3><small><?= array_sum(array_column($items,'qty')) ?> items</small></div><?php flow_items($items);flow_totals($o); ?></section><div class="stack"><section class="card pad"><h3>Shipping details</h3><strong><?= e($ship['recipient']??$o['customer_name']) ?></strong><p><?= e(implode(', ',array_filter([$ship['street']??'',$ship['barangay']??'',$ship['city']??'',$ship['province']??'',$ship['postal_code']??'']))) ?><br><?= e($ship['phone']??'') ?></p><hr><strong><?= $o['delivery_method']==='pickup'?'Café pickup':'Standard delivery' ?></strong></section><section class="card pad"><h3>Order information</h3><div class="line"><span>Placed</span><span><?= e(date('d M Y · g:i A',strtotime($o['created_at']))) ?></span></div><div class="line"><span>Payment method</span><span><?= ($pay['method']??'')==='cod'?'Cash on delivery':'Mock online payment' ?></span></div><div class="line"><?= status_badge(ucfirst($pay['status']??'pending')) ?><strong><?= money($o['total']) ?></strong></div></section></div></div>
<?php foreach($reqs as $r): ?><section class="card pad"><h3><?= e(ucfirst($r['type'])) ?> request <?= status_badge($r['status']) ?></h3><p><?= e($r['reason']) ?> · <?= e($r['admin_remarks']??'') ?></p></section><?php endforeach; ?><details class="card pad"><summary>Order activity</summary><ol class="timeline"><?php foreach($history as $h): ?><li><strong><?= e($h['status']) ?></strong><small><?= e($h['created_at']) ?> · <?= e($h['remarks']) ?></small></li><?php endforeach; ?></ol></details></div></div><?php page_footer();
