<?php

/**
 * RestTYPO3Plugin.php
 *
 * REST interface for the TYPO3 extension importstudip.
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License as
 * published by the Free Software Foundation; either version 2 of
 * the License, or (at your option) any later version.
 *
 * @author  Thomas Hackl <thomas.hackl@uni-passau.de>
 * @license http://www.gnu.org/licenses/gpl-2.0.html GPL version 2
 * @version 1.0
 */
class RestIDMPassauPlugin extends StudIPPlugin implements RESTAPIPlugin {

    public function getRouteMaps() {

        // Autoload models if required
        if (class_exists("StudipAutoloader")) {
            StudipAutoloader::addAutoloadPath(__DIR__ . '/models');
        } else {
            spl_autoload_register(function ($class) {
                include_once __DIR__ . $class . '.php';
            });
        }

        // Load all routes
        foreach (glob(__DIR__ . '/routes/*') as $filename) {
            require_once $filename;
            $classname = basename($filename, '.php');
            $routes[] = new $classname;
        }

        return $routes;
    }
}
