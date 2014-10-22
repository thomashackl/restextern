<?php

namespace RESTAPI\Routes;

use \DBManager;

/**
 * UserData - TYPO3 routes for user related data, like search functions.
 *
 * @author Thomas Hackl <thomas.hackl@uni-passau.de>
 */

class UserData extends \RESTAPI\RouteMap {

    /**
     * Finds users matching the given search term.
     *
     * @get /typo3/usersearch/:searchterm
     */
    public function searchUsers($searchterm) {
        $users = array();
        $visible = array('yes', 'always');
        if (Config::get()->USER_VISIBILITY_UNKNOWN) {
            $visible[] = 'unknown';
        }
        $users = DBManager::get()->fetchAll("SELECT DISTINCT a.`user_id`, a.`username`, a.`Vorname` AS firstname, a.`Nachname` AS lastname, u.`title_front`, u.`title_rear`
            FROM `auth_user_md5` a
                INNER JOIN `user_info` u ON (a.`user_id`=u.`user_id`)
                INNER JOIN `user_inst` ui ON (a.`user_id`=ui.`user_id`)
            WHERE (a.`Vorname` LIKE :searchterm
                OR a.`Nachname` LIKE :searchterm
                OR a.`username` LIKE :searchterm
                OR CONCAT(a.`Vorname`, ' ', a.`Nachname`) LIKE :searchterm
                OR CONCAT(a.`Nachname`, ' ', a.`Vorname`) LIKE :searchterm)
                AND a.`visible` IN (:visible)
                AND ui.`inst_perms` != 'user'
            ORDER BY a.`Nachname`, a.`Vorname`, a.`username`",
            array('searchterm' => '%'.utf8_decode(urldecode($searchterm)).'%', 'visible' => $visible));
        return $users;
    }

}
