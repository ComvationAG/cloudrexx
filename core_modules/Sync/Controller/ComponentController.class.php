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
 * Main controller for Sync
 * 
 * @copyright   Cloudrexx AG
 * @author Michael Ritter <michael.ritter@cloudrexx.com>
 * @package cloudrexx
 * @subpackage core_modules_sync
 */

namespace Cx\Core_Modules\Sync\Controller;

/**
 * Main controller for Sync
 * 
 * @copyright   Cloudrexx AG
 * @author Michael Ritter <michael.ritter@cloudrexx.com>
 * @package cloudrexx
 * @subpackage core_modules_sync
 */
class ComponentController extends \Cx\Core\Core\Model\Entity\SystemComponentController implements \Cx\Core\Event\Model\Entity\EventListener {
    /**
     * @var array Two dimensional array array(<entityClassName> => <listOfSyncEntities)
     */
    protected $syncs = array();
    
    /**
     * @var boolean Allows suppress pushing to remotes
     */
    protected $doPush = true;
    
    /**
     * Returns all Controller class names for this component (except this)
     * 
     * Be sure to return all your controller classes if you add your own
     * @return array List of Controller class names (without namespace)
     */
    public function getControllerClasses() {
        return array();
    }
    
    /**
     * Registers all needed events for all syncronized entities
     * 
     * This includes listeners for local side (all entities that are to be synced)
     * and listeners for remote side (all changes of ID that are in mapping table)
     */
    public function registerEvents() {
        $doctrineEvents = array(
            \Doctrine\ORM\Events::postRemove,
            \Doctrine\ORM\Events::postPersist,
            \Doctrine\ORM\Events::postUpdate,
        );
        
        $this->syncs = array();
        $em = $this->cx->getDb()->getEntityManager();
        $syncRepo = $em->getRepository($this->getNamespace() . '\Model\Entity\Sync');
        $syncs = $syncRepo->findAll();
        foreach ($syncs as $sync) {
            // get entity class name
            $entityClassName = $sync->getDataAccess()->getDataSource()->getIdentifier();
            if (!isset($this->syncs[$entityClassName])) {
                $this->syncs[$entityClassName] = array();
            }
            $this->syncs[$entityClassName][] = $sync;
            /*foreach ($doctrineEvents as $doctrineEvent) {
                $this->cx->getEvents()->addModelListener(
                    $entityClassName,
                    $doctrineEvent,
                    $this
                );
            }*/
        }
        
        // listen to events of all entities (since they could be in mapping table)
        foreach ($doctrineEvents as $doctrineEvent) {
            $this->cx->getEvents()->addEventListener(
                'model/' . $doctrineEvent,
                $this
            );
        }
    }
    
    /**
     * Returns a list of command mode commands provided by this component
     * @return array List of command names
     */
    public function getCommandsForCommandMode() {
        return array(
            'sync' => new \Cx\Core_Modules\Access\Model\Entity\Permission(
                array('http', 'https'), // allowed protocols
                array(
                    'get',
                    'post',
                    'put',
                    'delete',
                    'trace',
                    'options',
                    'head',
                    'cli',
                ),   // allowed methods
                false                   // requires login
            ),
        );
    }

    /**
     * Returns the description for a command provided by this component
     * @param string $command The name of the command to fetch the description from
     * @param boolean $short Wheter to return short or long description
     * @return string Command description
     */
    public function getCommandDescription($command, $short = false) {
        switch ($command) {
            case 'sync':
                if ($short) {
                    return 'Allows synchronizing data objects using the RESTful API';
                }
                return 'Allows synchronizing data objects using the RESTful API' . "\n" .
                    'Usage: sync <apiVersion> <outputModule> <dataSource> (<elementId>) (apikey=<apiKey>) (<options>)';
            default:
                return '';
        }
    }
    
    /**
     * Execute one of the commands listed in getCommandsForCommandMode()
     *
     * <domain>(/<offset>)/api/sync/<apiVersion>/<outputModule>/<dataSource>/<parameters>[(?apikey=<apikey>(&<options>))|?<options>]
     * @see getCommandsForCommandMode()
     * @param string $command Name of command to execute
     * @param array $arguments List of arguments for the command
     * @return void
     */
    public function executeCommand($command, $arguments, $dataArguments = array()) {
        try {
            switch ($command) {
                case 'sync':
                    // do not push changes made during this request
                    $this->doPush = false;
                    $this->sync($command, $arguments, $dataArguments);
            }
        } catch (\Exception $e) {
            // This should only be used if API cannot handle the request at all.
            // Most exceptions should be catched inside the API!
            http_response_code(400); // BAD REQUEST
            echo 'Exception of type "' . get_class($e) . '" with message "' . $e->getMessage() . '"';
        }
    }
    
    /**
     * This is the handler for remote side: handles incoming changes
     * @param string $command Always "sync"
     * @param array $arguments Arguments for API, first entry is API version
     * @param array  $dataArguments (optional) List of data arguments for the command
     * @todo pretending delete was successful does not work for other output methods than json
     */
    public function sync($command, $arguments, $dataArguments) {
        $method = strtolower($_SERVER['REQUEST_METHOD']);
        
        $apiVersion = array_shift($arguments); // shift api version
        
        if (!in_array($apiVersion, $this->supportedApiVersions)) {
            throw new \BadMethodCall('Unsupported API version: "' . $apiVersion . '"');
        }
        
        // if an existing element is altered (delete, put, patch)
        // replace ID using mapping table
        if (in_array($method, array('put', 'patch', 'delete'))) {
            if (!isset($arguments[2])) {
                // API would produce a 404 here
                throw new \BadMethodCall('No element given');
            }
            $elementId = $arguments[2];
            
            // get mapping table entry
            $em = $this->cx->getDb()->getEntityManager();
            $mappingRepo = $em->getRepository($this->getNamespace() . '\Model\Entity\IdMapping');
            $mapping = $mappingRepo->findOneBy(array(
                'foreignHost' => $foreignHost,
                'entityType' => $entityType,
                'foreignId' => $foreignId,
            ));
            
            if (!$mapping) {
                // remote is trying to push changes to an entity that does not yet exist here
                // let's self-heal:
                if ($method == 'delete') {
                    // pretend everything is ok
                    $response = new \Cx\Core_Modules\DataAccess\Model\Entity\ApiResponse();
                    $response->setStatus(
                        \Cx\Core_Modules\DataAccess\Model\Entity\ApiResponse::STATUS_OK
                    );
                    $response->setData(array());
                    
                    $response->send($this->getComponent('DataSource')->getController('JsonOutputController'));
                    return;
                } else {
                    // pretent it's a new element
                    // in order to do so we need to:
                    // - tell api it's a POST request
                    // - make sure our eventlistener thinks it's a POST request
                    $this->cx->getRequest()->setHttpRequestMethod('post');
                }
            }
            
            $arguments[2] = $mapping->getLocalId();
        }
        
        $this->getComponent('DataAccess')->executeCommand(
            $apiVersion,
            $arguments,
            $dataArguments
        );
    }
    
    /**
     * Pushes changes to remote (local side) and updates mapping table (remote side)
     * @param string $eventName Name of the triggered event
     * @param object $eventArgs Doctrine event args
     */
    public function onEvent($eventName, array $eventArgs) {
        switch ($eventName) {
            // entity was dropped
            case \Doctrine\ORM\Events::postRemove:
                // remote side code
                $this->updateMappingTable('delete', $entityClassName, $entityIndexData);
                
                // local side code
                $this->spoolSync('delete', $entityClassName, $entityIndexData);
                break;
            // entity was added
            case \Doctrine\ORM\Events::postPersist:
                // remote side code
                    // add ID to mapping table
                
                // local side code
                $this->spoolSync('post', $entityClassName, $entityIndexData, $entity);
                break;
            // entity was updated
            case \Doctrine\ORM\Events::postUpdate:
                // remote side code
                $this->updateMappingTable('put', $entityClassName, $entityIndexData);
                
                // local side code
                $this->spoolSync('put', $entityClassName, $entityIndexData, $entity);
                break;
            default:
                return;
        }
    }
    
    /**
     * Updates mapping table on remote side
     * @param string $eventType One of "put", "delete"
     * @param string $entityClassName Classname of the entity to update
     * @param array $entityIndexData Field=>value-type array with primary key data
     * @param array $newEntityIndexData (optional) Field=>value-type array with primary key data for "put" events
     */
    protected function updateMappingTable($eventType, $entityClassName, $entityIndexData, $newEntityIndexData = array()) {
        // search for entry in mapping table
        $em = $this->cx->getDb()->getEntityManager();
        $mappingRepo = $em->getRepository($this->getNamespace() . '\Model\Entity\Mapping');
        $mappings = $mappingRepo->findBy(array(
            'entityType' => $entityClassName,
            'localId' => $entityIndexData,
        ));
        
        foreach ($mappings as $mapping) {
            if ($eventType == 'delete') {
                // drop entry
                $em->remove($mapping);
            } else if ($eventType == 'put') {
                // update entry
                $mapping->setLocalId($newEntityIndexData);
                $em->persist($mapping);
            }
        }
        $em->flush();
    }
    
    /**
     * Spools changes to be pushed to remote on local side
     * @param string $eventType One of "post", "put", "delete"
     * @param string $entityClassName Classname of the entity to update
     * @param array $entityIndexData Field=>value-type array with primary key data
     * @param \Cx\Model\Base\EntityBase $entity (optional) Entity for "post" and "put"
     * @todo: This does not spool yet, instead it writes changes instantly
     */
    public function spoolSync($eventType, $entityClassName, $entityIndexData, $entity = null) {
        // suppress push on incoming changes (allows two-way sync)
        if (!$this->doSync) {
            return;
        }
        
        // do not sync entities that are not configured to be synced
        if (!isset($this->syncs[$entityClassName])) {
            return;
        }
        
        // push changes
        // @todo: This should write to a spooling table for asynchronous sync
        foreach ($this->syncs[$entityClassName] as $sync) {
            $sync->push($eventType, $entityIndexData, $entity);
        }
    }
}
