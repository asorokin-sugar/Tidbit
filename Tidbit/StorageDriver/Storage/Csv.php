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

require_once('Tidbit/Tidbit/StorageDriver/Storage/Abstract.php');

class Tidbit_StorageDriver_Storage_Csv extends Tidbit_StorageDriver_Storage_Abstract {

    /**
     * @var string
     */
    const STORE_TYPE = Tidbit_StorageDriver_Factory::OUTPUT_TYPE_CSV;

    /**
     * Here this value for number of strings in file
     */
    const STORE_CHUNK_SIZE_DEFAULT = 10000;

    /**
     * Handler for current storage file
     *
     * @var Resource
     */
    private $currentStoreFile = null;

    /**
     * Current chunk number
     *
     * @var int
     */
    private $currentChunkNumber = 0;

    /**
     * Counter of saved objects
     *
     * @var int
     */
    private $savedInChunkObjects = 0;

    /**
     * Saves data from tool to file
     *
     * @param Tidbit_InsertObject $insertObject
     */
    public function save(Tidbit_InsertObject $insertObject)
    {
        $this->prepareToSave($insertObject);
        $this->makeSave();
    }

    /**
     * Make save if we collect full chunk of data
     *
     * @param Tidbit_InsertObject $insertObject
     */
    public function saveByChunk(Tidbit_InsertObject $insertObject)
    {
        if ($this->savedInChunkObjects >= $this->storeChunkSize) {
            fclose($this->currentStoreFile);
            $this->currentChunkNumber++;
            $this->savedInChunkObjects = 0;
        }
        $this->save($insertObject);
    }

    /**
     * {@inheritdoc}
     *
     * @param Tidbit_InsertObject $insertObject
     */
    protected function prepareToSave(Tidbit_InsertObject $insertObject)
    {
        if (!is_resource($this->currentStoreFile)) {
            $fileName = $this->getCurrentFilePathName($insertObject->tableName);
            $this->currentStoreFile = fopen($fileName, 'a');
            $this->head = $this->prepareCsvString(array_keys($insertObject->installData));
            // write head here because it must be only in the 1st string
            fwrite($this->currentStoreFile, $this->head);
        }

        $this->values = $this->prepareCsvString($insertObject->installData);
    }

    /**
     * {@inheritdoc}
     *
     */
    protected function makeSave()
    {
        if (!$this->head || !$this->values) {
            throw new Tidbit_Exception("Csv driver error: wrong data to save");
        }

        // just write into values str file
        fwrite($this->currentStoreFile, $this->values);
        $this->savedInChunkObjects++;
        $this->values = [];
    }

    /**
     * Remove spaces and wrap to quotes values in the list
     *
     * @param array $values
     * @return string
     */
    protected function prepareCsvString(array $values)
    {
        foreach ($values as $k => $v) {
            $values[$k] = trim($v);
        }
        return join(',', $values) . "\n";
    }

    /**
     * Return full path to file for data storing
     *
     * @param string $tableName
     * @return string
     * @throws Tidbit_Exception
     */
    protected function getCurrentFilePathName($tableName)
    {
        if (!$this->storageResource
            || !is_string($this->storageResource)
            || !file_exists($this->storageResource)
        ) {
            throw new Tidbit_Exception(
                "For csv generation storageResource must be string with path to saving directory"
            );
        }
        $fileName = $this->storageResource . '/' . $tableName . '.' . $this->currentChunkNumber . '.csv';
        if (file_exists($fileName)) {
            $this->currentChunkNumber++;
            return $this->getCurrentFilePathName($tableName);
        } else {
            return $fileName;
        }
    }

    /**
     * Destructor
     *
     */
    public function __destruct()
    {
        parent::__destruct();
        if ($this->currentStoreFile) {
            fclose($this->currentStoreFile);
        }
    }

    /**
     * Stubbed for csv
     */
    public function commitQuery()
    {

    }

    /**
     * Stubbed for csv
     *
     * @param string $query
     * @param bool $quote
     */
    protected function executeQuery($query, $quote = true)
    {

    }
}
