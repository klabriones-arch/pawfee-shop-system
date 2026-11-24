<?php
if (!is_post()) { http_response_code(405); exit('Use the Sign out button.'); }
csrf_check();
if (!ADMIN_SESSION) { setcookie('pf_cart','',['expires'=>time()-3600,'path'=>'/','httponly'=>true,'samesite'=>'Lax']); unset($_COOKIE['pf_cart']); }
$_SESSION = [];
session_regenerate_id(true);
flash('success', 'You have been signed out.');
redirect(ADMIN_SESSION ? url('login') : url('home'));
