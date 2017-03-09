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
 * Media  Directory
 *
 * @copyright   CLOUDREXX CMS - CLOUDREXX AG
 * @author      Cloudrexx Development Team <info@cloudrexx.com>
 * @version     1.0.0
 * @subpackage  module_mediadir
 * @todo        Edit PHP DocBlocks!
 */
namespace Cx\Modules\MediaDir\Controller;
/**
 * Media Directory
 *
 * @copyright   CLOUDREXX CMS - CLOUDREXX AG
 * @author      CLOUDREXX Development Team <info@cloudrexx.com>
 * @package     cloudrexx
 * @subpackage  module_mediadir
 */
class MediaDirectory extends MediaDirectoryLibrary
{

    var $pageTitle;
    var $metaTitle;
    var $metaDescription;
    var $metaImage;


    var $arrNavtree = array();

    /**
     * Constructor
     */
    function __construct($pageContent, $name)
    {
        global $_ARRAYLANG, $_CORELANG;

        //globals
        parent::__construct('.', $name);
        parent::getSettings();
        parent::getFrontendLanguages();
        parent::checkDisplayduration();

        $this->pageContent = $pageContent;
    }

    /**
     * get oage
     *
     * Reads the act and selects the right action
     *
     * @access   public
     * @return   string  parsed content
     */
    function getPage()
    {
        global $_CONFIG;

        \JS::activate('shadowbox');
        \JS::activate('jquery');

        if($this->arrSettings['settingsAllowVotes']) {
            $objVoting = new MediaDirectoryVoting($this->moduleName);
            $this->setJavascript($objVoting->getVoteJavascript());

            if(isset($_GET['vote']) && intval($_GET['eid']) != 0) {
                $objVoting->saveVote(intval($_GET['eid']), intval($_GET['vote']));
            }
        }

        if($this->arrSettings['settingsAllowComments'] == 1) {
            $objComment = new MediaDirectoryComment($this->moduleName);
            $this->setJavascript($objComment->getCommentJavascript());
            $comment = isset($_GET['comment']) ? $_GET['comment'] : '';
            if($comment == 'add' && intval($_GET['eid']) != 0) {
                $objComment->saveComment(intval($_GET['eid']), $_POST);
            }

            if($comment == 'refresh' && intval($_GET['eid']) != 0) {
                $objComment->refreshComments(intval($_GET['eid']), $_GET['pageSection'], $_GET['pageCmd']);
            }
        }

        switch ($_REQUEST['cmd']) {
            case 'delete':
                if((!empty($_REQUEST['eid'])) || (!empty($_REQUEST['entryId']))) {
                    parent::checkAccess('delete_entry');
                    $this->deleteEntry();
                } else {
                    header("Location: index.php?section=".$this->moduleName);
                    exit;
                }
                break;
            case 'latest':
                $this->showLatest();
                break;
            case 'popular':
                $this->showPopular();
                break;
            case 'map':
                $this->showMap();
                break;
            case 'myentries':
                parent::checkAccess('my_entries');
                $this->showMyEntries();
                break;
            case 'detail':
                parent::checkAccess('show_entry');
                $this->showEntry();
                break;
            case 'adduser':
                $this->showAddUser();
                break;
            case 'confirm_in_progress':
                $this->_objTpl->setTemplate($this->pageContent);
                break;
            case 'alphabetical':
                $this->showAlphabetical();
                break;
            default:
                if(isset($_REQUEST['check'])) {
                    parent::checkDisplayduration();
                }

                if(substr($_REQUEST['cmd'],0,6) == 'detail'){
                    parent::checkAccess('show_entry');
                    $this->showEntry();
                } else if (substr($_REQUEST['cmd'],0,3) == 'add'){
                    parent::checkAccess('add_entry');
                    $this->modifyEntry();
                } else if (substr($_REQUEST['cmd'],0,4) == 'edit'){
                    if((intval($_REQUEST['eid']) != 0) || (intval($_REQUEST['entryId']) != 0)) {
                        parent::checkAccess('edit_entry');
                        $this->modifyEntry();
                    } else {
                        header("Location: index.php?section=".$this->moduleName);
                        exit;
                    }
                } else {
                    if(isset($_REQUEST['search'])) {
                        $this->showSearch();
                    } else {
                        $this->showOverview();
                    }
                }
        }

        $this->_objTpl->setVariable(array(
            $this->moduleLangVar.'_JAVASCRIPT' =>  $this->getJavascript(),
        ));

        return $this->_objTpl->get();
    }

    function showOverview()
    {
        global $_ARRAYLANG, $_CORELANG;

        $this->_objTpl->setTemplate($this->pageContent, true, true);

        $intCmdFormId = 0;
        $listCategoriesAndLevels = false;
        $showEntriesOfLevel = false;
        $showEntriesOfCategory = false;
        $showLevelDetails = false;
        $showCategoryDetails = false;
        $bolLatest = false;

        // whether the loaded form (if at all) does use categories or not
        $bolFormUseCategory = false;

        // whether the loaded form (if at all) does use levels or not
        $bolFormUseLevel = false;

        $intLimitStart = isset($_GET['pos']) ? intval($_GET['pos']) : 0;

        //search existing category&level blocks
        $arrExistingBlocks = array();

        for($i = 1; $i <= 10; $i++){
            if($this->_objTpl->blockExists($this->moduleNameLC.'CategoriesLevels_row_'.$i)){
                array_push($arrExistingBlocks, $i);
            }
        }

        //get ids
        if(isset($_GET['cmd'])) {
            $arrIds = explode("-", $_GET['cmd']);
        }

        if($this->arrSettings['settingsShowLevels'] == 1) {
            if(intval($arrIds[0]) != 0) {
                $intLevelId = intval($arrIds[0]);
            } else {
                $intLevelId = 0;
            }

            $intLevelId = isset($_GET['lid']) ? intval($_GET['lid']) : $intLevelId;

            if(!empty($arrIds[1])) {
                $intCategoryCmd = $arrIds[1];
            } else {
                $intCategoryCmd = 0;
            }
        } else {
            $intLevelId = 0;

            if(intval($arrIds[0]) != 0) {
                $intCategoryCmd = $arrIds[0];
            } else {
                $intCategoryCmd = 0;
            }
        }

        if($intCategoryCmd != 0) {
            $intCategoryId = intval($intCategoryCmd);
        } else {
            $intCategoryId = 0;
        }

        $intCategoryId = isset($_GET['cid']) ? intval($_GET['cid']) : $intCategoryId;

        // show block {$this->moduleNameLC}Overview
        if (empty($intCategoryId) && empty($intLevelId) && $this->_objTpl->blockExists($this->moduleNameLC.'Overview')) {
            $this->_objTpl->touchBlock($this->moduleNameLC.'Overview');
        }

        //check form cmd
        if(!empty($_GET['cmd']) && $arrIds[0] != 'search') {
            $arrFormCmd = array();

            $objForms = new MediaDirectoryForm(null, $this->moduleName);
            foreach ($objForms->arrForms as $intFormId => $arrForm) {
                if(!empty($arrForm['formCmd'])) {
                    $arrFormCmd[$arrForm['formCmd']] = intval($intFormId);
                }
            }

            if(!empty($arrFormCmd[$_GET['cmd']])) {
                $intCmdFormId = intval($arrFormCmd[$_GET['cmd']]);
            }
        }

        // check if categories and levels shall be listed
        if ($this->_objTpl->blockExists($this->moduleNameLC.'CategoriesLevelsList')){
            $listCategoriesAndLevels = true;
            if ($intCmdFormId != 0) {   
                $bolFormUseCategory = $objForms->arrForms[intval($intCmdFormId)]['formUseCategory'];
                $bolFormUseLevel = $objForms->arrForms[intval($intCmdFormId)]['formUseLevel'];
            } else {     
                $bolFormUseCategory = true;
                $bolFormUseLevel = $this->arrSettings['settingsShowLevels'];
            }
        }

        // check if latest entries shall be listed instead of the regular listing
        if (($intCategoryId == 0 && $bolFormUseCategory) || ($intLevelId == 0  && $bolFormUseLevel)) {
            $bolLatest = true;
            $intLimitEnd = intval($this->arrSettings['settingsLatestNumOverview']);
        } else {
            $bolLatest   = false;
            $intLimitEnd = intval($this->arrSettings['settingsPagingNumEntries']);
            if (    !empty($intCmdFormId)
                &&  !empty($objForms->arrForms[$intCmdFormId]['formEntriesPerPage'])
            ) {
                $intLimitEnd = $objForms->arrForms[$intCmdFormId]['formEntriesPerPage'];
            }
        }

        //get navtree
        if($this->_objTpl->blockExists($this->moduleNameLC.'Navtree') && ($intCategoryId != 0 || $intLevelId != 0)){
            $this->getNavtree($intCategoryId, $intLevelId);
        }

        //get searchform
        if($this->_objTpl->blockExists($this->moduleNameLC.'Searchform')){
            $objSearch = new MediaDirectorySearch($this->moduleName);
            $objSearch->getSearchform($this->_objTpl, 1);
        }

        //get level / category details
        if($this->_objTpl->blockExists($this->moduleNameLC.'CategoryLevelDetail')){
            if ($intCategoryId == 0 && $intLevelId != 0 && $this->arrSettings['settingsShowLevels']) {
                $objLevel = new MediaDirectoryLevel($intLevelId, null, 0, $this->moduleName);
                $showLevelDetails = true;
            }

            if($intCategoryId != 0) {
                $objCategory = new MediaDirectoryCategory($intCategoryId, null, 0, $this->moduleName);
                $showCategoryDetails = true;
            }
        }

        // check show entries
        $showEntries = $showEntriesOfLevel || $showEntriesOfCategory || $bolLatest || (!$bolFormUseCategory && !$bolFormUseLevel);

        if ($showEntries) {
            $objEntries = new MediaDirectoryEntry($this->moduleName);
// TODO: Show all entries regardless of set pagging
            $objEntries->getEntries(null,$intLevelId,$intCategoryId,null,$bolLatest,null,1,$intLimitStart, $intLimitEnd, null, null, $intCmdFormId);
        }

        if ($showLevelDetails) {
            $objLevel->listLevels($this->_objTpl, 5, $intLevelId);
            $showEntriesOfLevel = $objLevel->arrLevels[$intLevelId]['levelShowEntries'];
        }

        if ($objLevel) {
            // only set page's title to level's name
            // if not in legacy mode
            if (!$this->arrSettings['legacyBehavior']) {
                $this->pageTitle = $objLevel->arrLevels[$intLevelId]['levelName'][0];
            }
            $this->metaDescription = $objLevel->arrLevels[$intLevelId]['levelDescription'][0];
            $this->metaImage = $objLevel->arrLevels[$intLevelId]['levelPicture'];
        }

        if ($showCategoryDetails) {
            $objCategory->listCategories($this->_objTpl, 5, $intCategoryId);
            $showEntriesOfCategory = $objCategory->arrCategories[$intCategoryId]['catShowEntries'];
        }

        if ($objCategory) {
            // only set page's title to category's name
            // if not in legacy mode
            if (!$this->arrSettings['legacyBehavior']) {
                $this->pageTitle = $objCategory->arrCategories[$intCategoryId]['catName'][0];
            }
            $this->metaDescription = $objCategory->arrCategories[$intCategoryId]['catDescription'][0];
            $this->metaImage = $objCategory->arrCategories[$intCategoryId]['catPicture'];
        }

        //list levels / categories
        if ($listCategoriesAndLevels) {
            if($this->arrSettings['settingsShowLevels'] == 1 && $intCategoryId == 0 && $bolFormUseLevel) {
                $objLevels = new MediaDirectoryLevel(null, $intLevelId, 1, $this->moduleName);
                $objCategories = new MediaDirectoryCategory(null, $intCategoryId, 1, $this->moduleName);
                $objLevels->listLevels($this->_objTpl, 2, null, null, null, $arrExistingBlocks);
                $this->_objTpl->clearVariables();
                $this->_objTpl->parse($this->moduleNameLC.'CategoriesLevelsList');
            }

            if((((isset($objLevel) && $objLevel->arrLevels[$intLevelId]['levelShowCategories'] == 1) || $intLevelId === 0) || $this->arrSettings['settingsShowLevels'] == 0 || $intCategoryId != 0) || ($bolFormUseCategory && !$bolFormUseLevel)) {
                $objCategories = new MediaDirectoryCategory(null, $intCategoryId, 1, $this->moduleName);
                $objCategories->listCategories($this->_objTpl, 2, null, null, null, $arrExistingBlocks);
                $this->_objTpl->clearVariables();
                $this->_objTpl->parse($this->moduleNameLC.'CategoriesLevelsList');
            }

            if(empty($objLevel->arrLevels) && empty($objCategories->arrCategories)) {
                $this->_objTpl->hideBlock($this->moduleNameLC.'CategoriesLevelsList');
                $this->_objTpl->clearVariables();
            }
        }

        //latest title
        if($this->_objTpl->blockExists($this->moduleNameLC.'LatestTitle') && $intCategoryId == 0 && $intLevelId == 0){
            $this->_objTpl->touchBlock($this->moduleNameLC.'LatestTitle');
        }

        //list entries
        if(!$this->_objTpl->blockExists($this->moduleNameLC.'EntryList')){
            return;
        }

        if ($showEntries) {
            $objEntries->listEntries($this->_objTpl, 2);
            
            if(!$bolLatest) {
                $intNumEntries = intval($objEntries->countEntries());
                if($intNumEntries > $intLimitEnd) {
                    $objUrl           = clone \Env::get('Resolver')->getUrl();                        
                    $currentUrlParams = $objUrl->getSuggestedParams();
                    $strPaging = getPaging($intNumEntries, $intLimitStart, $currentUrlParams, "<b>".$_ARRAYLANG['TXT_MEDIADIR_ENTRIES']."</b>", true, $intLimitEnd);
                    $this->_objTpl->setGlobalVariable(array(
                        $this->moduleLangVar.'_PAGING' =>  $strPaging
                    ));
                }
            }
        }

        //no entries found
        if(empty($objEntries->arrEntries)) {
            $this->_objTpl->hideBlock($this->moduleNameLC.'EntryList');
            $this->_objTpl->clearVariables();
        }
    }



    function showAlphabetical()
    {
        global $_ARRAYLANG, $_CORELANG;

        $this->_objTpl->setTemplate($this->pageContent, true, true);

        //get navtree
        if($this->_objTpl->blockExists($this->moduleNameLC.'Navtree') && ($intCategoryId != 0 || $intLevelId != 0)){
            $this->getNavtree($intCategoryId, $intLevelId);
        }

        //get searchform
        $searchTerm = null;
        if($this->_objTpl->blockExists($this->moduleNameLC.'Searchform')){
            $objSearch = new MediaDirectorySearch($this->moduleName);
            $objSearch->getSearchform($this->_objTpl, 1);
            $searchTerm = isset($_GET['term']) ? contrexx_input2raw($_GET['term']) : null;
        }

        $objEntries = new MediaDirectoryEntry($this->moduleName);
        $objEntries->getEntries(null,null,null,$searchTerm,false,null,true);
        $objEntries->listEntries($this->_objTpl,3);
    }



    function showSearch()
    {
        global $_ARRAYLANG, $_CORELANG;

        $this->_objTpl->setTemplate($this->pageContent, true, true);

        //get searchform
        if($this->_objTpl->blockExists($this->moduleNameLC.'Searchform')){
            $objSearch = new MediaDirectorySearch($this->moduleName);
            $objSearch->getSearchform($this->_objTpl);
        }

        $_GET['term'] = trim($_GET['term']);

        $cmd         = isset($_GET['cmd']) ? contrexx_input2raw($_GET['cmd']) : '';
        $intLimitEnd = intval($this->arrSettings['settingsPagingNumEntries']);
        if (!empty($cmd)) {
            $objForms = new MediaDirectoryForm(null, $this->moduleName);
            foreach ($objForms->arrForms as $intFormId => $arrForm) {
                if (    !empty($arrForm['formCmd'])
                    &&  $arrForm['formCmd'] === $cmd
                    &&  !empty($arrForm['formEntriesPerPage'])
                ) {
                    $intLimitEnd = $arrForm['formEntriesPerPage'];
                    break;
                }
            }
        }

        $intLimitStart = isset($_GET['pos']) ? intval($_GET['pos']) : 0;

        if(!empty($_GET['term']) || $_GET['type'] == 'exp') {
            $objSearch = new MediaDirectorySearch($this->moduleName);
            $objSearch->searchEntries($_GET);

            $objEntries = new MediaDirectoryEntry($this->moduleName);

            if(!empty($objSearch->arrFoundIds)) {
                $intNumEntries = count($objSearch->arrFoundIds);

                for($i=$intLimitStart; $i < ($intLimitStart+$intLimitEnd); $i++) {
                    $intEntryId = isset($objSearch->arrFoundIds[$i]) ? $objSearch->arrFoundIds[$i] : 0;
                    if(intval($intEntryId) != 0) {
                        $objEntries->getEntries($intEntryId, null, null, null, null, null, 1, 0, 1, null, null);
                    }
                }

                $objEntries->listEntries($this->_objTpl, 2);

                // parse GoogleMap
                $this->parseGoogleMapPlaceholder($this->_objTpl, $this->moduleLangVar.'_SEARCH_GOOGLE_MAP');
                
                $urlParams = $_GET;
                unset($urlParams['pos']);
                unset($urlParams['section']);

                if($intNumEntries > $intLimitEnd) {
                    $strPaging = getPaging($intNumEntries, $intLimitStart, $urlParams, "<b>".$_ARRAYLANG['TXT_MEDIADIR_ENTRIES']."</b>", true, $intLimitEnd);
                    $this->_objTpl->setGlobalVariable(array(
                        $this->moduleLangVar.'_PAGING' =>  $strPaging
                    ));
                }
            } else {
                $this->_objTpl->setVariable(array(
                    'TXT_'.$this->moduleLangVar.'_SEARCH_MESSAGE' =>  $_ARRAYLANG['TXT_MEDIADIR_NO_ENTRIES_FOUND'],
                ));
            }
        } else {
            $this->_objTpl->setVariable(array(
                'TXT_'.$this->moduleLangVar.'_SEARCH_MESSAGE' =>  $_ARRAYLANG['TXT_MEDIADIR_NO_SEARCH_TERM'],
            ));
        }

    }




    function showEntry()
    {
        global $_ARRAYLANG, $_CORELANG;

        $this->_objTpl->setTemplate($this->pageContent, true, true);

        //get ids
        $intCategoryId = isset($_GET['cid']) ? intval($_GET['cid']) : 0;
        $intLevelId = isset($_GET['lid']) ? intval($_GET['lid']) : 0;
        $intEntryId = isset($_GET['eid']) ? intval($_GET['eid']) : 0;

        // load source code if cmd value is integer
        if ($this->_objTpl->placeholderExists('APPLICATION_DATA')) {
            $page = new \Cx\Core\ContentManager\Model\Entity\Page();
            $page->setVirtual(true);
            $page->setType(\Cx\Core\ContentManager\Model\Entity\Page::TYPE_APPLICATION);
            $page->setModule('MediaDir');
            $page->setCmd('detail');
            // load source code
            $applicationTemplate = \Cx\Core\Core\Controller\Cx::getContentTemplateOfPage($page);
            \LinkGenerator::parseTemplate($applicationTemplate);
            $this->_objTpl->addBlock('APPLICATION_DATA', 'application_data', $applicationTemplate);
        }

        //get navtree
        if($this->_objTpl->blockExists($this->moduleNameLC.'Navtree') && ($intCategoryId != 0 || $intLevelId != 0)){
            $this->getNavtree($intCategoryId, $intLevelId);
        }

        if($intEntryId != 0 && $this->_objTpl->blockExists($this->moduleNameLC.'EntryList')) {
            $objEntry = new MediaDirectoryEntry($this->moduleName);
            $objEntry->getEntries($intEntryId,$intLevelId,$intCategoryId,null,null,null,1,null,1);
            $objEntry->listEntries($this->_objTpl, 2);
            $objEntry->updateHits($intEntryId);

            //set meta attributes
            $entry = $objEntry->arrEntries[$intEntryId];

            $objInputfields = new MediaDirectoryInputfield($entry['entryFormId'], false, $entry['entryTranslationStatus'], $this->moduleName);
            $inputFields = $objInputfields->getInputfields();

            $titleChanged = false;
            $contentChanged = false;

            foreach ($inputFields as $arrInputfield) {
                $contextType = isset($arrInputfield['context_type']) ? $arrInputfield['context_type'] : '';
                if (!in_array($contextType, array('title', 'content', 'image'))) {
                    continue;
                }
                $strType = isset($arrInputfield['type_name']) ? $arrInputfield['type_name'] : '';
                $strInputfieldClass = "\Cx\Modules\MediaDir\Model\Entity\MediaDirectoryInputfield" . ucfirst($strType);
                try {
                    $objInputfield = safeNew($strInputfieldClass, $this->moduleName);
                    $arrTranslationStatus = (contrexx_input2int($arrInputfield['type_multi_lang']) == 1)
                        ? $entry['entryTranslationStatus']
                        : null;
                    $arrInputfieldContent = $objInputfield->getContent($entry['entryId'], $arrInputfield, $arrTranslationStatus);
                    switch ($contextType) {
                        case 'title':
                            $inputfieldValue = $arrInputfieldContent[$this->moduleLangVar . '_INPUTFIELD_VALUE'];
                            if ($inputfieldValue) {
                                $this->metaTitle .= ' - ' . $inputfieldValue;
                                $this->pageTitle = $inputfieldValue;
                            }
                            $titleChanged = true;
                            break;
                        case 'content':
                            $inputfieldValue = $arrInputfieldContent[$this->moduleLangVar . '_INPUTFIELD_VALUE'];
                            if ($inputfieldValue) {
                                $this->metaDescription = $inputfieldValue;
                            }
                            $contentChanged = true;
                            break;
                        case 'image':
                            $inputfieldValue = $arrInputfieldContent[$this->moduleLangVar . '_INPUTFIELD_VALUE_SRC'];
                            if ($inputfieldValue) {
                                $this->metaImage = $inputfieldValue;
                            }
                            break;
                        default:
                            break;
                    }
                } catch (\Exception $e) {
                    \DBG::log($e->getMessage());
                    continue;
                }
            }

            $firstInputfieldValue = $objEntry->arrEntries[$intEntryId]['entryFields'][0];
            if (!$titleChanged && $firstInputfieldValue) {
                $this->pageTitle = $firstInputfieldValue;
                $this->metaTitle = $firstInputfieldValue;
            }
            if (!$contentChanged && $firstInputfieldValue) {
                $this->metaDescription = $firstInputfieldValue;
            }

            if(empty($objEntry->arrEntries)) {
                $this->_objTpl->hideBlock($this->moduleNameLC.'EntryList');
                $this->_objTpl->clearVariables();

                header("Location: index.php?section=".$this->moduleName);
                exit;
            }
        } else {
            header("Location: index.php?section=".$this->moduleName);
            exit;
        }
    }



    function showMap()
    {
        global $_ARRAYLANG, $_CORELANG;

        $this->_objTpl->setTemplate($this->pageContent, true, true);

        $objEntry = new MediaDirectoryEntry($this->moduleName);
        $objEntry->getEntries(null,null,null,null,null,null,true);
        $objEntry->listEntries($this->_objTpl, 4);
    }



    function showLatest()
    {
        global $_ARRAYLANG, $_CORELANG;

        $this->_objTpl->setTemplate($this->pageContent, true, true);

        //get searchform
        if($this->_objTpl->blockExists($this->moduleNameLC.'Searchform')){
            $objSearch = new MediaDirectorySearch($this->moduleName);
            $objSearch->getSearchform($this->_objTpl, 1);
        }

        $objEntry = new MediaDirectoryEntry($this->moduleName);
        $objEntry->getEntries(null, null, null, null, true, null, true, null, $this->arrSettings['settingsLatestNumFrontend']);
        $objEntry->listEntries($this->_objTpl, 2);
    }


    function showMyEntries()
    {
        global $_ARRAYLANG, $_CORELANG;

        $this->_objTpl->setTemplate($this->pageContent, true, true);

        //get user attributes
        $objFWUser  = \FWUser::getFWUserObject();
        $objUser    = $objFWUser->objUser;
        $intUserId  = intval($objUser->getId());

        //get searchform
        if($this->_objTpl->blockExists($this->moduleNameLC.'Searchform')){
            $objSearch = new MediaDirectorySearch($this->moduleName);
            $objSearch->getSearchform($this->_objTpl, 1);
        }

        $objEntry = new MediaDirectoryEntry($this->moduleName);

        if($this->arrSettings['settingsReadyToConfirm'] == 1) {
            $objEntry->getEntries(null, null, null, null, null, null, true, null, 'n', $intUserId, null, null, true);
        } else {
            $objEntry->getEntries(null, null, null, null, null, null, true, null, 'n', $intUserId);
        }

        $objEntry->listEntries($this->_objTpl, 2);
    }

    /**
     * Show the latest entries
     *
     * @param \Cx\Core\Html\Sigma $template  template object
     * @param integer             $formId    form ID
     * @param string              $blockName block name
     */
    function getLatestEntries(
        \Cx\Core\Html\Sigma $template,
        $formId = null,
        $blockName = null
    ) {
        $objEntry = new MediaDirectoryEntry($this->moduleName);
        $objEntry->getEntries(
            null,
            null,
            null,
            null,
            true,
            null,
            true,
            null,
            $this->arrSettings['settingsLatestNumHeadlines'],
            null,
            null,
            $formId
        );
        if ($blockName == null) {
            $objEntry->setStrBlockName($this->moduleNameLC . 'Latest');
        } else {
            $objEntry->setStrBlockName($blockName);
        }

        $objEntry->listEntries($template, 2);
    }

    /**
     * Get headlines
     *
     * @param \Cx\Core\Html\Sigma $template         template object
     * @param integer             $startingPosition entries starting position
     * @param integer             $diffCount        diff count
     *
     * @return null
     */
    function getHeadlines(
        \Cx\Core\Html\Sigma $template,
        $startingPosition,
        $diffCount
    ) {
        global $_ARRAYLANG;

        $objEntry = new MediaDirectoryEntry($this->moduleName);
        $objEntry->getEntries(
            null,
            null,
            null,
            null,
            null,
            null,
            true,
            null,
            $this->arrSettings['settingsLatestNumHeadlines']
        );

        if (empty($objEntry->arrEntries)) {
            return;
        }

        $i        = 1;
        $r        = 0;
        $position = $startingPosition;
        foreach ($objEntry->arrEntries as $key => $arrEntry) {
            if ($i != $position) {
                $i++;
                continue;
            }

            $strDetailCmd = 'detail';
            if ($objEntry->checkPageCmd('detail' . intval($arrEntry['entryFormId']))) {
                $strDetailCmd = 'detail' . intval($arrEntry['entryFormId']);
            }

            $detailUrl = \Cx\Core\Routing\Url::fromModuleAndCmd(
                $this->moduleName,
                $strDetailCmd,
                '',
                array('eid' => $arrEntry['entryId'])
            )->toString();
            $template->setVariable(array(
                $this->moduleLangVar.'_LATEST_ROW_CLASS'           =>
                    ($r % 2 == 0) ? 'row1' : 'row2',
                $this->moduleLangVar.'_LATEST_ENTRY_ID'            =>
                    contrexx_raw2xhtml($arrEntry['entryId']),
                $this->moduleLangVar.'_LATEST_ENTRY_VALIDATE_DATE' =>
                    date('H:i:s - d.m.Y', contrexx_raw2xhtml($arrEntry['entryValdateDate'])),
                $this->moduleLangVar.'_LATEST_ENTRY_CREATE_DATE'   =>
                    date('H:i:s - d.m.Y', contrexx_raw2xhtml($arrEntry['entryCreateDate'])),
                $this->moduleLangVar.'_LATEST_ENTRY_HITS'          =>
                    contrexx_raw2xhtml($arrEntry['entryHits']),
                $this->moduleLangVar.'_ENTRY_DETAIL_URL'           => $detailUrl,
                'TXT_'.$this->moduleLangVar.'_ENTRY_DETAIL'        =>
                    $_ARRAYLANG['TXT_MEDIADIR_DETAIL']
            ));

            foreach ($arrEntry['entryFields'] as $key => $strFieldValue) {
                $intPos = $key + 1;
                $template->setVariable(array(
                    $this->moduleLangVar . '_LATEST_ENTRY_FIELD_' . $intPos . '_POS' => $strFieldValue
                ));
            }

            $template->parse($this->moduleNameLC . 'Latest_row_' . $startingPosition . '_' . $diffCount);
            $i++;
            $position += $diffCount;
        }
    }


    function showPopular()
    {
        global $_ARRAYLANG, $_CORELANG;

        $this->_objTpl->setTemplate($this->pageContent, true, true);

        //get searchform
        if($this->_objTpl->blockExists($this->moduleNameLC.'Searchform')){
            $objSearch = new MediaDirectorySearch($this->moduleName);
            $objSearch->getSearchform($this->_objTpl, 1);
        }

        $objEntry = new MediaDirectoryEntry($this->moduleName);
        $objEntry->getEntries(null, null, null, null, null, null, true, null, $this->arrSettings['settingsPopularNumFrontend'], null, true);
        $objEntry->listEntries($this->_objTpl, 2);
    }



    function modifyEntry()
    {
        global $_ARRAYLANG, $_CORELANG;

        $this->_objTpl->setTemplate($this->pageContent, true, true);

        parent::getSettings();

        $bolFileSizesStatus = true;
        $strOkMessage = '';
        $strErrMessage = '';
        $strOnSubmit = '';
        //count forms
        $objForms = new MediaDirectoryForm(null, $this->moduleName);
        $arrActiveForms = array();

        foreach ($objForms->arrForms as $intFormId => $arrForm) {
            if($arrForm['formActive'] == 1) {
                $arrActiveForms[] = $intFormId;
            }
        }

        //check id and form
        if(!empty($_REQUEST['eid']) || !empty($_REQUEST['entryId'])) {
            if(!empty($_REQUEST['eid'])) {
                $intEntryId = intval($_REQUEST['eid']);
            }
            if(!empty($_REQUEST['entryId'])) {
                $intEntryId = intval($_REQUEST['entryId']);
            }
            $intFormId = intval(substr($_GET['cmd'],4));
        } else {
            $intEntryId = null;
            $intFormId = intval(substr($_GET['cmd'],3));
        }

        $intCountForms = count($arrActiveForms);

        if($intCountForms > 0){
            //check form
            if(intval($intEntryId) == 0 && empty($_REQUEST['selectedFormId']) && empty($_POST['formId']) && $intCountForms > 1 && $intFormId == 0 ) {
                $intFormId = null;

                //get form selector
                $objForms = new MediaDirectoryForm(null, $this->moduleName);
                $objForms->listForms($this->_objTpl, 3, $intFormId);

                //parse blocks
                $this->_objTpl->hideBlock($this->moduleNameLC.'Inputfields');
            } else {
                //save entry data
                if(isset($_POST['submitEntryModfyForm'])) {
                    $objEntry = new MediaDirectoryEntry($this->moduleName);
                    $strStatus = $objEntry->saveEntry($_POST, intval($_POST['entryId']));

                    if(!empty($_POST['entryId'])) {
                        $objEntry->getEntries(intval($_POST['entryId']));
                        if($strStatus == true) {
                            if (intval($_POST['readyToConfirm']) == 1) {
                                if($objEntry->arrEntries[intval($_POST['entryId'])]['entryConfirmed'] == 1) {
                                    $bolReadyToConfirmMessage = false;
                                    $bolSaveOnlyMessage = false;
                                } else {
                                    $bolReadyToConfirmMessage = true;
                                    $bolSaveOnlyMessage = false;
                                }
                            } else {
                                $bolReadyToConfirmMessage = false;
                                $bolSaveOnlyMessage = true;
                            }
                            $strOkMessage = $_ARRAYLANG['TXT_MEDIADIR_ENTRY']." ".$_ARRAYLANG['TXT_MEDIADIR_SUCCESSFULLY_EDITED'];
                        } else {
                            $strErrMessage = $_ARRAYLANG['TXT_MEDIADIR_ENTRY']." ".$_ARRAYLANG['TXT_MEDIADIR_CORRUPT_EDITED'];
                        }
                    } else {
                        if($strStatus == true) {
                            if (intval($_POST['readyToConfirm']) == 1) {
                                $bolReadyToConfirmMessage = true;
                                $bolSaveOnlyMessage = false;
                            } else {
                                $bolReadyToConfirmMessage = false;
                                $bolSaveOnlyMessage = true;
                            }
                            $strOkMessage = $_ARRAYLANG['TXT_MEDIADIR_ENTRY']." ".$_ARRAYLANG['TXT_MEDIADIR_SUCCESSFULLY_ADDED'];
                        } else {
                            $strErrMessage = $_ARRAYLANG['TXT_MEDIADIR_ENTRY']." ".$_ARRAYLANG['TXT_MEDIADIR_CORRUPT_ADDED'];
                        }
                    }

                    if(!empty($_POST['entryId'])) {
                        if($strStatus == true) {
                            $strOkMessage = $_ARRAYLANG['TXT_MEDIADIR_ENTRY']." ".$_ARRAYLANG['TXT_MEDIADIR_SUCCESSFULLY_EDITED'];
                        } else {
                            $strErrMessage = $_ARRAYLANG['TXT_MEDIADIR_ENTRY']." ".$_ARRAYLANG['TXT_MEDIADIR_CORRUPT_EDITED'];
                        }
                    } else {
                        if($strStatus == true) {
                            $strOkMessage = $_ARRAYLANG['TXT_MEDIADIR_ENTRY']." ".$_ARRAYLANG['TXT_MEDIADIR_SUCCESSFULLY_ADDED'];
                        } else {
                            $strErrMessage = $_ARRAYLANG['TXT_MEDIADIR_ENTRY']." ".$_ARRAYLANG['TXT_MEDIADIR_CORRUPT_ADDED'];
                        }
                    }
                } else {
                    //get form id
                    if(intval($intEntryId) != 0) {
                        //get entry data
                        $objEntry = new MediaDirectoryEntry($this->moduleName);
                        if($this->arrSettings['settingsReadyToConfirm'] == 1) {
                            $objEntry->getEntries($intEntryId,null,null,null,null,null,true,null,'n',null,null,null,true);
                        } else {
                            $objEntry->getEntries($intEntryId);
                        }

                        $intFormId = $objEntry->arrEntries[$intEntryId]['entryFormId'];
                    } else {
                         //set form id
                        if($intCountForms == 1) {
                            $intFormId = intval($arrActiveForms[0]);
                        } else {
                            if($intFormId == 0) {
                                $intFormId = intval($_REQUEST['selectedFormId']);
                            }
                        }
                    }

                    //get inputfield object
                    $objInputfields = new MediaDirectoryInputfield($intFormId, false, null, $this->moduleName);

                    //list inputfields
                    $objInputfields->listInputfields($this->_objTpl, 2, $intEntryId);

                    //get translation status date
                    if($this->arrSettings['settingsTranslationStatus'] == 1) {
                        foreach ($this->arrFrontendLanguages as $key => $arrLang) {
                            if ($arrLang['id'] == 2) {
                                $strLangStatus = 'checked="checked" disabled="disabled"';
                            } elseif ($intEntryId != 0) {
                                if(in_array($arrLang['id'], $objEntry->arrEntries[$intEntryId]['entryTranslationStatus'])) {
                                    $strLangStatus = 'checked="checked"';
                                } else {
                                    $strLangStatus = '';
                                }
                            } else {
                                $strLangStatus = '';
                            }

                            $this->_objTpl->setVariable(array(
                                'TXT_'.$this->moduleLangVar.'_TRANSLATION_LANG_NAME' => htmlspecialchars($arrLang['name'], ENT_QUOTES, CONTREXX_CHARSET),
                                $this->moduleLangVar.'_TRANSLATION_LANG_ID' => intval($arrLang['id']),
                                $this->moduleLangVar.'_TRANSLATION_LANG_STATUS' => $strLangStatus,
                            ));

                            $this->_objTpl->parse($this->moduleNameLC.'TranslationLangList');
                        }
                    } else {
                        $this->_objTpl->hideBlock($this->moduleNameLC.'TranslationStatus');
                    }

                    //get ready to confirm
                    if($this->arrSettings['settingsReadyToConfirm'] == 1 && empty($objEntry->arrEntries[$intEntryId]['entryReadyToConfirm']) && empty($objEntry->arrEntries[$intEntryId]['entryConfirmed'])) {
                        $objForm = new MediaDirectoryForm($intFormId, $this->moduleName);
                        if($objForm->arrForms[$intFormId]['formUseReadyToConfirm'] == 1) {
                            $strReadyToConfirm = '<p><input class="'.$this->moduleNameLC.'InputfieldCheckbox" name="readyToConfirm" id="'.$this->moduleNameLC.'Inputfield_ReadyToConfirm" value="1" type="checkbox">&nbsp;'.$_ARRAYLANG['TXT_MEDIADIR_READY_TO_CONFIRM'].'</p>';
                        } else {
                            $strReadyToConfirm = '<input type="hidden" name="readyToConfirm" value="1" />';
                        }
                    } else {
                        $strReadyToConfirm = '<input type="hidden" name="readyToConfirm" value="1" />';
                    }

                    $this->_objTpl->setVariable(array(
                        $this->moduleLangVar.'_READY_TO_CONFIRM' => $strReadyToConfirm,
                    ));

                    //generate javascript
                    parent::setJavascript($this->getSelectorJavascript());
                    parent::setJavascript($objInputfields->getInputfieldJavascript());
                    //parent::setJavascript("\$J().ready(function(){ \$J('.mediadirInputfieldHint').inputHintBox({className:'mediadirInputfieldInfobox',incrementLeft:3,incrementTop:-6}); });");

                    //get form onsubmit
                    $strOnSubmit = parent::getFormOnSubmit($objInputfields->arrJavascriptFormOnSubmit);

                    //parse blocks
                    $this->_objTpl->hideBlock($this->moduleNameLC.'Forms');
                }
            }

            if (!empty($_SESSION[$this->moduleNameLC]) && empty($_SESSION[$this->moduleNameLC]['bolFileSizesStatus'])) {
                $strFileMessage = '<div class="'.$this->moduleNameLC.'FileErrorMessage">'.$_ARRAYLANG['TXT_MEDIADIR_IMAGE_ERROR_MESSAGE'].'</div>';
                unset($_SESSION[$this->moduleNameLC]['bolFileSizesStatus']);
            } else {
                $strFileMessage = '';
            }


            //parse global variables
            $this->_objTpl->setVariable(array(
                $this->moduleLangVar.'_ENTRY_ID' => $intEntryId,
                $this->moduleLangVar.'_FORM_ID' => $intFormId,
                'TXT_'.$this->moduleLangVar.'_SUBMIT' =>  $_ARRAYLANG['TXT_'.$this->moduleLangVar.'_SUBMIT'],
                $this->moduleLangVar.'_FORM_ONSUBMIT' =>  $strOnSubmit,
                'TXT_'.$this->moduleLangVar.'_PLEASE_CHECK_INPUT' =>  $_ARRAYLANG['TXT_MEDIADIR_PLEASE_CHECK_INPUT'],
                'TXT_'.$this->moduleLangVar.'_OK_MESSAGE' =>  $strOkMessage.$strFileMessage,
                'TXT_'.$this->moduleLangVar.'_ERROR_MESSAGE' =>  $strErrMessage.$strFileMessage,
                $this->moduleLangVar.'_MAX_CATEGORY_SELECT' =>  $strErrMessage,
                'TXT_'.$this->moduleLangVar.'_TRANSLATION_STATUS' => $_ARRAYLANG['TXT_MEDIADIR_TRANSLATION_STATUS'],
            ));

            if(!empty($strOkMessage)) {
                $this->_objTpl->touchBlock($this->moduleNameLC.'EntryOkMessage');
                $this->_objTpl->hideBlock($this->moduleNameLC.'EntryErrMessage');
                $this->_objTpl->hideBlock($this->moduleNameLC.'EntryModifyForm');
                if($bolReadyToConfirmMessage) {
                    $this->_objTpl->touchBlock($this->moduleNameLC.'EntryReadyToConfirmMessage');
                    $this->_objTpl->hideBlock($this->moduleNameLC.'EntryOkMessage');
                }
                if($bolSaveOnlyMessage) {
                    $this->_objTpl->touchBlock($this->moduleNameLC.'EntrySaveOnlyMessage');
                    $this->_objTpl->hideBlock($this->moduleNameLC.'EntryOkMessage');
                }
            } else if(!empty($strErrMessage)) {
                $this->_objTpl->hideBlock($this->moduleNameLC.'EntryOkMessage');
                $this->_objTpl->touchBlock($this->moduleNameLC.'EntryErrMessage');
                $this->_objTpl->hideBlock($this->moduleNameLC.'EntryModifyForm');
            } else {
                $this->_objTpl->hideBlock($this->moduleNameLC.'EntryOkMessage');
                $this->_objTpl->hideBlock($this->moduleNameLC.'EntryErrMessage');
                $this->_objTpl->parse($this->moduleNameLC.'EntryModifyForm');
                $this->_objTpl->hideBlock($this->moduleNameLC.'EntryReadyToConfirmMessage');
                $this->_objTpl->hideBlock($this->moduleNameLC.'EntrySaveOnlyMessage');
            }
        } else {
            header("Location: index.php?section=".$_GET['section']);
            exit;
        }

    }



    function deleteEntry()
    {
        global $_ARRAYLANG, $_CORELANG;

        $this->_objTpl->setTemplate($this->pageContent, true, true);

        //save entry data
        if(isset($_POST['submitEntryModfyForm']) && intval($_POST['entryId'])) {
            $objEntry = new MediaDirectoryEntry($this->moduleName);

            $strStatus = $objEntry->deleteEntry(intval($_POST['entryId']));

            if($strStatus == true) {
                $strOkMessage = $_ARRAYLANG['TXT_MEDIADIR_ENTRY']." ".$_ARRAYLANG['TXT_MEDIADIR_SUCCESSFULLY_DELETED'];
            } else {
                $strErrMessage = $_ARRAYLANG['TXT_MEDIADIR_ENTRY']." ".$_ARRAYLANG['TXT_MEDIADIR_CORRUPT_DELETED'];
            }
        }

         //check id
        if(intval($_GET['eid']) != 0) {
            $intEntryId = intval($_GET['eid']);
        } else {
            $intEntryId = null;
        }

        $objEntry = new MediaDirectoryEntry($this->moduleName);

        if($this->arrSettings['settingsReadyToConfirm'] == 1) {
            $objEntry->getEntries($intEntryId,null,null,null,null,null,true,null,1,null,null,null,true);
        } else {
            $objEntry->getEntries($intEntryId,null,null,null,null,null,1,null,1);
        }


        $objEntry->listEntries($this->_objTpl, 2);

        //parse global variables
        $this->_objTpl->setVariable(array(
            $this->moduleLangVar.'_ENTRY_ID' => $intEntryId,
            'TXT_'.$this->moduleLangVar.'_DELETE' =>  $_CORELANG['TXT_ACCESS_DELETE_ENTRY'],
            'TXT_'.$this->moduleLangVar.'_ABORT' =>  $_CORELANG['TXT_CANCEL'],
            'TXT_'.$this->moduleLangVar.'_OK_MESSAGE' =>  $strOkMessage,
            'TXT_'.$this->moduleLangVar.'_ERROR_MESSAGE' =>  $strErrMessage,
        ));

        if(!empty($strOkMessage)) {
            $this->_objTpl->parse($this->moduleNameLC.'EntryOkMessage');
            $this->_objTpl->hideBlock($this->moduleNameLC.'EntryErrMessage');
            $this->_objTpl->hideBlock($this->moduleNameLC.'EntryModifyForm');
        } else if(!empty($strErrMessage)) {
            $this->_objTpl->hideBlock($this->moduleNameLC.'EntryOkMessage');
            $this->_objTpl->parse($this->moduleNameLC.'EntryErrMessage');
            $this->_objTpl->parse($this->moduleNameLC.'EntryModifyForm');
        } else {
            $this->_objTpl->hideBlock($this->moduleNameLC.'EntryOkMessage');
            $this->_objTpl->hideBlock($this->moduleNameLC.'EntryErrMessage');
            $this->_objTpl->parse($this->moduleNameLC.'EntryModifyForm');
        }
    }



    function getNavtree($intCategoryId, $intLevelId)
    {
        global $_ARRAYLANG;

        if($intCategoryId != 0) {
           $this->getNavtreeCategories($intCategoryId);
        }

        if($intLevelId != 0 && $this->arrSettings['settingsShowLevels'] == 1) {
           $this->getNavtreeLevels($intLevelId);
        }

        //set pagetitle
        krsort($this->arrNavtree);
        $this->metaTitle = $this->pageTitle." - ".strip_tags(join(" - ", $this->arrNavtree));

        if(isset($_GET['cmd'])) {
            $strOverviewCmd = '&amp;cmd='.$_GET['cmd'];
        } else {
            $strOverviewCmd = null;
        }


        $this->arrNavtree[] = '<a href="?section='.$this->moduleName.$strOverviewCmd.'">'.$_ARRAYLANG['TXT_MEDIADIR_OVERVIEW'].'</a>';
        krsort($this->arrNavtree);

        if(!empty($this->arrNavtree)) {
            $i = 0;
            $count = count($this->arrNavtree);
            foreach ($this->arrNavtree as $key => $strName) {
                $strClass = $i == $count -1 ? 'last' : '';
                $strSeparator = $i == 0 ? '' : '&gt;';

                $this->_objTpl->setVariable(array(
                    $this->moduleLangVar.'_NAVTREE_LINK'    =>  $strName,
                    $this->moduleLangVar.'_NAVTREE_LINK_CLASS'    =>  $strClass,
                    $this->moduleLangVar.'_NAVTREE_SEPARATOR'    =>  $strSeparator
                ));

                $i++;
                $this->_objTpl->parse($this->moduleNameLC.'NavtreeElement');
            }
            $this->_objTpl->parse($this->moduleNameLC.'Navtree');
        } else {
            $this->_objTpl->hideBlock($this->moduleNameLC.'Navtree');
        }
    }



    function getNavtreeCategories($intCategoryId)
    {
        $objCategory = new MediaDirectoryCategory($intCategoryId, null, 0, $this->moduleName);
        $objCategory->arrCategories[$intCategoryId];

        $strLevelId = isset($_GET['lid']) ? "&amp;lid=".intval($_GET['lid']) : '';
        if(isset($_GET['cmd'])) {
            $strCategoryCmd = '&amp;cmd='.$_GET['cmd'];
        } else {
            $strCategoryCmd = null;
        }
        $this->arrNavtree[] = '<a href="?section='.$this->moduleName.$strCategoryCmd.$strLevelId.'&amp;cid='.$objCategory->arrCategories[$intCategoryId]['catId'].'">'.contrexx_raw2xhtml($objCategory->arrCategories[$intCategoryId]['catName'][0]).'</a>';

        if($objCategory->arrCategories[$intCategoryId]['catParentId'] != 0) {
            $this->getNavtreeCategories($objCategory->arrCategories[$intCategoryId]['catParentId']);
        }
    }



    function getNavtreeLevels($intLevelId)
    {
        $objLevel = new MediaDirectoryLevel($intLevelId, null, 0, $this->moduleName);
        $objLevel->arrLevels[$intLevelId];

        if(isset($_GET['cmd'])) {
            $strLevelCmd = '&amp;cmd='.$_GET['cmd'];
        } else {
            $strLevelCmd = null;
        }
        $this->arrNavtree[] = '<a href="?section='.$this->moduleName.$strLevelCmd.'&amp;lid='.$objLevel->arrLevels[$intLevelId]['levelId'].'">'.contrexx_raw2xhtml($objLevel->arrLevels[$intLevelId]['levelName'][0]).'</a>';

        if($objLevel->arrLevels[$intLevelId]['levelParentId'] != 0) {
            $this->getNavtreeLevels($objLevel->arrLevels[$intLevelId]['levelParentId']);
        }
    }


    public function getPageTitle() {
        return $this->pageTitle;
    }

    public function getMetaTitle() {
        return $this->metaTitle;
    }

    public function getMetaDescription() {
        return $this->metaDescription;
    }

    public function getMetaImage() {
        return $this->metaImage;
    }
}
