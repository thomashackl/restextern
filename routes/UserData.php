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
        $users = DBManager::get()->fetchAll("SELECT DISTINCT a.`user_id`, a.`username`, a.`Vorname` AS firstname, a.`Nachname` AS lastname, u.`title_front`, u.`title_rear`
            FROM `auth_user_md5`
                INNER JOIN `user_info` u ON (a.`user_id`=u.`user_id`)
            WHERE a.`Vorname` LIKE :searchterm
                OR a.`Nachname` LIKE :searchterm
                OR a.`username` LIKE :searchterm
                OR CONCAT(a.`Vorname`, ' ', a.`Nachname`) LIKE :searchterm
                OR CONCAT(a.`Nachname`, ' ', a.`Vorname`) LIKE :searchterm
            ORDER BY a.`Nachname`, a.`Vorname`, a.`username`", 
            array('searchterm' => '%'.$searchterm.'%'));
        return $users;
    }

}
