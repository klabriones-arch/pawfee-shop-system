<?php
setcookie('pf_cart','',['expires'=>time()-3600,'path'=>'/','httponly'=>true,'samesite'=>'Lax']);
unset($_COOKIE['pf_cart']);
$_SESSION = [];
session_destroy();
session_start();
flash('success', 'You have been signed out.');
redirect(url('home'));
