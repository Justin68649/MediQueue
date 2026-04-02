<?php
$hash = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi';
var_dump(password_verify('Admin@123', $hash));
$hash2 = '$2y$10$BcGUTR1oZo0cd8HMvujM3.4DLo32fEdcr06PYaDH3IFMITM22V9Ae';
var_dump(password_verify('Patient@123', $hash2));
$hash3 = '$2y$10$9/EUqnQ6ui6wgXrxzKh5VeKiCJNcuaHglSzJrmlgCrqsDxiOyehl6';
var_dump(password_verify('Staff@123', $hash3));
?>