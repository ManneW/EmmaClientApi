EmmaClientApi
=============

Experimental sandboxish attempt to convert the EmmaClient API (http://emmaclient.codeplex.com/) from "vanilla PHP" to using OOP and Silex.

Notice
======
The official EmmaClient can be found at http://emmaclient.codeplex.com.

Installation
============
At the moment, this repo only provides the refactored API and its dependencies. Download the full EmmaClient from http://emmaclient.codeplex.com first.

 1. Download EmmaClient from http://emmaclient.codeplex.com
 1. Clone this repo into another folder
 1. Copy the /web/api-folder from the cloned repo into the EmmaClient folder
 1. Use terminal, cd to the newly created api-folder and run "composer.phar install"
     * If not composer is installed, install it using "curl -s http://getcomposer.org/installer | php"
