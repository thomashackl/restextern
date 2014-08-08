<?php

namespace RESTAPI\Routes;

use \DBManager, \TreeAbstract;

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
                "SELECT DISTINCT t.`id`, t.`name` AS type, c.`name` AS classname
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


    /**
     * Returns the sem tree hierarchy, optionally starting at the given level.
     *
     * @get /typo3/semtree/:parent
     * @get /typo3/semtree
     */
    public function getSemTree($parent_id = 'root') {
        $tree = TreeAbstract::getInstance('StudipSemTree', array('visible_only' => 1));
        return self::buildTreeLevel($parent_id, $tree);
    }

    /**
     * Recursively builds the tree structure of the sem tree hierarchy.
     *
     * @param  String          $parent_id current level
     * @param  StudipSemTree   $tree      sem tree object
     * @return array The tree structure of subjects of study.
     */
    private function buildTreeLevel($parent_id, &$tree) {
        $level = array();
        if ($tree->getKids($parent_id)) {
            foreach ($tree->getKids($parent_id) as $kid) {
                $data = $tree->tree_data[$kid];
                $current = array(
                    'id' => $kid,
                    'name' => $data['name'],
                    'tree_id' => $kid
                );
                $current['children'] = self::buildTreeLevel($kid, $tree);
                $level[] = $current;
            }
        }
        return $level;
    }

}
