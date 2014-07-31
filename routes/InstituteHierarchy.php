<?php

namespace RESTAPI\Routes;

use \Institute, \TreeAbstract;

/**
 * InstiuteHierarchyMap - TYPO3 routes for institute hierarchy related stuff
 *
 * @author Thomas Hackl <thomas.hackl@uni-passau.de>
 */

class InstituteHierarchy extends \RESTAPI\RouteMap {

    /**
     * Returns the institute hierarchy.
     *
     * @get /typo3/institutes
     */
    public function getInstituteHierarchy() {
        $institutes = array();
        // Get faculties.
        $faculties = Institute::findBySQL("`Institut_id`=`fakultaets_id` ORDER BY `Name`");
        foreach ($faculties as $faculty) {
            $data = array(
                'id' => $faculty->id,
                'name' => $faculty->name
            );
            $children = Institute::findByFaculty($faculty->id);
            if ($children) {
                foreach ($children as $c) {
                    $data['children'][] = array(
                        'id' => $c->id,
                        'name' => $c->name
                    );
                }
            }
            $institutes[] = $data;
        }
        return $institutes;
    }

    /**
     * Returns the range tree hierarchy.
     *
     * @get /typo3/rangetree
     */
    public function getRangeTree() {
        $tree = TreeAbstract::getInstance('StudipRangeTree', array('visible_only' => 1));
        return self::buildRangeTreeLevel('root', $tree);
    }

    /**
     * Recursively builds the tree structure of the range hierarchy.
     *
     * @param  String          $parent_id current level
     * @param  StudipRangeTree $tree      range tree object
     * @return array The tree structure of institutes and pseudo levels.
     */
    private function buildRangeTreeLevel($parent_id, &$tree) {
        $level = array();
        if ($tree->getKids($parent_id)) {
            foreach ($tree->getKids($parent_id) as $kid) {
                $data = $tree->tree_data[$kid];
                $current = array(
                    'id' => $data['studip_object_id'] ?: '',
                    'name' => $data['name'],
                    'tree_id' => $data['range_tree_id']
                );
                $current['children'] = self::buildRangeTreeLevel($kid, $tree);
                $level[] = $current;
            }
        }
        return $level;
    }

}
