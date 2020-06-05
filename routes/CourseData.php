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
     * Returns available course types.
     *
     * @get /extern/coursetypes/:institute
     * @get /extern/coursetypes
     */
    public function getCourseTypes($institute='')
    {
        $types = [];
        if ($institute) {
            $types = DBManager::get()->fetchAll(
                "SELECT DISTINCT t.`id`, t.`name` AS type, c.`name` AS classname, c.`id` AS typeclass
                FROM `sem_types` t
                    INNER JOIN `sem_classes` c ON (t.`class`=c.`id`)
                    INNER JOIN `seminare` s ON (s.`status`=t.`id`)
                    INNER JOIN `seminar_inst` si ON (s.`Seminar_id`=si.`seminar_id`)
                WHERE si.`institut_id`=?
                    AND c.`studygroup_mode` != 1
                ORDER BY c.`id`, t.`name`", [$institute]);
        } else {
            $types = DBManager::get()->fetchAll(
                "SELECT DISTINCT t.`id`, t.`name` AS type, c.`name` AS classname, c.`id` AS typeclass
                FROM `sem_types` t
                    INNER JOIN `sem_classes` c ON (t.`class`=c.`id`)
                WHERE c.`studygroup_mode` != 1
                ORDER BY c.`id`, t.`name`");
        }
        return $types;
    }


    /**
     * Returns the sem tree hierarchy, optionally starting at the given level
     * and to the given depth.
     *
     * @get /extern/semtree/:parent/:depth/:selected
     * @get /extern/semtree/:parent/:depth
     * @get /extern/semtree/:parent
     * @get /extern/semtree
     */
    public function getSemTree($parent_id = 'root', $depth=0, $selected='')
    {
        $tree = TreeAbstract::getInstance('StudipSemTree', ['visible_only' => 1]);
        return self::buildTreeLevel($parent_id, $depth, $selected, $tree);
    }

    /**
     * Returns all available semester entries.
     *
     * @get /extern/allsemesters
     */
    public function getAllSemesters()
    {
        $semesters = \Semester::getAll();

        return array_map(function ($s) use ($semesters) {
            return [
                'semester_id' => $s->semester_id,
                'name' => (string) $s->name,
                'description' => (string) $s->description,
                'semester_token' => (string) $s->semester_token,
                'beginn' => $s->beginn,
                'ende' => $s->ende,
                'vorles_beginn' => $s->vorles_beginn,
                'vorles_ende' => $s->vorles_ende,
                'visible' => $s->visible,
                'first_sem_week' => $s->first_sem_week,
                'last_sem_week' => $s->last_sem_week,
                'current' => $s->current,
                'past' => $s->past
            ];
        }, $semesters);
    }

    /**
     * Finds courses matching the given search term. The search range can be
     * restricted to a given semester.
     *
     * @get /extern/coursesearch/:searchterm/:semester_id
     * @get /extern/coursesearch/:searchterm
     */
    public function searchCourses($searchterm, $semester_id='')
    {
        $query = "SELECT s.`Seminar_id` AS course_id,
                s.`VeranstaltungsNummer` AS number, s.`Name` AS name,
                sd.`name` AS semester, t.`name` AS type
            FROM `seminare` s
                JOIN `semester_data` sd ON (s.`start_time` BETWEEN sd.`beginn` AND sd.`ende`)
                JOIN `sem_types` t ON (s.`status` = t.`id`)
                JOIN `seminar_user` su ON (su.`Seminar_id` = s.`Seminar_id` AND su.`status`='dozent')
                JOIN `auth_user_md5` a ON (a.`user_id` = su.`user_id`)
            WHERE (s.`VeranstaltungsNummer` LIKE :searchterm
                    OR s.`Name` LIKE :searchterm
                    OR a.`Vorname` LIKE :searchterm
                    OR a.`Nachname` LIKE :searchterm
                    OR CONCAT_WS(' ', a.`Vorname`, a.`Nachname`) LIKE :searchterm
                    OR CONCAT_WS(' ', a.`Nachname`, a.`Vorname`) LIKE :searchterm)
                AND s.`visible` = 1
                AND s.`status` NOT IN (:studygroups)";
        $parameters = [
            'searchterm' => '%'.urldecode($searchterm).'%',
            'studygroups' => studygroup_sem_types() ?: []
        ];
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
     * Finds courses matching the given search term. The search range can be
     * restricted to a given semester, an institute or a course type.
     * More data is returned than in searchCourses.
     *
     * @get /extern/extendedcoursesearch/:searchterm/:semester_id/:institute_id/:coursetype
     * @get /extern/extendedcoursesearch/:searchterm/:semester_id/:institute_id
     * @get /extern/extendedcoursesearch/:searchterm/:semester_id
     * @get /extern/extendedcoursesearch/:searchterm
     */
    public function extendedCourseSearch($searchterm, $semester_id='', $institute_id='', $coursetype='')
    {
        $select = "SELECT DISTINCT s.*";
        $from = " FROM `seminare` s
            JOIN `seminar_user` su ON (su.`Seminar_id` = s.`Seminar_id` AND su.`status`='dozent')
            JOIN `auth_user_md5` a ON (a.`user_id` = su.`user_id`)
            ";
        $where = " WHERE (s.`VeranstaltungsNummer` LIKE :searchterm
                OR s.`Name` LIKE :searchterm
                OR s.`Untertitel` LIKE :searchterm
                OR a.`Vorname` LIKE :searchterm
                OR a.`Nachname` LIKE :searchterm
                OR CONCAT_WS(' ', a.`Vorname`, a.`Nachname`) LIKE :searchterm
                OR CONCAT_WS(' ', a.`Nachname`, a.`Vorname`) LIKE :searchterm)
            AND s.`visible` = 1
            AND s.`status` NOT IN (:excludetypes)";
        $parameters = [
            'searchterm' => '%'.urldecode($searchterm).'%',
            'excludetypes' => studygroup_sem_types() ?: []
        ];
        if ($semester_id) {
            $from .= " JOIN `semester_data` sd ON (
                (s.`duration_time` != -1 AND s.`start_time` + s.`duration_time` BETWEEN sd.`beginn` AND sd.`ende`)
                OR
                (s.`duration_time` = -1 AND s.`start_time` <= sd.`ende`))";
            $where .= " AND sd.`semester_id`=:semester";
            $parameters['semester'] = $semester_id;
            $order = " ORDER BY s.`VeranstaltungsNummer`, s.`Name`";
        } else {
            $order = " ORDER BY s.`start_time` DESC, s.`VeranstaltungsNummer`, s.`Name`";
        }
        if ($institute_id) {
            $from .= " JOIN `seminar_inst` si ON (s.`Seminar_id`=si.`seminar_id`)";
            $where .= " AND si.`institut_id`=:institute";
            $parameters['institute'] = $institute_id;
        }
        if ($coursetype) {
            $from .= " JOIN `sem_types` t ON (s.`status`=t.`id`)";
            $where .= " AND s.`status`=:coursetype";
            $parameters['coursetype'] = $coursetype;
        }
        $query = $select.$from.$where.$order;
        $data = DBManager::get()->fetchAll($query, $parameters, 'Course::buildExisting');

        $courses = [];
        foreach ($data as $c) {
            $type = $c->getSemType();
            $course = [
                'id' => $c->id,
                'number' => $c->veranstaltungsnummer,
                'name' => ($c->name instanceof \I18NString) ? $c->name->original() : $c->name,
                'subtitle' => ($c->untertitel instanceof \I18NString) ? $c->untertitel->original() : $c->untertitel,
                'type' => $GLOBALS['SEM_TYPE'][$c->status]['name'],
                'lecturers' => []
            ];
            foreach (\SimpleORMapCollection::createFromArray($c->getMembersWithStatus('dozent'))->orderBy('position') as $l) {
                $course['lecturers'][] = [
                    'id' => $l->id,
                    'firstname' => $l->vorname,
                    'lastname' => $l->nachname,
                    'username' => $l->username
                ];
            }
            $courses[] = $course;
        }
        return $courses;
    }

    /**
     * Fetches the given course. There is already an identical route in the
     * core API, but we need less and other data here.
     *
     * @get /extern/course/:course_id
     */
    public function getCourse($course_id) {
        $data = [];
        $c = \Course::find($course_id);
        if ($c->visible) {
            $type = $c->getSemType();
            $data = [
                'course_id' => $c->id,
                'number' => $c->veranstaltungsnummer,
                'name' => ($c->name instanceof \I18NString) ? $c->name->original() : $c->name,
                'type' => $type['name'],
                'semester' => $c->start_semester->name,
                'home_institute' => [
                        'institute_id' => $c->home_institut->id,
                        'name' => ($c->home_institut->name instanceof \I18NString) ?
                            $c->home_institut->name->original() : $c->home_institut->name
                    ],
                'participating_institutes' => []
            ];
            foreach ($c->institutes as $i) {
                if ($i->id != $c->institut_id) {
                    $data['participating_institutes'][] = ['institute_id' => $i->id,
                        'name' => ($i->name instanceof \I18NString) ? $i->name->original() : $i->name];
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
        $level = [];
        $kids = $tree->getKids($parent_id);
        if (is_array($kids) && count($kids) > 0) {
            foreach ($kids as $kid) {
                $data = $tree->tree_data[$kid];

                $kidskids = $tree->getKids($kid);

                $current = [
                    'id' => $kid,
                    'name' => $data['name'],
                    'tree_id' => $kid,
                    'num_children' => is_array($kidskids) ? count($kidskids) : 0
                ];
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
