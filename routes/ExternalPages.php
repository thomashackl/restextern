<?php

namespace RESTAPI\Routes;

use \DBManager;

/**
 * ExternalPagesMap - TYPO3 routes for external pages and configurations
 * related stuff
 *
 * @author Thomas Hackl <thomas.hackl@uni-passau.de>
 */

class ExternalPagesMap extends RESTAPI\RouteMap {

    /**
     * Returns all configured external page types.
     *
     * @get /typo3/externalpagetypes
     * @param String $institute_id an (optional) institute to narrow the search
     *                             focus to
     */
    public function getExternalPageTypes($institute_id='') {
        $types = array();
        $query = "SELECT DISTINCT `config_type` FROM `extern_config` ";
        $parameters = array();
        if ($institute_id) {
            $query .= "WHERE `range_id`=? ";
            $parameters[] = $institute_id;
        }
        $query .= "ORDER BY `config_type`";
        return DBManager::get()->fetchFirst($query, $parameters);
    }

    /**
     * Returns all configurations for external pages that belong to the given
     * institute.
     *
     * @get /typo3/externconfig/:institute_id
     */
    public function getExternalPageConfigurations($institute_id) {
        $configs = array();
        $data = DBManager::get()->fetchAll("SELECT `config_id`, `name`, `type`
            FROM `extern_config` WHERE `range_id`=?
            ORDER BY `type`, `name`", array($institute_id));
        foreach ($data as $entry) {
            $configs[] = array(
                'id' => $entry['config_id'],
                'name' => $entry['name'],
                'type' => $GLOBALS['EXTERN_MODULE_TYPES'][$entry['type']]['module']
            );
        }
        return $configs;
    }

}
