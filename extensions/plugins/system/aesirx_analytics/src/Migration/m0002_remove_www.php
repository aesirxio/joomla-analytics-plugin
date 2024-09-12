<?php

global $wpdb;

$sql = [];

// Prepare the query with placeholders
$sql[] = 
"UPDATE `#__analytics_visitors`
SET domain = SUBSTRING(domain, LOCATE('www.', domain) + LENGTH('www.'))
WHERE domain LIKE 'www.%';";
