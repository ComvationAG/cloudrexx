<?php
/**
 * Cloudrexx
 *
 * @link      http://www.cloudrexx.com
 * @copyright Cloudrexx AG 2007-2015
 *
 * According to our dual licensing model, this program can be used either
 * under the terms of the GNU Affero General Public License, version 3,
 * or under a proprietary license.
 *
 * The texts of the GNU Affero General Public License with an additional
 * permission and of our proprietary license can be found at and
 * in the LICENSE file you have received along with this program.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * "Cloudrexx" is a registered trademark of Cloudrexx AG.
 * The licensing of the program under the AGPLv3 does not imply a
 * trademark license. Therefore any rights, title and interest in
 * our trademarks remain entirely with us.
 */

/**
 * This standalone script is used to create a Cloudrexx compatible
 * backup package of the current installation of Cloudrexx.
 * 
 * This script assumes, that there are no customizings present!
 */

// init framework
require_once('./core/Core/init.php');
$cx = init('minimal');

\DBG::activate(DBG_PHP);

$bc = new BackupCreator($cx);
$bc->createBackup();

class BackupCreationException extends \Exception {}

/**
 * Used to create a Cloudrexx compatible
 * backup package of the current installation of Cloudrexx.
 * 
 * This script assumes, that there are no customizings present!
 * @copyright   CLOUDREXX CMS - CLOUDREXX AG
 * @author      Michael Ritter <michael.ritter@comvation.com>
 * @package     cloudrexx
 * @subpackage  _meta
 * @link        http://www.cloudrexx.com/ cloudrexx homepage
 * @since       v5.0.0
 * @todo Add check for customizings!
 */
class BackupCreator {
    /**
     * @const Currently no package is being generated, ready to create one
     */
    const STATE_IDLE = 0;
    
    /**
     * @const Package generation in progress, initializing
     */
    const STATE_INIT = 1;
    
    /**
     * @const Package generation in progress, database backup in progress
     */
    const STATE_DB = 2;
    
    /**
     * @const Package generation in progress, meta file generating
     */
    const STATE_META = 3;
    
    /**
     * @const Package generation in progress, path config
     */
    const STATE_PATHS = 4;
    
    /**
     * @const Package generation in progress, files are being added to package
     */
    const STATE_FILES = 5;
    
    /**
     * @const Package generation in progress, finalizing
     */
    const STATE_FINALIZE = 6;
    
    /**
     * @const Currently no package is being generated, ready for reset
     */
    const STATE_DONE = 7;
    
    /**
     * @var \Cx\Core\Core\Controller\Cx
     */
    protected $cx;
    
    /**
     * @var array Paths to check for package appendal
     */
    protected $paths = array();
    
    /**
     * @var string Absolute path to temporary working directory
     */
    protected $tempDirPath;
    
    /**
     * @var string Absolute path to database dump info file
     */
    protected $databaseDumpInfoFile;
    
    /**
     * @var string Absolute path to database dump file
     */
    protected $databaseDumpFile;
    
    /**
     * @var string Absolute path to zip package
     */
    protected $filename;
    
    /**
     * @var \PclZip Package object
     */
    protected $package;
    
    /**
     * @var array List of files that are generated to tmp dir
     */
    protected $generatedFiles = array();
    
    /**
     * We should use static:: here, but PHP won't allow it...
     * @var integer Current state (see constants)
     */
    protected $state = self::STATE_IDLE;
    
    /**
     * Constructor
     * @param \Cx\Core\Core\Controller\Cx $cx
     */
    public function __construct($cx) {
        $this->cx = $cx;
    }
    
    /**
     * Creates a backup package of the current installation
     * @param boolean $party If set to true, will stop after completing one step
     */
    public function createBackup($partly = false) {
        $steps = array(
            static::STATE_INIT => 'initPackage', // done
            static::STATE_DB => 'createDatabaseBackup', // done, todos
            static::STATE_META => 'writeMetaData', // done, todos
            static::STATE_PATHS => 'initPaths', // done
            static::STATE_FILES => 'addPathsToPackage', // done, todos
            static::STATE_FINALIZE => 'closePackage', // done, todos
            static::STATE_DONE => 'reset', // open
        );
        $this->log('Starting backup' . ($partly ? ' (partly)' : ''));
        $startTime = microtime(true);
        foreach ($steps as $state=>$method) {
            if ($this->state > $state) {
                continue;
            }
            $this->log('Performing step "' . $method . '"');
            $stepStartTime = microtime(true);
            $oldState = $state;
            $this->$method();
            
            $stepTimeDiff = microtime(true) - $stepStartTime . 's';
            $this->log('Step done, took ' . round($stepTimeDiff, 3) . 's');
            if ($partly && $oldState == $state) {
                return;
            }
        }
        $timeDiff = microtime(true) - $startTime . 's';
        $this->log('Backup done, took ' . round($timeDiff, 3) . 's');
    }
    
    /**
     * Reset to be ready to generate new package
     * @param boolean $hard If set to true last exising package will be dropped
     */
    public function reset($hard = false) {
        if ($this->state != static::STATE_DONE && !$hard) {
            // exception cannot reset
        }
        if ($this->state != static::STATE_DONE) {
            // drop existing package
        }
        $this->state = static::STATE_IDLE;
    }
    
    /**
     * This ensures the necessary files and folders exist and are writable
     * @throws BackupCreationException If files or folders cannot be created
     */
    protected function initPackage() {
        $this->tempDirPath = $this->cx->getWebsiteTempPath() . '/Backup';
        $this->databaseDumpInfoFile = $this->tempDirPath . '/database/dumpInfo.dat';
        $this->databaseDumpFile = $this->tempDirPath . '/database/database.sql';
        $this->metaDataFile = $this->tempDirPath . '/info/meta.yml';
        $this->filename = $this->tempDirPath . '/CloudrexxBackup.zip';
        
        $this->package = new \PclZip($this->filename);
        
        $this->generatedFiles = array(
            $this->databaseDumpInfoFile,
            $this->databaseDumpFile,
            $this->metaDataFile,
        );
        $files = $this->generatedFiles + array(
            $this->filename,
        );
        foreach ($files as $file) {
            $folder = dirname($file);
            if (!file_exists($folder)) {
                if (!\Cx\Lib\FileSystem\FileSystem::make_folder($folder, true)) {
                    throw new BackupCreationException('Could not create temporary folder "' . $folder . '"');
                }
            }
            if (!\Cx\Lib\FileSystem\FileSystem::touch($file, true)) {
                throw new BackupCreationException('Could not touch "' . $file . '"');
            }
        }
        $this->state++;
    }
    
    /**
     * Creates a database dump to temporary directory
     * @todo write file saying which is the last table we finished in order to avoid runtime problems with new entries
     */
    protected function createDatabaseBackup() {
        // write db dump to temp dir
        $db = $this->cx->getDb();
        $adoDb = $db->getAdoDb();
        $pdo = $db->getPdoConnection();
        
        // get all tables
        $tables = $this->getTables($adoDb);
        $dumpInfo = new DumpInfo($this->databaseDumpInfoFile);
        $dumpInfo->cleanup($this->databaseDumpFile);
        
        $f = fopen($this->databaseDumpFile, 'w');
        
        if (!$this->getLineCount($this->databaseDumpFile)) {
            fwrite($f, "SET TIME_ZONE = '+00:00';\nSET FOREIGN_KEY_CHECKS = 0;\n");
        }
        $adoDb->query('SET TIME_ZONE = \'+00:00\'');
        
        foreach ($tables as $table) {
            if ($dumpInfo->isTableDone($table)) {
                continue;
            }
            
            if (!$dumpInfo->isTableStructureDone($table)) {
                // create structure dump
                $result = $adoDb->query('SHOW CREATE TABLE `' . $table . '`'); // 0.03s
                $createStatement = end($result->fields);
                
                // write to structure dump
                fwrite($f, $createStatement . ";\n\n");
                $dumpInfo->setTableStructureDone($table, $this->getLineCount($this->databaseDumpFile));
            }
            
            if (!$dumpInfo->isTableDataDone($table)) {
die('not impl. yet');
                // fetch num rows
                $result = $adoDb->query('SELECT COUNT(*) FROM `' . $table . '`'); // 0.05s
                $currentTableRowOffset = current($result->fields);
                
                for ($i = 0; $i < $currentTableRowOffset; $i++) {
                    $result = $adoDb->query('SELECT * FROM `' . $table . '` LIMIT ' . $i . ',1'); // 0.3s
                    $insertStatement = $this->createInsertStatement($pdo, $table, $result->fields);
                    // write to data file for this table
                    fwrite($f, $insertStatement . ";\n");
                }
            }
        }
        
        $this->state++;
        return;
        // create structure dump
        if (!$dataDumpOffset) {
            $f = fopen($this->structureDumpFile, 'w');
            fwrite($f, "SET FOREIGN_KEY_CHECKS = 0;\n");
            foreach ($tables as $table) {
                // get "show create table"
                $result = $adoDb->query('SHOW CREATE TABLE `' . $table . '`'); // 0.03s
                $createStatement = end($result->fields);
                
                // write to structure dump
                fwrite($f, $createStatement . ";\n\n");
            }
            fclose($f);
        }
        
        // create data dump
        $f = fopen($this->dataDumpFile, 'a');
        if ($dataDumpOffset == 0) {
            fwrite($f, "SET TIME_ZONE = '+00:00';\nSET FOREIGN_KEY_CHECKS = 0;\n");
        }
        $adoDb->query('SET TIME_ZONE = \'+00:00\'');
        $rowOffset = 0;
        foreach ($tables as $index=>$table) {
            // fetch num rows
            $result = $adoDb->query('SELECT COUNT(*) FROM `' . $table . '`'); // 0.05s
            $currentTableRowOffset = current($result->fields);
            $rowOffset += $currentTableRowOffset;
            // if we have more lines in dump than in all queried tables, we can skip to next
            if ($dataDumpOffset > $rowOffset) {
                continue;
            }
            
            for ($i = 0; $i < $currentTableRowOffset; $i++) {
                $result = $adoDb->query('SELECT * FROM `' . $table . '` LIMIT ' . $i . ',1'); // 0.3s
                $insertStatement = $this->createInsertStatement($pdo, $table, $result->fields);
                // write to data file for this table
                fwrite($f, $insertStatement . ";\n");
            }
        }
        fclose($f);
        
        $this->state++;
    }
    
    /**
     * Writes the package's metadata file
     * @todo Fill in missing fields
     */
    protected function writeMetaData() {
        global $_CONFIG;
        
        if ($this->getLineCount($this->metaDataFile)) {
            return;
        }
        
        $domainRepository = new \Cx\Core\Net\Model\Repository\DomainRepository();
        $domain = $domainRepository->getMainDomain();
        
        $metadata = array(
            'website' => array(
                'websiteName' => $domain->getName(),
                'websiteEmail' => $_CONFIG['coreAdminEmail'],
            ),
            'subscription' => array(
                'subscriptionCreatedDate' => '', // ?
                'subscriptionExpiredDate' => '',
                'subscriptionRenewalDate' => '', // ?
                'subscriptionRenewalUnit' => '', // ?
                'subscriptionRenewalQuantifier' => '', // ?
                'subscriptionProductId' => '', // ?
                'subscriptionId' => '', // ?
            ),
        );
        $ds = new \Cx\Core_Modules\Listing\Model\Entity\DataSet($metadata);
        $ds->save($this->metaDataFile);
        $this->state++;
    }
    
    /**
     * Search all paths that may be of interest later
     */
    protected function initPaths() {
        // search paths that may need to be added to package
        $this->paths = array();
        foreach (new \DirectoryIterator($this->cx->getWebsiteDocumentRootPath()) as $fileinfo) {
            if (substr($fileinfo->getFilename(), 0, 1) == '.') {
                continue;
            }
            $this->paths[] = $fileinfo->getPathname();
        }
        asort($this->paths);
        $this->state++;
    }
    
    /**
     * Adds all paths to the package that need to be added
     */
    protected function addPathsToPackage() {
        foreach ($this->paths as $path) {
            if (!$this->needsToBeAddedToBackup($path)) {
                continue;
            }
            $this->addToPackage($path, $this->cx->getWebsiteDocumentRootPath(), 'dataRepository');
        }
        $this->state++;
    }
    
    /**
     * Checks wheter a specific path needs to be added to package or not
     * 
     * @todo Check whitelist (tmp, sitemap in other languages)
     * @param string $path Absolute path
     * @return boolean True if needs to be added, false otherwise
     */
    protected function needsToBeAddedToBackup($path) {
        $relativePath = $this->makePathRelative($path);
        $pathWhitelist = array(
            'config',
            'feed',
            'images',
            'media',
            'themes',
            'tmp',
            'robots.txt',
            'sitemap_de.xml',
            'sitemap_en.xml',
        );
        return in_array($relativePath, $pathWhitelist);
    }
    
    /**
     * Adds a path to the package
     * 
     * @param string $path Absolute path to file or folder
     * @param string $cutOffset Offset to cut in the package
     * @param string $addOffset Offset to add after cut
     * @throws BackupCreationException If path could not be added
     */
    protected function addToPackage($path, $cutOffset, $addOffset) {
        $newFilesCount = $this->package->add(
            $path,
            PCLZIP_OPT_ADD_PATH,
            $addOffset,
            PCLZIP_OPT_REMOVE_PATH,
            $cutOffset
        );
        if (!$newFilesCount) {
            throw new BackupCreationException('Error while adding path "' . $path . '"');
        }
    }
    
    /**
     * Closed the package and finished packaging
     * @todo Move complete package to another location than tmp
     */
    protected function closePackage() {
        foreach ($this->generatedFiles as $file) {
            $this->addToPackage($file, $this->tempDirPath);
        }
        $this->state++;
    }
    
    /*******************/
    /* LIBRARY METHODS */
    /*******************/
    
    /**
     * Creates an insert statement out of an array of fields
     * 
     * @param \PDO $pdo Instance of PDO connection
     * @param string $table Name of the table
     * @param array $result "field=>value" type array
     * @return string Insert statement
     */
    protected function createInsertStatement($pdo, $table, $result) {
        $fields = implode('`, `', array_keys($result));
        foreach ($result as &$field) {
            if ($field === null) {
                $field = 'NULL';
                continue;
            }
            $field = str_replace("\n", '\n', $pdo->quote($field));
        }
        $values = implode(', ', $result);
        $insert = 'INSERT INTO `' . $table . '`(`' . $fields . '`) VALUES (' . $values . ')';
        return $insert;
    }
    
    /**
     * Returns a list of all tables
     * 
     * @param \ADONewConnection $adoDb AdoDb instance
     * @return array List of table names
     */
    protected function getTables($adoDb) {
        $result = $adoDb->query('SHOW TABLES');
        
        $tables = array();
        while (!$result->EOF) {
            $tables[] = current($result->fields);
            $result->moveNext();
        }
        return $tables;
    }
    
    /**
     * Returns the number of lines of a file
     * 
     * @param string $file Absolute file name
     * @return int Number of lines, 0 if file does not exist
     */
    protected function getLineCount($file) {
        if (!file_exists($file)) {
            return 0;
        }
        $f = fopen($file, 'rb');
        $lines = 0;

        while (!feof($f)) {
            $lines += substr_count(fread($f, 8192), "\n");
        }

        fclose($f);

        return $lines;
    }
    
    /**
     * Makes a path relative to Cx root
     * 
     * @param string $path absolute path
     * @return string Path relative to Cx root
     */
    protected function makePathRelative($path) {
        return substr($path, strlen($this->cx->getWebsiteDocumentRootPath()) + 1);
    }
    
    /**
     * Wrapper for logging
     * 
     * @param string $msg Message to log
     */
    protected function log($msg) {
        //echo $msg . '<br />';
        \DBG::log($msg);
    }
}

/**
 * @todo implement cleanup() method
 */
class DumpInfo {
    protected $infoFile;
    protected $tables;
    protected $data;
    
    public function __construct($infoFilePath, $tables) {
        $this->tables = $tables;
        $this->infoFile = new \Cx\Lib\FileSystem\File($infoFilePath);
        $this->data = unserialize($this->infoFile->getData());
        if (!is_array($this->data) || count($this->data) != 4) {
            $this->data = array(
                'currentTable' => null,
                'currentDataOffset' => 0,
                'currentFileOffset' => 0,
                'currentOperation' => 'done',
            );
            $this->write();
        }
    }
    
    /**
     * Resets the dump file to the latest state we know of (in case writing to
     * file was cancelled by timeout)
     */
    public function cleanup($dumpFilePath) {
        // calculate highest offset
        $lastKnownOffset = $this->data['currentFileOffset'];
        
        // clear everything after last offset we know of
        
    }
    
    public function isTableDone($tableName) {
        if ($tableName == $this->getCurrentTable()) {
            return $this->data['currentOperation'] == 'done';
        }
        if (array_search($tableName, $this->tables) < array_search($this->getCurrentTable(), $this->tables)) {
            return true;
        }
        return false;
    }
    
    public function getCurrentTable() {
        return $this->data['currentTable'];
    }
    
    public function isTableStructureDone($tableName) {
        if ($tableName == $this->getCurrentTable()) {
            return $this->data['currentOperation'] == 'done' || $this->data['currentOperation'] == 'data';
        }
        if (array_search($tableName, $this->tables) < array_search($this->getCurrentTable(), $this->tables)) {
            return true;
        }
        return false;
    }
    
    public function setTableStructureDone($tableName, $fileOffset) {
        $this->data = array(
            'currentTable' => $tableName,
            'currentDataOffset' => 0,
            'currentFileOffset' => $fileOffset,
            'currentOperation' => 'data',
        );
        $this->write();
    }
    
    public function isTableDataDone($tableName) {
        return $this->isTableDone($tableName);
    }
    
    public function getTableDataOffset($tableName) {
        if ($tableName != $this->data['currentTable']) {
            return 0;
        }
        return $this->data['currentDataOffset'];
    }
    
    public function setTableDataOffset($tableName, $dataOffset, $fileOffset) {
        $this->data = array(
            'currentTable' => $tableName,
            'currentDataOffset' => $dataOffset,
            'currentFileOffset' => $fileOffset,
            'currentOperation' => 'data',
        );
        $this->write();
    }
    
    public function setTableDone($tableName, $fileOffset) {
        $this->data = array(
            'currentTable' => $tableName,
            'currentDataOffset' => $this->data['currentDataOffset'],
            'currentFileOffset' => $fileOffset,
            'currentOperation' => 'done',
        );
        $this->write();
    }
    
    protected function write() {
        $this->infoFile->write(serialize($this->data));
    }
}

