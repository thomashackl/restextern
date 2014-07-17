<?php

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
     * @get /typo3/externalpagetypes/:lang(/:institute_id)
     * @param String $lang language to get localized text from
     * @param String $institute_id an (optional) institute to narrow the search
     *                             focus to
     */
    public function getExternalPageTypes($lang, $institute_id='') {
        $types = array();
        $query = "SELECT DISTINCT `config_type` FROM `extern_config` ";
        $parameters = array();
        if ($institute_id) {
            $query .= "WHERE `range_id`=? ";
            $parameters[] = $institute_id;
        }
        $query .= "ORDER BY `config_type`";
        $data = DBManager::get()->fetchAll($query, $parameters);
        foreach ($data as $entry) {
            switch ($entry['config_type']) {
                case 3:
                case 8:
                case 12:
                case 15:
                    $types['courses'] = dgettext('resttypo3', 'Liste von Veranstaltungen');
                    break;
                case 4:
                case 13:
                    $types['coursedetails'] = dgettext('resttypo3', 'Einzelne Veranstaltung');
                    break;
                case 1:
                case 9:
                case 16:
                    $types['persons'] = dgettext('resttypo3', 'Liste von Personen');
                    break;
                case 2:
                case 14:
                    $types['persondetails'] = dgettext('resttypo3', 'Einzelne Person');
                    break;
                case 5:
                case 7:
                case 11:
                    $types['news'] = dgettext('resttypo3', 'Ankündigungen');
                    break;
                case 6:
                case 10:
                    $types['download'] = dgettext('resttypo3', 'Downloads');
                    break;
            }
        }
        return $types;
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
