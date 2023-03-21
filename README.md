# joomla-analytics-plugin
Joomla plugin for tracking and storing the tracking data in the 1st party Aesirx Analytics server.

This plugin is for the latest Joomla form 4.2.9 and newer.

First you will need to set up the 1st party Analytics server.
The instructions are here [AesirX 1st Party Server](https://github.com/aesirxio/analytics-1stparty).

After you set up the server you will install the Joomla plugin and in the configuration you will need to enter
the URL of the 1st party Aesirx Analytics server example [http://example.com:1000/] and publish the plugin.

And this is all set. 
The tracking from your Joomla site will be stored in the Mongo database on the 1st party server.