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
    public function getCourseTypes($institute='')
    {
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
     * Returns the sem tree hierarchy, optionally starting at the given level
     * and to the given depth.
     *
     * @get /typo3/semtree/:parent/:depth/:selected
     * @get /typo3/semtree/:parent/:depth
     * @get /typo3/semtree/:parent
     * @get /typo3/semtree
     */
    public function getSemTree($parent_id = 'root', $depth=0, $selected='')
    {
        $tree = TreeAbstract::getInstance('StudipSemTree', array('visible_only' => 1));
        return self::buildTreeLevel($parent_id, $depth, $selected, $tree);
    }

    /**
     * Returns all available semester entries.
     *
     * @get /typo3/allsemesters
     */
    public function getAllSemesters()
    {
        return \Semester::getAll();
    }

    /**
     * Finds courses matching the given search term. The search range can be
     * restricted to a given semester.
     *
     * @get /typo3/coursesearch/:searchterm
     * @get /typo3/coursesearch/:searchterm/:semester_id
     */
    public function searchCourses($searchterm, $semester_id='')
    {
        $query = "SELECT s.`Seminar_id` AS course_id,
                s.`VeranstaltungsNummer` AS number, s.`Name` AS name,
                sd.`name` AS semester, t.`name` AS type
            FROM `seminare` s
                JOIN `semester_data` sd ON (s.`start_time` BETWEEN sd.`beginn` AND sd.`ende`)
                JOIN `sem_types` t ON (s.`status`=t.`id`)
            WHERE (s.`VeranstaltungsNummer` LIKE :searchterm OR s.`Name` LIKE :searchterm)
                AND s.`visible` = 1";
        $parameters = array(
            'searchterm' => '%'.utf8_decode(urldecode($searchterm)).'%'
        );
        if ($semester_id) {
            $query .= " AND ((s.`start_time`+s.`duration_time` >= sd.`beginn`)
                OR s.`duration_time` = -1)
                AND sd.`semester_id`=:semester";
            $parameters['semester'] = $semester_id;
        }
        $query .= " ORDER BY s.`start_time` DESC, number, name";
        return DBManager::get()->fetchAll($query, $parameters);
    }

    /**
     * Fetches the given course. There is already an identical route in the
     * core API, but we need less and other data here.
     *
     * @get /typo3/course/:course_id
     */
    public function getCourse($course_id) {
        $data = array();
        $c = \Course::find($course_id);
        if ($c->visible) {
            $type = $c->getSemType();
            $data = array(
                'course_id' => $c->id,
                'number' => $c->veranstaltungsnummer,
                'name' => $c->name,
                'type' => $type['name'],
                'semester' => $c->start_semester->name,
                'home_institute' => array(
                        'institute_id' => $c->home_institut->id,
                        'name' => $c->home_institut->name
                    )
            );
            foreach ($c->institutes as $i) {
                if ($i->id != $c->institut_id) {
                    $data['participating_institutes'][] = array('institute_id' => $i->id, 'name' => $i->name);
                }
            }
            usort($data['participating_institutes'], function ($a, $b) {
                return strnatcasecmp($a['name'], $b['name']);
            });
        }
        return $data;
    }

    /**
     * Recursively builds the tree structure of the sem tree hierarchy.
     *
     * @param  String          $parent_id       start item
     * @param  int             $depth           return $depth levels only
     * @param  String          $selected        selected element to include in result
     * @param  StudipSemTree   $tree            sem tree object
     * @param  int             $current_level   current level in recursion
     * @return array The tree structure of subjects of study.
     */
    private function buildTreeLevel($parent_id, $depth, $selected, &$tree, $current_level=0)
    {
        $level = array();
        if ($tree->getKids($parent_id)) {
            foreach ($tree->getKids($parent_id) as $kid) {
                $data = $tree->tree_data[$kid];
                $current = array(
                    'id' => $kid,
                    'name' => $data['name'],
                    'tree_id' => $kid,
                    'num_children' => sizeof($tree->getKids($kid))
                );
                /*
                 * We need to build tree recursively until the given depth is
                 * reached or we have found the full path to the selected
                 * element.
                 */
                if (!$depth || $current_level < $depth || ($selected && $tree->isChildOf($kid, $selected))) {
                    $current['children'] = self::buildTreeLevel($kid, $depth, $selected, $tree, $current_level+1);
                }
                $level[] = $current;
            }
        }
        return $level;
    }

}
