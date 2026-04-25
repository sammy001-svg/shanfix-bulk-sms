<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('admin');
redirect('/admin/resellers.php?create=1');
