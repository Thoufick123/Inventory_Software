<?php
$f = 'c:/xampp/htdocs/test/Inventory_Software/app/Models/ProductionBatch.php';
$c = file_get_contents($f);
echo (strpos($c, "\r") !== false) ? "CRLF\n" : "LF\n";
