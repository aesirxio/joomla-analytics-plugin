<?php

$sql = [];

// Prepare and execute the query to add a new column 'fingerprint'
$sql[] = "ALTER TABLE `#__analytics_visitors` ADD `fingerprint` VARCHAR(255) NULL DEFAULT NULL FIRST;";

// Prepare and execute the query to add a unique index on the 'fingerprint' column
$sql[] = "ALTER TABLE `#__analytics_visitors` ADD UNIQUE `fingerprint_idx` (`fingerprint`);";
