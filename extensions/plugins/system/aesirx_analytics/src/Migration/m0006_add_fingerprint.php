<?php

$sql = [];

// Prepare and execute the query to drop the existing unique index 'fingerprint_idx'
$sql[] = "ALTER TABLE `#__analytics_visitors` DROP INDEX `fingerprint_idx`;";

// Prepare and execute the query to add a new unique index on the 'fingerprint' and 'domain' columns
$sql[] = "ALTER TABLE `#__analytics_visitors` ADD UNIQUE `fingerprint_domain_idx` (`fingerprint`, `domain`);";
