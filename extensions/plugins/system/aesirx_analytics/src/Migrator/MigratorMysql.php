<?php
namespace AesirxAnalytics\Migrator;

use Joomla\CMS\Factory;

class MigratorMysql {

    public static function aesirx_analytics_create_migrator_table_query() {
        // Get the Joomla database object
        $db = Factory::getDbo();
    
        // Prepare the SQL query
        $query = "
            CREATE TABLE IF NOT EXISTS #__analytics_migrations (
                id INT PRIMARY KEY NOT NULL AUTO_INCREMENT,
                app VARCHAR(384) NOT NULL,
                name VARCHAR(384) NOT NULL,
                applied_time TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE (app, name)
            )
        ";
    
        // Set and execute the query
        $db->setQuery($query);
        
        try {
            $db->execute();
        } catch (Exception $e) {
            // Handle any errors
            Factory::getApplication()->enqueueMessage('Error creating migrations table: ' . $e->getMessage(), 'error');
        }
    }

    public static function aesirx_analytics_fetch_rows() {
        // Get the Joomla database object
        $db = Factory::getDbo();

        // Prepare the query to select the 'name' field from the 'analytics_migrations' table
        $query = $db->getQuery(true)
                    ->select($db->quoteName('name'))
                    ->from($db->quoteName('#__analytics_migrations'));

        // Execute the query
        $db->setQuery($query);

        // Fetch the results
        $results = $db->loadAssocList();

        return $results;
    }

    public static function aesirx_analytics_add_migration_query($name) {
        // Get the Joomla database object
        $db = Factory::getDbo();
    
        // Prepare the insert query
        $query = $db->getQuery(true);
    
        // Define the columns and values to be inserted
        $columns = array('app', 'name');
        $values = array($db->quote('main'), $db->quote($name));
    
        // Build the query
        $query
            ->insert($db->quoteName('#__analytics_migrations'))
            ->columns($db->quoteName($columns))
            ->values(implode(',', $values));
    
        // Execute the query
        try {
            $db->setQuery($query);
            $db->execute();
        } catch (Exception $e) {
            // Handle any errors
            Factory::getApplication()->enqueueMessage('Error inserting migration record: ' . $e->getMessage(), 'error');
        }
    }
}