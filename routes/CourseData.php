<?php

namespace RESTAPI\Routes;

use \DBManager;

/**
 * CourseData - TYPO3 routes for course related data, like available course
 * types.
 *
 * @author Thomas Hackl <thomas.hackl@uni-passau.de>
 */

class CourseData extends \RESTAPI\RouteMap {

    /**
     * Returns avaible course types.
     *
     * @get /typo3/coursetypes/:institute
     * @get /typo3/coursetypes
     */
    public function getCourseTypes($institute='') {
        $types = array();
        if ($institute) {
            $types = DBManager::get()->fetchAll(
                "SELECT DISTINCT t.`id`, t.`name`, c.`name`
                FROM `sem_types` t
                    INNER JOIN `sem_classes` c ON (t.`class`=c.`id`)
                    INNER JOIN `seminare` s ON (s.`status`=t.`id`)
                    INNER JOIN `seminar_inst` si ON (s.`Seminar_id`=si.`seminar_id`)
                WHERE si.`institut_id`=?
                ORDER BY c.`id`, t.`name`", array($institute));
        } else {
            $types = DBManager::get()->fetchAll(
                "SELECT DISTINCT t.`id`, t.`name` AS type, c.`name` AS classname
                FROM `sem_types` t
                    INNER JOIN `sem_classes` c ON (t.`class`=c.`id`)
                ORDER BY c.`id`, t.`name`");
        }
        return $types;
    }


}
