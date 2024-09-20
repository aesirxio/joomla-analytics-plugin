<?php

$sql = [];

// Add a primary key to the id column of the analytics_conversion table
$sql[] = "ALTER TABLE `#__analytics_conversion` ADD PRIMARY KEY(`id`);";