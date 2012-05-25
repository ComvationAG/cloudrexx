<?php
/**
 * JSON Adapter for Cx\Model\ContentManager\Page
 * @copyright   Comvation AG
 * @author      Florian Schuetz <florian.schuetz@comvation.com>
 * @author      Michael Ritter <michael.ritter@comvation.com>
 * @package     contrexx
 * @subpackage  core/json
 */
namespace Cx\Core\Json\Adapter;
require_once ASCMS_CORE_PATH.'/json/JsonAdapter.interface.php';
require_once ASCMS_CORE_PATH . '/routing/LanguageExtractor.class.php';
require_once ASCMS_CORE_PATH.'/BackendTable.class.php';
use \Cx\Core\Json\JsonAdapter;

/**
 * JSON Adapter for Cx\Model\ContentManager\Page
 * @copyright   Comvation AG
 * @author      Florian Schuetz <florian.schuetz@comvation.com>
 * @author      Michael Ritter <michael.ritter@comvation.com>
 * @package     contrexx
 * @subpackage  core/json
 */

class JsonPage implements JsonAdapter {

    private $em = null;
    private $db = null;
    private $pageRepo = null;
    private $nodeRepo = null;
    private $fallbacks;
    private $pageId = 0;
    public $messages;

    /**
     * Constructor
     */
    function __construct() {
        $this->em = \Env::em();
        $this->db = \Env::get('db');
        $this->pageRepo = $this->em->getRepository('Cx\Model\ContentManager\Page');
        $this->nodeRepo = $this->em->getRepository('Cx\Model\ContentManager\Node');
        $this->logRepo  = $this->em->getRepository('Gedmo\Loggable\Entity\LogEntry');
        $this->messages = array();
        $this->tz = new \DateTimeZone('Europe/Berlin');
        $this->pageId = !empty($_GET['pageId']) ? $_GET['pageId'] : 0;

        $fallback_lang_codes = \FWLanguage::getFallbackLanguageArray();
        $active_langs = \FWLanguage::getActiveFrontendLanguages();

        // get all active languages and their fallbacks
        foreach ($active_langs as $lang) {
            $this->fallbacks[\FWLanguage::getLanguageCodeById($lang['id'])] = ((array_key_exists($lang['id'], $fallback_lang_codes)) ? \FWLanguage::getLanguageCodeById($fallback_lang_codes[$lang['id']]) : null);
        }
    }
    
    /**
     * Returns the internal name used as identifier for this adapter
     * @return String Name of this adapter
     */
    public function getName() {
        return 'page';
    }
    
    /**
     * Returns an array of method names accessable from a JSON request
     * @author Michael Ritter <michael.ritter@comvation.com>
     * @return array List of method names
     */
    public function getAccessableMethods() {
        return array('get', 'set', 'setPagePreview', 'setPageStatus', 'getHistoryTable');
    }

    /**
     * Returns all messages as string
     * @return String HTML encoded error messages
     */
    public function getMessagesAsString() {
        return implode("<br />", $this->messages);
    }

    /**
     * Sends data to the client
     * @todo Clean up usage of $param and $_GET
     * @param Array $params Client parameters
     * @return Array Page as array
     * @throws Exception If the page could not be found
     */
    public function get($params) {
        global $_CORELANG;
        
        // Global access check
        if (!\Permission::checkAccess(6, 'static', true) ||
                !\Permission::checkAccess(35, 'static', true)) {
            throw new \ContentManagerException($_CORELANG['TXT_CORE_CM_USAGE_DENIED']);
        }
        
        $_GET['page'] = contrexx_input2raw($_GET['page']);
        $_GET['node'] = contrexx_input2raw($_GET['node']);
        $_GET['lang'] = contrexx_input2raw($_GET['lang']);
        if (isset($params['get']) && isset($params['get']['history'])) {
            $params['get']['history'] = contrexx_input2raw($params['get']['history']);
        }
        
        // pages can be requested in two ways:
        // by page id               - default for existing pages
        // by node id + lang        - to translate an existing page into a new language, assigned to the same node
        if (isset($_GET['page']) && $_GET['page'] != 0) {
            $page = $this->pageRepo->find($_GET['page']);
        }

        if (isset($page)) {
            // All is well, continue
        } elseif (isset($_GET['node']) && isset($_GET['lang'])) {
            $node = $this->nodeRepo->find($_GET['node']);
            $pageArray = $this->getFallbackPageArray($node, $_GET['lang']);
        } else {
            throw new Exception('cannot find that page');
        }

        // load an older revision if asked to do so:
        if (isset($params['get']) && isset($params['get']['history'])) {
            $this->logRepo->revert($page, $params['get']['history']);
        }
        // load the draft revision if one is available and we're not loading historic data:
        elseif ($page->getEditingStatus() == 'hasDraft' || $page->getEditingStatus() == 'hasDraftWaiting') {
            $this->logRepo = $this->em->getRepository('Gedmo\Loggable\Entity\LogEntry');

            $availableRevisions = $this->logRepo->getLogEntries($page);
            $this->logRepo->revert($page, $availableRevisions[1]->getVersion());
        }

        $pageArray = $this->getPageArray($page);

        return $pageArray;
    }

    /**
     * Handles request from the client
     * @todo Clean up usage of $param and $_GET
     * @global Array $_CORELANG Core language data
     * @param Array $params Client parameters
     * @return type 
     */
    public function set($params) {
        global $_CORELANG;
        
        // Global access check
        if (!\Permission::checkAccess(6, 'static', true) ||
                !\Permission::checkAccess(35, 'static', true)) {
            throw new \ContentManagerException($_CORELANG['TXT_CORE_CM_USAGE_DENIED']);
        }
        
        $newpage = false;
        $reload = false;
        $pg = \Env::get('pageguard');

        $page = $params['post']['page'];
        $output = $this->validatePageArray($page);

        if (intval($page['id']) > 0) {
            // if we got a page id, the page already exists and can be updated
            $page = $this->pageRepo->find($params['post']['page']['id']);
            $node = $page->getNode();
        } elseif ($page['id'] == 0 && $page['node'] && $page['lang']) {
            // we are translating another page (to $page['lang'])
            $node = $this->nodeRepo->find($page['node']);
            
            $page = $node->translatePage(true, $page['lang']);

            $reload = true;
        }
        else {
            if (!\Permission::checkAccess(5, 'static', true)) {
                throw new \ContentManagerException($_CORELANG['TXT_CORE_CM_CREATION_DENIED']);
            }
            
            // create a new node/page combination
            $node = new \Cx\Model\ContentManager\Node();
            $node->setParent($this->nodeRepo->getRoot());

            $this->em->persist($node);
            $this->em->flush();

            $page = new \Cx\Model\ContentManager\Page();
            $page->setNode($node);
            $page->setNodeIdShadowed($node->getId());
            $page->setLang(\FWLanguage::getLanguageIdByCode($params['post']['page']['lang']));

            $newpage = true;
            $reload = true;
        }

        $page->updateFromArray($output);
        $page->setUpdatedAtToNow();

        if (isset($params['get']['publish']) && $params['get']['publish']) {
            $page->setActive(true);
        }

        $page->validate();

        if ($page->isFrontendProtected() && isset($params['post']['frontendGroups'])) {
            if (\Permission::checkAccess(36, 'static', true)) {
                $pg->setAssignedGroupIds($page, $params['post']['frontendGroups'], true);
            } else {
                $this->messages[] = $_CORELANG['TXT_CORE_CM_ACCESS_CHANGE_DENIED'];
            }
        }
        if ($page->isBackendProtected() && isset($params['post']['backendGroups'])) {
            if (\Permission::checkAccess(36, 'static', true)) {
                $pg->setAssignedGroupIds($page, $params['post']['backendGroups'], false);
            } else {
                $this->messages[] = $_CORELANG['TXT_CORE_CM_ACCESS_CHANGE_DENIED'];
            }
        }

        if ((isset($params['get']['publish']) && $params['get']['publish'])
                && \Permission::checkAccess(78, 'static', true)) {
            // user w/permission clicked save&publish. we should either publish the page or submit the draft for approval
            if ($page->getEditingStatus() == 'hasDraftWaiting') {
                $reload = true;
            }
            $page->setEditingStatus('');
            $this->messages[] = $_CORELANG['TXT_CORE_SAVED'];
            // TODO: define what log data we want to keep in a case like this.
            //       make adjustments, if necessary.
        } else {
            // user clicked save [as draft], so let's do that
            if ($newpage) {
                $this->em->persist($page);
                $this->em->flush();
            }
            $updatingDraft = $page->getEditingStatus() != '' ? true : false;

            if (isset($params['get']['publish']) && $params['get']['publish']) {
                // user w/o publish permission clicked save&publish. submit it as a draft
                $page->setEditingStatus('hasDraftWaiting');
                $this->messages[] = $_CORELANG['TXT_CORE_DRAFT_SUBMITTED'];
            } else {
                if ($page->getEditingStatus() == 'hasDraftWaiting' && \Permission::checkAccess(78, 'static', true)) {
                    $reload = true;
                }
                $page->setEditingStatus('hasDraft');
                $this->messages[] = $_CORELANG['TXT_CORE_SAVED_AS_DRAFT'];
            }

            // gedmo-loggable generates a LogEntry (i.e. revision) on persist, so we'll have to 
            // store the draft first, then revert the current version to what it previously was.
            // in the end, we'll have the current [published] version properly stored as a page
            // and the draft version stored as a gedmo LogEntry

            $this->em->persist($page);
            // gedmo hooks in on persist/flush, so we unfortunately need to flush our em in
            // order to get a clean set of logEntries
            $this->em->flush();
            $logEntries = $this->logRepo->getLogEntries($page);
            // $logEntries holds an array of Gedmo LogEntries, the most recent one listed first
            // we need the editing status of the page
            $logData = $logEntries[1]->getData();
            $logData['editingStatus'] = $page->getEditingStatus();
            $logEntries[1]->setData($logData);

            // revert to the published version
            $this->logRepo->revert($page, $logEntries[1]->getVersion());
            $this->em->persist($page);

            // gedmo auto-logs slightly too much data. clean up unnecessary revisions:
            if ($updatingDraft) {
                $this->em->flush();

                $logEntries = $this->logRepo->getLogEntries($page);
                $this->em->remove($logEntries[2]);
                $this->em->remove($logEntries[3]);
            }
        }

        $this->em->persist($page);
        $this->em->flush();

        // only users with publish rights can create aliases
        if (\Permission::checkAccess(115, 'static', true) &&
                \Permission::checkAccess(78, 'static', true)) {
            // aliases are updated after persist!
            $data = array();
            $data['alias'] = $params['post']['page']['alias'];
            $aliases = $page->getAliases();
            $page->updateFromArray($data);
            if ($aliases != $page->getAliases()) {
                $reload = true;
            }
        } else {
            $this->messages[] = $_CORELANG['TXT_CORE_ALIAS_CREATION_DENIED'];
        }
        
        return array(
            'reload' => $reload,
            'id' => $page->getId()
        );
    }

    /**
     * Sets the page object in the session and returns the link to the page (frontend).
     * @param   array  $params
     * @return  array  [link]     The link to the page (frontend).
     */
    public function setPagePreview($params) {
        global $_CORELANG;
        
        // Global access check
        if (!\Permission::checkAccess(6, 'static', true) ||
                !\Permission::checkAccess(35, 'static', true)) {
            throw new \ContentManagerException($_CORELANG['TXT_CORE_CM_USAGE_DENIED']);
        }
        
        $page = $this->validatePageArray($params['post']['page']);
        $page['pageId'] = $params['post']['page']['id'];
        $page['lang'] = $params['post']['page']['lang'];

        $_SESSION['page'] = $page;
    }

    /**
     * Returns a validated page array.
     * 
     * @param   array  $page
     * @return  array  $output
     */
    private function validatePageArray($page) {
        $fields = array(
            'type' => array('type' => 'String'),
            'name' => array('type' => 'String', 'map_to' => 'title'),
            'title' => array('type' => 'String', 'map_to' => 'contentTitle'),
            // the model can take advantage of proper NULLing, so this needn't be set
            //                  'scheduled_publishing' => array('type' => 'boolean'),
            'start' => array('type' => 'DateTime', 'require' => 'scheduled_publishing'),
            'end' => array('type' => 'DateTime', 'require' => 'scheduled_publishing'),
            'metatitle' => array('type' => 'String'),
            'metakeys' => array('type' => 'String'),
            'metadesc' => array('type' => 'String'),
            'metarobots' => array('type' => 'boolean'),
            'content' => array('type' => 'String'),
            'sourceMode' => array('type' => 'boolean'),
            'protection_frontend' => array('type' => 'boolean', 'map_to' => 'frontendProtection'),
            'protection_backend' => array('type' => 'boolean', 'map_to' => 'backendProtection'),
            'application' => array('type' => 'String', 'map_to' => 'module'),
            'area' => array('type' => 'String', 'map_to' => 'cmd'),
            'target' => array('type' => 'String'),
            'link_target' => array('type' => 'String', 'map_to' => 'linkTarget'),
            'slug' => array('type' => 'String'),
            'caching' => array('type' => 'boolean'),
            'skin' => array('type' => 'integer'),
            'customContent' => array('type' => 'String'),
            'cssName' => array('type' => 'String'),
            'cssNavName' => array('type' => 'String'),
        );

        $output = array();

        foreach ($fields as $field => $meta) {
            $target = isset($meta['map_to']) ? $meta['map_to'] : $field;
            
            if (isset($page[$field])) {
                $page[$field] = contrexx_input2raw($page[$field]);
            } else {
                $page[$field] = null;
            }

            switch ($meta['type']) {
                case 'boolean':
                    // checkboxes and radiobuttons by default aren't submitted
                    // unless checked or selected. in cm.html they are prefixed
                    // with an input type=hidden value=off, so we always get a
                    // value this is required for Page#updateFromArray to work.
                    if ($page[$field] == 'on') {
                        $value = true;
                    } else if ($page[$field] == 'off') {
                        $value = false;
                    }
                    break;
                case 'DateTime':
                    try {
                        $value = new \DateTime($page[$field], $this->tz);
                    } catch (\Exception $e) {
                        $value = new \DateTime('0000-00-00 00:00', $this->tz);
                    }
                    break;
                case 'integer':
                    $value = intval($page[$field]);
                    break;
                case 'String':
                    $value = $page[$field];
                    break;
            }
            if (isset($meta['require']) && $page[$meta['require']] == 'off') {
                $value = null;
            }

            $output[$target] = $value;
        }

        //TODO: should we allow filter/callback fns in field processing above?
        $output['content'] = preg_replace('/\\[\\[([A-Z0-9_-]+)\\]\\]/', '{\\1}', $output['content']);

        return $output;
    }

    public function getHistoryTable() {
        if (empty($this->pageId)) {
            throw new \ContentManagerException('please provide a pageId');
        }

        //(I) get the right page
        $page = $this->pageRepo->findOneById($this->pageId);

        if (!$page) {
            throw new \ContentManagerException('could not find page with id '.$this->pageId);
        }

        //(II) build the table with headers
        $table = new \BackendTable(array('width' => '100%'));
        $table->setAutoGrow(true);

        $table->setHeaderContents(0, 0, 'Date');
        $table->setHeaderContents(0, 1, 'Title');
        $table->setHeaderContents(0, 2, 'Author');
        //make sure those are th's too
        $table->setHeaderContents(0, 3, '');
        $table->setHeaderContents(0, 4, '');

        //(III) collect page informations - path, virtual language directory
        $path = $this->pageRepo->getPath($page);
        $pageHasDraft = $page->getEditingStatus() != '' ? true : false;

        $le = new \Cx\Core\Routing\LanguageExtractor($this->db, DBPREFIX);
        $langDir = $le->getShortNameOfLanguage($page->getLang());

        $logs = $this->logRepo->getLogEntries($page);

        //(V) add the history entries
        $row = 0;
        $logCount = count($logs);
        for ($i = 0; $i < $logCount; $i++) {
            if (isset($logs[$i + 1])) {
                $nextData = $logs[$i + 1]->getData();
                if (isset($nextData['editingStatus']) && ($nextData['editingStatus'] == 'hasDraft' || $nextData['editingStatus'] == 'hasDraftWaiting')) {
                    continue;
                }
            }
            
            $version = $logs[$i]->getVersion();
            $row++;
            $user = json_decode($logs[$i]->getUsername());
            $username = $user->{'name'};
            try {
                $this->logRepo->revert($page, $version);
                $page->setUpdatedAt($logs[$i]->getLoggedAt());

                $this->addHistoryEntries($page, $username, $table, $row, $version, $langDir . '/' . $path, $pageHasDraft);
            } catch (\Gedmo\Exception\UnexpectedValueException $e) {
                
            }
        }

        //(VI) render
        die($table->toHtml());
    }

    private function addHistoryEntries($page, $username, $table, $row, $version = '', $path = '', $pageHasDraft = true) {
        global $_ARRAYLANG;

        $dateString  = $page->getUpdatedAt()->format(ASCMS_DATE_FILE_FORMAT);
        $historyLink = ASCMS_PATH_OFFSET . '/' . $path . '?history=' . $version;
        $tableStyle  = '';

        if ($row == 1) {
            $tableStyle  = 'style="display: none;"';
            if ($pageHasDraft) {
                $dateString .= ' (' . $_ARRAYLANG['TXT_CORE_DRAFT'] . ')';
            }
        }
        
        $table->setCellContents($row, 3, '<a id="load_'.$version.'" class="historyLoad" href="javascript:loadHistoryVersion('.$version.')" '.$tableStyle.'>' . $_ARRAYLANG['TXT_CORE_LOAD'] . '</a>');
        $table->setCellContents($row, 4, '<a id="preview_'.$version.'" class="historyPreview" href="' . $historyLink . '" target="_blank" '.$tableStyle.'>' . $_ARRAYLANG['TXT_CORE_PREVIEW'] . '</a>');

        $table->setCellContents($row, 0, $dateString);
        $table->setCellContents($row, 1, $page->getTitle());
        $table->setCellContents($row, 2, $username);
    }

    /**
     * Returns the access data array
     * @param type $page Unused
     * @return array Access data
     */
    public function getAccessData($page = null) {
        // TODO: add functionality for $page!=null (see below), DRY up #getFallbackPageArray

        $pg = \Env::get('pageguard');

        $accessData = array();

        $accessData['frontend'] = array('groups' => $pg->getGroups(true), 'assignedGroups' => array());
        $accessData['backend'] = array('groups' => $pg->getGroups(false), 'assignedGroups' => array());

        return $accessData;
    }

    /**
     * Returns the array representation of a fallback page
     * @param \Cx\Model\ContentManager\Node $node Node to get the page of
     * @param integer $lang Language id to get the fallback of
     * @return array Array representing a fallback page
     */
    private function getFallbackPageArray($node, $lang) {
        foreach ($node->getPages() as $page) {
            if ($page->getLang() == \FWLanguage::getLanguageIdByCode($this->fallbacks[$lang]))
                break;
        }

        // Access Permissions
        $pg = \Env::get('pageguard');
        $accessData = array();
        $accessData['frontend'] = array('groups' => $pg->getGroups(true), 'assignedGroups' => $pg->getAssignedGroupIds($page, true));
        $accessData['backend'] = array('groups' => $pg->getGroups(false), 'assignedGroups' => $pg->getAssignedGroupIds($page, false));

        $pageArray = array(
            // Editor Meta
            'id' => 0,
            'lang' => $lang,
            'node' => $node->getId(),
            'type' => ($this->fallbacks[$lang] ? 'fallback' : 'content'),
            // Page Tab
            'name' => $page->getTitle(),
            'title' => $page->getContentTitle(),
            // Metadata
            'metatitle' => $page->getMetatitle(),
            'metadesc' => $page->getMetadesc(),
            'metakeys' => $page->getMetakeys(),
            'metarobots' => $page->getMetarobots(),
            // Access Permissions
            'frontend_protection' => $page->isFrontendProtected(),
            'backend_protection' => $page->isBackendProtected(),
            'accessData' => $accessData,
            // Advanced Settings
            'slug' => $page->getSlug(),
            'sourceMode' => false,
            'sourceMode' => $page->getSourceMode(),
        );

        return $pageArray;
    }

    /**
     * Creates the page array representation of a page
     * @param \Cx\Model\ContentManager\Page $page Page to "arrayify"
     * @return Array Array representing a page
     */
    private function getPageArray($page) {
        // Scheduled Publishing
        $n = new \DateTime(null, $this->tz);
        if ($page->getStart() && $page->getEnd()) {
            $scheduled_publishing = true;
            $start = $page->getStart()->format('d.m.Y H:i');
            $end = $page->getEnd()->format('d.m.Y H:i');
        } else {
            $scheduled_publishing = false;
            $start = $n->format('d.m.Y H:i');
            $end = $n->format('d.m.Y H:i');
        }

        // Access Permissions
        $pg = \Env::get('pageguard');
        $accessData = array();
        $accessData['frontend'] = array('groups' => $pg->getGroups(true), 'assignedGroups' => $pg->getAssignedGroupIds($page, true));
        $accessData['backend'] = array('groups' => $pg->getGroups(false), 'assignedGroups' => $pg->getAssignedGroupIds($page, false));

        $pageArray = array(
            // Editor Meta
            'id' => $page->getId(),
            'lang' => \FWLanguage::getLanguageCodeById($page->getLang()),
            'node' => $page->getNode()->getId(),
            // Page Tab
            'name' => $page->getTitle(),
            'title' => $page->getContentTitle(),
            'type' => $page->getType(),
            'target' => $page->getTarget(),
            'module' => $page->getModule(),
            'area' => $page->getCmd(),
            'scheduled_publishing' => $scheduled_publishing,
            'start' => $start,
            'end' => $end,
            'content' => preg_replace('/{([A-Z0-9_-]+)}/', '[[\\1]]', $page->getContent()),
            'sourceMode' => $page->getSourceMode(),
            // Metadata
            'metatitle' => $page->getMetatitle(),
            'metadesc' => $page->getMetadesc(),
            'metakeys' => $page->getMetakeys(),
            'metarobots' => $page->getMetarobots(),
            // Access Permissions
            'frontend_protection' => $page->isFrontendProtected(),
            'backend_protection' => $page->isBackendProtected(),
            'accessData' => $accessData,
            // Advanced Settings
            'skin' => $page->getSkin(),
            'customContent' => $page->getCustomContent(),
            'cssName' => $page->getCssName(),
            'cssNavName' => $page->getCssNavName(),
            'caching' => $page->getCaching(),
            'linkTarget' => $page->getLinkTarget(),
            'slug' => $page->getSlug(),
            'aliases' => $this->getAliasArray($page),
            'editingStatus' => $page->getEditingStatus(),
                /* 'display'       =>  $page->getDisplay(),
                  'active'        =>  $page->getActive(),
                  'updatedAt'     =>  $page->getUpdatedAt(), */
        );

        return $pageArray;
    }

    /**
     * Returns an array of alias slugs
     * @param Cx\Model\ContentManager\Page $page Page to get the aliases of
     * @return Array<String>
     */
    private function getAliasArray($page) {
        $pages = $page->getAliases();
        $aliases = array();
        foreach ($pages as $alias) {
            $aliases[] = $alias->getSlug();
        }
        return $aliases;
    }
}
