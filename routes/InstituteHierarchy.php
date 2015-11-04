<?php

namespace RESTAPI\Routes;

use \DBManager, \Institute, \TreeAbstract;

/**
 * InstiuteHierarchyMap - TYPO3 routes for institute hierarchy related stuff
 *
 * @author Thomas Hackl <thomas.hackl@uni-passau.de>
 */

class InstituteHierarchy extends \RESTAPI\RouteMap {

    /**
     * Fetches the given institute.
     *
     * @get /extern/institute/:institute_id
     */
    public function getInstitute($institute_id) {
        $data = array();
        $i = \Institute::find($institute_id);
        $data = array(
            'institute_id' => $i->id,
            'name' => $i->name,
        );
        return $data;
    }

    /**
     * Returns the institute hierarchy.
     *
     * @get /extern/institutes/:externtypes
     * @get /extern/institutes
     */
    public function getInstituteHierarchy($externtypes='') {
        $institutes = array();
        // Pseudo root node, needed for globally available extern configurations.
        if ($externtypes) {
            $extern = (sizeof(DBManager::get()->fetchFirst(
                    "SELECT `config_id` FROM `extern_config` WHERE `range_id`='studip' AND `config_type` IN (?)",
                    array(explode(',', $externtypes)))) > 0);
        } else {
            $extern = true;
        }
        $root = array(
            'id' => 'studip',
            'name' => $GLOBALS['UNI_NAME_CLEAN'],
            'children' => array(),
            'selectable' => $extern
        );
        // Get faculties.
        $faculties = Institute::findBySQL("`Institut_id`=`fakultaets_id` ORDER BY `Name`");
        foreach ($faculties as $faculty) {
            if ($externtypes) {
                $extern = (sizeof(DBManager::get()->fetchFirst(
                        "SELECT `config_id` FROM `extern_config` WHERE `range_id`=? AND `config_type` IN (?)",
                        array($faculty->id, explode(',', $externtypes)))) > 0);
            } else {
                $extern = true;
            }
            $data = array(
                'id' => $faculty->id,
                'name' => $faculty->name,
                'selectable' => $extern
            );
            $children = Institute::findByFaculty($faculty->id);
            if ($children) {
                foreach ($children as $c) {
                    if ($externtypes) {
                        $extern = (sizeof(DBManager::get()->fetchFirst(
                                "SELECT `config_id` FROM `extern_config` WHERE `range_id`=? AND `config_type` IN (?)",
                            array($c->id, explode(',', $externtypes)))) > 0);
                    } else {
                        $extern = true;
                    }
                    $data['children'][] = array(
                        'id' => $c->id,
                        'name' => $c->name,
                        'selectable' => $extern
                    );
                }
            }
            $root['children'][] = $data;
        }
        $institutes[] = $root;
        return $institutes;
    }

    /**
     * Returns the range tree hierarchy.
     *
     * @get /extern/rangetree/:externtypes
     * @get /extern/rangetree
     */
    public function getRangeTree($externtypes='') {
        $tree = TreeAbstract::getInstance('StudipRangeTree', array('visible_only' => 1));
        if ($externtypes) {
            $extern = (sizeof(DBManager::get()->fetchFirst(
                "SELECT `config_id` FROM `extern_config` WHERE `range_id`='studip' AND `config_type` IN (?)",
                array(explode(',', $externtypes)))) > 0);
        } else {
            $extern = true;
        }
        $root = array(
            'id' => 'studip',
            'tree_id' => 'root',
            'name' => $GLOBALS['UNI_NAME_CLEAN'],
            'children' => self::buildRangeTreeLevel('root', $tree, $externtypes),
            'selectable' => $extern
        );
        return array($root);
    }

    /**
     * Returns all statusgroup names found at the given institute
     * (and sublevels if configured). The statusgroup ID is not considered.
     *
     * @get /extern/statusgroupnames/:institute/:aggregate
     * @get /extern/statusgroupnames/:institute
     */
    public function getStatusgroupNames($institute, $aggregate=false) {
        if ($aggregate) {
            $ids = DBManager::get()->fetchAll("SELECT `Institut_id` FROM `Institute` WHERE `fakultaets_id`=?", array($institute));
        } else {
            $ids = array($institute);
        }
        $groups = DBManager::get()->fetchAll("SELECT DISTINCT `statusgruppe_id`, `name` FROM `statusgruppen` WHERE `range_id` IN (?) ORDER BY `name`", array($ids));
        return array_keys(self::getStatusgroupChildren($ids, true));
    }

    /**
     * Recursively builds the tree structure of the range hierarchy.
     *
     * @param  String          $parent_id current level
     * @param  StudipRangeTree $tree      range tree object
     * @return array The tree structure of institutes and pseudo levels.
     */
    private function buildRangeTreeLevel($parent_id, &$tree, $externtypes) {
        $level = array();
        if ($tree->getKids($parent_id)) {
            foreach ($tree->getKids($parent_id) as $kid) {
                $data = $tree->tree_data[$kid];
                if ($externtypes && $data['studip_object_id']) {
                    $extern = DBManager::get()->fetchFirst(
                        "SELECT `config_id` FROM `extern_config` WHERE `range_id`=? AND `config_type` IN (?)",
                        array($data['studip_object_id'], explode(',', $externtypes)));
                } else {
                    if ($data['studip_object_id']) {
                        $extern = true;
                    } else {
                        $extern = false;
                    }
                }
                $current = array(
                    'id' => $data['studip_object_id'] ?: '',
                    'name' => $data['name'],
                    'tree_id' => $kid,
                    'selectable' => $extern
                );
                $current['children'] = self::buildRangeTreeLevel($kid, $tree, $externtypes);
                $level[] = $current;
            }
        }
        return $level;
    }

    /**
     * Recursively finds all statusgroups (indirectly) belonging to the given
     * set of IDs.
     *
     * @param String $ids       parent IDs to check
     * @param bool   $name_only are only the group names required?
     * @return array the groups (or just their names) belonging to the given IDs
     */
    private function getStatusgroupChildren($ids, $name_only=false) {
        $result = array();
        $children = DBManager::get()->fetchAll("SELECT `statusgruppe_id`, `name`, `range_id` FROM `statusgruppen` WHERE `range_id` IN (?) ORDER BY `name`", array($ids));
        if ($children) {
            foreach ($children as $child) {
                if ($name_only) {
                    if ($child['name']) {
                        $result[$child['name']] = true;
                    }
                } else {
                    $result[] = $child;
                }
            }
            $result = array_merge($result, self::getStatusgroupChildren(array_map(function($e){
                return $e['statusgruppe_id'];
            }, $children), $name_only));
        }
        return $result;
    }

}
