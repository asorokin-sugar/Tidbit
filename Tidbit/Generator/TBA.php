<?php

/*********************************************************************************
 * Tidbit is a data generation tool for the SugarCRM application developed by
 * SugarCRM, Inc. Copyright (C) 2004-2010 SugarCRM Inc.
 *
 * This program is free software; you can redistribute it and/or modify it under
 * the terms of the GNU Affero General Public License version 3 as published by the
 * Free Software Foundation with the addition of the following permission added
 * to Section 15 as permitted in Section 7(a): FOR ANY PART OF THE COVERED WORK
 * IN WHICH THE COPYRIGHT IS OWNED BY SUGARCRM, SUGARCRM DISCLAIMS THE WARRANTY
 * OF NON INFRINGEMENT OF THIRD PARTY RIGHTS.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT
 * ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS
 * FOR A PARTICULAR PURPOSE.  See the GNU Affero General Public License for more
 * details.
 *
 * You should have received a copy of the GNU Affero General Public License along with
 * this program; if not, see http://www.gnu.org/licenses or write to the Free
 * Software Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA
 * 02110-1301 USA.
 *
 * You can contact SugarCRM, Inc. headquarters at 10050 North Wolfe Road,
 * SW2-130, Cupertino, CA 95014, USA. or at email address contact@sugarcrm.com.
 *
 * The interactive user interfaces in modified source and object code versions
 * of this program must display Appropriate Legal Notices, as required under
 * Section 5 of the GNU Affero General Public License version 3.
 *
 * In accordance with Section 7(b) of the GNU Affero General Public License version 3,
 * these Appropriate Legal Notices must retain the display of the "Powered by
 * SugarCRM" logo. If the display of the logo is not reasonably feasible for
 * technical reasons, the Appropriate Legal Notices must display the words
 * "Powered by SugarCRM".
 ********************************************************************************/


class Tidbit_Generator_TBA
{
    /**
     * @var DBManager
     */
    private $db;

    /**
     * Counter of inserting objects.
     *
     * @var int
     */
    private $insertCounter = 0;

    /**
     * @var array
     */
    private $aclRoleIds = array();

    /**
     * @var Tidbit_StorageDriver_Storage_Abstract
     */
    private $storageAclFields;

    /**
     * @var Tidbit_StorageDriver_Storage_Abstract
     */
    private $storageAclRolesActions;

    /**
     * Using storage in db or not
     *
     * @var
     */
    private $storageTypeDb = true;

    /**
     * Constructor.
     *
     * @param DBManager $db
     * @param Tidbit_StorageDriver_Factory $factory
     * @param array $aclRoleIds
     */
    public function __construct(DBManager $db, Tidbit_StorageDriver_Factory $factory, array $aclRoleIds)
    {
        $this->db = $db;
        $this->aclRoleIds = $aclRoleIds;
        $this->storageAclFields = $factory->getDriver();
        $this->storageAclRolesActions = $factory->getDriver();
        $this->storageTypeDb = $factory->getStorageType() != Tidbit_StorageDriver_Factory::OUTPUT_TYPE_CSV;
    }

    /**
     * @return int
     */
    public function getInsertCounter()
    {
        return $this->insertCounter;
    }

    /**
     * Generate TBA Rules
     *
     * @param $roleActions
     * @param $tbaRestrictionLevel
     * @param $tbaFieldAccess
     */
    function generate($roleActions, $tbaRestrictionLevel, $tbaFieldAccess)
    {
        $actionsIds = $this->getActionIds($roleActions);
        $this->loadAclRoleIds();

        $dateModified = $this->db->convert("'" . $GLOBALS['timedate']->nowDb() . "'", 'datetime');

        foreach ($this->aclRoleIds as $roleId) {
            foreach ($roleActions as $moduleName) {
                $this->generateACLRoleActions($moduleName, $roleId, $actionsIds, $dateModified, $tbaRestrictionLevel);
                if ($tbaRestrictionLevel[$_SESSION['tba_level']]['fields']) {
                    $this->generateACLFields($moduleName, $roleId, $dateModified, $tbaFieldAccess, $tbaRestrictionLevel);
                }
            }
        }
    }

    /**
     * Generate and save queries for 'acl_roles_actions' table
     *
     * @param $moduleName
     * @param $id
     * @param $actionsIds
     * @param $dateModified
     * @param $tbaRestrictionLevel
     */
    private function generateACLRoleActions($moduleName, $id, $actionsIds, $dateModified, $tbaRestrictionLevel) {
        foreach ($tbaRestrictionLevel[$_SESSION['tba_level']]['modules'] as $action => $access_override) {
            if (!isset($actionsIds[$moduleName . '_' . $action])) {
                continue;
            }

            $relationshipData = array(
                'id' => "'" . create_guid() . "'",
                'role_id' => "'" . $id . "'",
                'action_id' => "'" . $actionsIds[$moduleName . '_' . $action] . "'",
                'access_override' => $access_override,
                'date_modified' => $dateModified,
            );

            $insertObject = new Tidbit_InsertObject('acl_roles_actions', $relationshipData);
            $this->storageAclRolesActions->saveByChunk($insertObject);
            $this->insertCounter++;
        }
    }

    /**
     * Generate and save queries for 'acl_fields' table
     *
     * @param $moduleName
     * @param $id
     * @param $dateModified
     * @param $tbaFieldAccess
     * @param $tbaRestrictionLevel
     */
    private function generateACLFields($moduleName, $id, $dateModified, $tbaFieldAccess, $tbaRestrictionLevel) {
        $beanACLFields = BeanFactory::getBean('ACLFields');
        $roleFields = $beanACLFields->getFields($moduleName, '', $id);
        foreach ($roleFields as $fieldName => $fieldValues) {
            if ($tbaRestrictionLevel[$_SESSION['tba_level']]['fields'] === 'required_only'
                && !$fieldValues['required']
            ) {
                continue;
            }
            //$date = trim($dateModified, "'");
            $insertData = array(
                'id' => "'" . md5($moduleName . $id . $fieldName) . "'",
                'date_entered' => $dateModified,
                'date_modified' => $dateModified,
                'name' => "'" .$fieldName .  "'",
                'category' => "'" . $moduleName .  "'",
                'aclaccess' => $tbaFieldAccess,
                'role_id' => "'" . $id .  "'",
            );

            $insertObject = new Tidbit_InsertObject('acl_fields', $insertData);
            $this->storageAclFields->saveByChunk($insertObject);
            $this->insertCounter++;
        }
    }

    /**
     * Loads and return action ids
     *
     * @param $roleActions
     * @return array
     */
    private function getActionIds($roleActions)
    {
        // Cache ACLAction IDs
        $queryACL = "SELECT id, category, name FROM acl_actions where category in ('"
            . implode("','", array_values($roleActions)) . "')";
        $resultACL = $this->db->query($queryACL);

        $actionsIds = array();

        // $actionsIds will contain keys like %category%_%name%
        while ($row = $this->db->fetchByAssoc($resultACL)) {
            $actionsIds[$row['category'] . '_' . $row['name']] = $row['id'];
        }
        return $actionsIds;
    }

    /**
     * Load AclRole ids from db
     */
    private function loadAclRoleIds()
    {
        // if storage isn't db we use just setted in constructor ids
        if (!$this->storageTypeDb) {
            return;
        }
        $this->aclRoleIds = array();
        $result = $this->db->query("SELECT id FROM acl_roles WHERE id LIKE 'seed-ACLRoles%'");
        while ($row = $this->db->fetchByAssoc($result)) {
            $this->aclRoleIds[] = $row['id'];
        }
    }
}
