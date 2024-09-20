<?php

$sql = [];

// Add a new column 'region' to the analytics_visitors table
$sql[] = "ALTER TABLE `#__analytics_visitors` ADD `region` VARCHAR(255) NULL DEFAULT NULL;";

// Update geo_created_at to NULL where city is not empty and is not NULL
$sql[] = "UPDATE `#__analytics_visitors` SET geo_created_at = NULL WHERE city != '' AND city IS NOT NULL;";
