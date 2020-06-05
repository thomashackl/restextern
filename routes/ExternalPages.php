<?php

/**
 * ExternalPagesMap - TYPO3 routes for external pages and configurations
 * related stuff
 *
 * @author Thomas Hackl <thomas.hackl@uni-passau.de>
 */

namespace RESTAPI\Routes;

use \DBManager, \Request;

require_once($GLOBALS['STUDIP_BASE_PATH'] . '/lib/extern/extern_config.inc.php');

class ExternalPages extends \RESTAPI\RouteMap {

    /**
     * Returns all configured external page types.
     *
     * @get /extern/externalpagetypes
     * @param String $institute_id an (optional) institute to narrow the search
     *                             focus to
     */
    public function getExternalPageTypes($institute_id='')
    {
        $types = [];
        $query = "SELECT DISTINCT `config_type` FROM `extern_config` ";
        $parameters = [];
        if (Request::option('institute_id')) {
            $query .= "WHERE `range_id`=? ";
            $parameters[] = $institute_id;
        }
        $query .= "ORDER BY `config_type`";
        $configs = DBManager::get()->fetchFirst($query, $parameters);

        /*
         * Add a pseudo page type if the Phonebook plugin is present.
         * The type code is 555 as hommage to the pseudo American numbers in movies.
         */
        if (class_exists('Phonebook')) {
            $configs[] = '555';
        }

        return $configs;
    }

    /**
     * Returns all configurations for external pages that belong to the given
     * institute.
     *
     * @get /extern/externconfigs/:institute_id/:types
     * @get /extern/externconfigs/:institute_id
     */
    public function getExternalPageConfigurations($institute_id, $types='')
    {
        $configs = [];
        $query = "SELECT `config_id`, `name`, `config_type`
            FROM `extern_config` WHERE `range_id`=?";
        $parameters = [$institute_id];
        if ($types) {
            $query .= " AND `config_type` IN (?)";
            $parameters[] = [explode(',', $types)];
        }
        $query .= "ORDER BY `config_type`, `name`";
        $data = DBManager::get()->fetchAll($query, $parameters);
        foreach ($data as $entry) {
            $configs[] = [
                'id' => $entry['config_id'],
                'name' => $entry['name'],
                'type' => $GLOBALS['EXTERN_MODULE_TYPES'][$entry['config_type']]['module']
            ];
        }
        return $configs;
    }

    /**
     * Returns metadata about a given external page configuration.
     *
     * @get /extern/externconfig/:config_id
     */
    public function getExternalPageConfiguration($config_id)
    {
        return DBManager::get()->fetchOne("SELECT `config_id`, `range_id`, `config_type`, `name`, `is_standard`
            FROM `extern_config`
            WHERE `config_id` = ?
            LIMIT 1",
            [$config_id]);
    }

}
