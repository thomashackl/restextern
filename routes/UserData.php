<?php

namespace RESTAPI\Routes;

use \DBManager;

/**
 * UserData - TYPO3 routes for user related data, like search functions.
 *
 * @author Thomas Hackl <thomas.hackl@uni-passau.de>
 */

class UserData extends \RESTAPI\Routes\User {

    /**
     * Gets data for the given user. The difference to the core function is
     * that we get a username instead of an ID here.
     *
     * @get /extern/user/:username
     */
    public function getExternUser($username)
    {
        $user = $username ? \User::findByUsername($username) : $GLOBALS['user'];
        return parent::getUser($user->id);
    }

    /**
     * Fetches the given user's institutes. The difference to the core
     * function with the same name is that we need a username instead of an
     * ID here.
     *
     * @get /extern/user_institutes/:username
     */
    public function getInstitutes($username)
    {
        $query = "SELECT i0.Institut_id AS institute_id, i0.Name AS name,
                         inst_perms AS perms, sprechzeiten AS consultation,
                         raum AS room, ui.telefon AS phone, ui.fax,
                         i0.Strasse AS street, i0.Plz AS city,
                         i1.Name AS faculty_name, i1.Strasse AS faculty_street,
                         i1.Plz AS faculty_city
                  FROM user_inst AS ui
                  JOIN auth_user_md5 a USING (user_id)
                  JOIN Institute AS i0 USING (Institut_id)
                  LEFT JOIN Institute AS i1 ON (i0.fakultaets_id = i1.Institut_id)
                  WHERE ui.visible = 1 AND a.username = :username
                  ORDER BY priority ASC";
        $statement = \DBManager::get()->prepare($query);
        $statement->bindValue(':username', $username);
        $statement->execute();

        $institutes = array(
            'work'  => array(),
            'study' => array(),
        );

        foreach ($statement->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            if ($row['perms'] === 'user') {
                $institutes['study'][] = $row;
            } else {
                $institutes['work'][] = $row;
            }
        }

        $this->etag(md5(serialize($institutes)));

        $result = array_slice($institutes, $this->offset, $this->limit);
        return $this->paginated($result, count($institutes), compact('username'));
    }

    /**
     * Finds users matching the given search term.
     *
     * @get /extern/usersearch/:searchterm
     */
    public function searchUsers($searchterm)
    {
        $users = array();
        $visible = array('yes', 'always');
        if (\Config::get()->USER_VISIBILITY_UNKNOWN) {
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
            array('searchterm' => '%'.urldecode($searchterm).'%', 'visible' => $visible));
        return $users;
    }

}
