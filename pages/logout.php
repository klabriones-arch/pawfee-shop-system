<?php
$_SESSION = [];
session_destroy();
session_start();
flash('success', 'You have been signed out.');
redirect(url('home'));
