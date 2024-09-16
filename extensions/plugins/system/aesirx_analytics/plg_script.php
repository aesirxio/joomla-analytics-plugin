<?php
// Prevent direct access
defined('_JEXEC') or die;

use Aesirx\System\AesirxAnalytics\Migrator\MigratorMysql;
use Joomla\CMS\Factory;

/**
 * Script file for the plg_system_aesirx_analytics plugin
 */
class plgSystemAesirxAnalyticsInstallerScript
{
    /**
     * Method to install the plugin
     * 
     * @param \JInstallerAdapter $parent The parent installer object
     * @return void
     */
    public function install($parent)
    {
        // Call the initialization function
        $this->aesirx_analytics_initialize_function();

    }

    /**
     * Initialize the plugin
     */
    protected function aesirx_analytics_initialize_function()
    {
        $db = Factory::getDbo();

        // Add migration table
        MigratorMysql::aesirx_analytics_create_migrator_table_query();

        // Fetch existing migrations
        $migration_list = array_column(MigratorMysql::aesirx_analytics_fetch_rows(), 'name');

        // Get migration files from src/Migration directory
        $files = glob(__DIR__ . '/src/Migration/*.php');
        
        foreach ($files as $file) {
            include_once $file;
            $file_name = basename($file, ".php");

            // Only run migration if it's not already applied
            if (!in_array($file_name, $migration_list)) {
                MigratorMysql::aesirx_analytics_add_migration_query($file_name);

                foreach ($sql as $each_query) {
                    // Execute the migration queries
                    $db->setQuery($each_query);
                    $db->execute();
                }
            }
        }
    }

    /**
     * Method to update the plugin
     * 
     * @param \JInstallerAdapter $parent The parent installer object
     * @return void
     */
    public function update($parent)
    {
        // Actions to perform during an update
        $this->aesirx_analytics_initialize_function();
    }
}