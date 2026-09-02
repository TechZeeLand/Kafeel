<?php
require_once __DIR__ . '/../includes/admin_auth.php';
admin_logout();
redirect('/admin/login.php');
