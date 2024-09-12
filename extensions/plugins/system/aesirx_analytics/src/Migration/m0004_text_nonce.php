<?php

$sql = [];

// Prepare the SQL query to change the column type and default value
$sql[] = "ALTER TABLE `#__analytics_wallet` CHANGE `nonce` `nonce` VARCHAR(255) NULL DEFAULT NULL;";
