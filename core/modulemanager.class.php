<?php
/**
 * Modulemanager
 * @copyright   CONTREXX CMS - COMVATION AG
 * @author      Astalavista DEV Team <info@comvation.com>
 * @package     contrexx
 * @subpackage  core
 * @todo        Edit PHP DocBlocks!
 */

/**
 * Modulemanager
 *
 * This class manages the CMS Modules
 *
 * @copyright   CONTREXX CMS - COMVATION AG
 * @author      Astalavista DEV Team <info@comvation.com>
 * @package     contrexx
 * @subpackage  core
 */
class modulemanager
{
	var $strErrMessage = '';
	var $strOkMessage = '';
    var $txtInstalledModules = "";
    var $txtRemovedModules = "";
    var $arrayInstalledModules = array();
    var $arrayRemovedModules = array();
    var $langId;
    var $defaultOrderValue = 111;

    /**
    * Constructor
    *
    * @param  string
    * @access public
    */
    function modulemanager()
    {
		$this->__construct();
    }

    function __construct()
    {
    	global $objInit;

    	$this->langId=$objInit->userFrontendLangId;
    }


    function getModulesPage()
    {
    	global $objPerm, $_CORELANG, $objTemplate;

    	$objTemplate->setVariable(array(
    	    'CONTENT_TITLE'      => $_CORELANG['TXT_MODULE_MANAGER'],
    	    'CONTENT_NAVIGATION' => "<a href='index.php?cmd=modulemanager'>".$_CORELANG['TXT_MODULE_MANAGER']."</a>"
    	                             //<a href='index.php?cmd=modulemanager&act=manage'>[".$_CORELANG['TXT_MODULE_ACTIVATION']."]</a>"
    	));

    	$objTemplate->addBlockfile('ADMIN_CONTENT', 'module_manager', 'module_manager.html');

    	$objTemplate->setVariable(array(
    	    'TXT_NAME'                   => $_CORELANG['TXT_NAME'],
    	    'TXT_CONFIRM_DELETE_DATA'    => $_CORELANG['TXT_CONFIRM_DELETE_DATA'],
    	    'TXT_ACTION_IS_IRREVERSIBLE' => $_CORELANG['TXT_ACTION_IS_IRREVERSIBLE'],
    	    'TXT_DESCRIPTION'            => $_CORELANG['TXT_DESCRIPTION'],
    	    'TXT_STATUS'                 => $_CORELANG['TXT_STATUS'],
    	    'TXT_INSTALL_MODULE'         => $_CORELANG['TXT_INSTALL_MODULE'],
    	    'TXT_PROVIDE_MODULE'         => $_CORELANG['TXT_PROVIDE_MODULE'],
    	    'TXT_REMOVE_MODULE'          => $_CORELANG['TXT_REMOVE_MODULE'],
    	    'TXT_ACCEPT_CHANGES'         => $_CORELANG['TXT_ACCEPT_CHANGES'],
    	    'TXT_APPLICATION' => $_CORELANG['TXT_APPLICATION']
    	));

    	if(!isset($_GET['act'])) {
    	    $_GET['act']="";
    	}

        switch($_GET['act']){
			case "manage":
			    $objPerm->checkAccess(51, 'static');
                $this->manageModules();
			    break;

			case "edit":
			    $objPerm->checkAccess(52, 'static');
                $this->modModules();
                $this->showModules();
			    break;
			default:
    	        $objPerm->checkAccess(23, 'static');
                $this->showModules();
			    break;
		}

		$objTemplate->setVariable(array(
			'CONTENT_OK_MESSAGE'		=> $this->strOkMessage,
			'CONTENT_STATUS_MESSAGE'	=> $this->strErrMessage,
		));
    }

    function getModules()
    {
    	global $objDatabase;

    	$arrayInstalledModules = array();

    	$objResult = $objDatabase->Execute("SELECT module
    	            FROM ".DBPREFIX."content_navigation
    	           WHERE module<>0
    	             AND lang=".$this->langId."
    	        GROUP BY module");

    	if ($objResult !== false) {
    		$i=0;
    		while (!$objResult->EOF) {
    		    $arrayInstalledModules[$i] = $objResult->fields['module'];
    		    $i++;
    		    $objResult->MoveNext();
    		}
    	}

    	return $arrayInstalledModules;
    }

    function showModules()
    {
    	global $objDatabase, $_CORELANG, $_LANGID, $objTemplate;

    	$objTemplate->setVariable("MODULE_ACTION","edit");
    	$arrayInstalledModules = $this->getModules();

    	$query = "SELECT id,
    	                 name,
    	                 description_variable,
    	                 is_core,
    	                 is_required
    	            FROM ".DBPREFIX."modules
    	           WHERE status = 'y'
    	        ORDER BY is_required DESC, name ASC";
    	$i=0;
    	$objResult = $objDatabase->Execute($query);
    	if ($objResult !== false) {
    		while (!$objResult->EOF) {
    			$class = (($i % 2) == 0) ? "row1" : "row2";
    		    if(in_array($objResult->fields['id'],$arrayInstalledModules) || $objResult->fields['id'] == 6) {
    		    	$objTemplate->setVariable(array(
    		    		'MODULE_REMOVE'		=> "<input type='checkbox' name='removeModule[".$objResult->fields['id']."]' value='0' />",
    		    		'MODULE_INSTALL'	=> "&nbsp;",
    		    		'MODULE_STATUS'		=> "<img src='images/icons/led_green.gif' alt='' />"
    		    	));
                } else  {
    		    	$objTemplate->setVariable(array(
    		    		'MODULE_INSTALL'	=> "<input type='checkbox' name='installModule[".$objResult->fields['id']."]' value='1' />",
    		    		'MODULE_REMOVE'		=> "&nbsp;",
    		    		'MODULE_STATUS'		=> "<img src='images/icons/led_red.gif' alt='' />"
    		    	));
    		    }

    		    /*
    		    // core Modules
    		    if($db->f('is_core')==1){
    		    	$objTemplate->setVariable("MODULE_NAME", $db->f('name')." (core)");
    		    } else {
    		    	$objTemplate->setVariable("MODULE_NAME", $db->f('name'));
    		    }
    		    */

    		    $objTemplate->setVariable("MODULE_NAME", $objResult->fields['name']);

    		    // Required Modules
    		    if($objResult->fields['is_required']==1){
    		    	$class = "highlighted";
    		    	$objTemplate->setVariable(array(
    		    		'MODULE_REQUIRED'	=> $_CORELANG['TXT_REQUIRED'],
    		    		'MODULE_REMOVE'		=> "&nbsp;"
    		    	));
    		    } else {
    		    	$objTemplate->setVariable("MODULE_REQUIRED", $_CORELANG['TXT_OPTIONAL']);
    		    }

    		    $objTemplate->setVariable(array(
    		        'MODULE_ROWCLASS'   => $class,
    		        'MODULE_DESCRIPTON' => $_CORELANG[$objResult->fields['description_variable']],
    		        'MODULE_ID'         => $objResult->fields['id']
    		    ));
    		    $objTemplate->parse("moduleRow");
    		    $i++;
    		    $objResult->MoveNext();
    		}
    	}
    }




    function modModules()
    {
    	global $_CORELANG;
    	//$installedModules = "";
    	if($this->installModules()){
    		$installedModules = "";
    		foreach ($this->arrayInstalledModules AS $key => $elem){
    		    $installedModules .= $key.", ";
    		}
    	     $this->strOkMessage .= "".sprintf($_CORELANG['TXT_MODULES_INSTALLED_SUCCESFULL'],$installedModules." ");
    	}
    	if($this->removeModules()){
    		$removedModules = "";
    		foreach ($this->arrayRemovedModules AS $key => $elem){
    		   $removedModules .= $key.", ";
    		}
    		$this->strOkMessage .= " ".sprintf($_CORELANG['TXT_MODULES_REMOVED_SUCCESSFUL'],$removedModules." ");
    	}
    }




    function installModules()
    {
    	global $objDatabase,$_CORELANG;

    	$errorMsg = "";
    	$objDb2 = getDatabaseObject($errorMsg, true);

    	$alreadyexist = false;
    	$i = 1;

   	    if(isset($_POST['installModule']) && is_array($_POST['installModule'])) {
    		$currentTime = time();
    	    foreach ($_POST['installModule'] AS $key => $elem) {
    	    	$id = intval($key);
    	    	$objResult = $objDatabase->Execute("SELECT name FROM ".DBPREFIX."modules WHERE id = ".$id);
    	    	if ($objResult !== false) {
    	    		if (!$objResult->EOF) {
    	    			$module_name = $objResult->fields['name'];
    	    		}
    	    	} else {
    	    		$this->errorHandling();
	                return false;
    	    	}

	    		$query= "SELECT *
	    		         FROM ".DBPREFIX."module_repository
	    	             WHERE moduleid=".$id."
	    		         ORDER BY parid ASC";

	    		/* removed! Each language has the same repository!
	    		$query= "SELECT *
	    		         FROM ".DBPREFIX."module_repository
	    	             WHERE moduleid=$id
	    		         AND lang=$this->langId
	    		         ORDER BY parid ASC";
	    		*/

	    		$objResult = $objDatabase->Execute($query);
	    	    if ($objResult !== false) {
    	    	    while (!$objResult->EOF) {
    	    		    if (isset($paridarray[$objResult->fields['parid']])) {
					        $parcat= $paridarray[$objResult->fields['parid']];
					    } else {
						    if (!$alreadyexist)
						    {
							    $objResult2 = $objDb2->Execute("SELECT catid FROM ".DBPREFIX."content_navigation WHERE module=".$id." AND lang=".$this->langId );
							    if ($objResult2 !== false && !$objResult2->EOF) {
							        $objDb2->Execute("DELETE FROM ".DBPREFIX."content WHERE id='".$$objResult2->fields['catid']."'");
							    }
							    $objDb2->Execute("DELETE FROM ".DBPREFIX."content_navigation WHERE module=".$id." AND lang=".$this->langId );
						    }
						    $alreadyexist=true;
				            $parcat= 0;
					    }
	    		   	    $this->arrayInstalledModules[$module_name]=true;
	    	            $moduleid= $objResult->fields['moduleid'];
	    	            $content= addslashes($objResult->fields['content']);
	    	            $title= addslashes($objResult->fields['title']);
	    	            $cmd= $objResult->fields['cmd'];
	    	            $displaystatus= $objResult->fields['displaystatus'];
	    	            $expertmode= $objResult->fields['expertmode'];

	    	            // Set displayorder to a high value for the parent module page
	    	            $displayorder= ($i == 1) ? $this->defaultOrderValue : $objResult->fields['displayorder'];

	    	            $langId= $objResult->fields['lang'];
	    	            $parid= $objResult->fields['parid'];
	    	            $modulerepid= $objResult->fields['id'];
	    	            $username= $objResult->fields['username'];

	    	            $query= "INSERT INTO ".DBPREFIX."content_navigation SET
					    	    	        parcat='".$parcat."',
	    	        						catname='".$title."',
					    	    	        module='".$moduleid."',
	    	     						    cmd='".$cmd."',
					    	    	        displayorder='".$displayorder."',
					    	    	        username='".$username."',
					    	    	        changelog='".$currentTime."',
					    	    	        protected='0',
	    	        						displaystatus='".$displaystatus."',
					    	    	        lang=".$this->langId;

	                    if ($objDb2->Execute($query) !== false) {
	                	    $catid= $paridarray[$modulerepid]= $objDb2->Insert_ID();
	        	    	    $query= "INSERT INTO ".DBPREFIX."content SET
				    	        	       id=$catid,
				    	        	       content='$content',
				    	        	       title='$title',
				    	        	       metatitle='$title',
				    	        	       metadesc='$title',
				    	        	       metakeys='$title',
				    	        	       metarobots='index',
				    	        	       expertmode='$expertmode'";

	        	            if ($objDb2->Execute($query) === false) {
	                	        $this->errorHandling();
	                            return false;
	                        }
	        	            $parcat= $catid;
	                    } else {
	                	    $this->errorHandling();
	                        return false;
	                    }
	                    $i++;
	                    $objResult->MoveNext();
    	    	    }
    	        } else {
	    		    $this->errorHandling();
	                return false;
    	        }
    	        $i = 1;
    	    } // end foreach

	    	if (!$alreadyexist){
	    		$objResult = $objDatabase->Execute("SELECT name FROM ".DBPREFIX."modules WHERE id=".$id);
	    		if ($objResult !== false && !$objResult->EOF) {
	    			$name=$objResult->fields['name'];
	    		}
	    		$username= $_SESSION['auth']['username'];
	    		$query= "INSERT INTO ".DBPREFIX."content_navigation SET
					    	    	        parcat='0',
	    									catname='".$name."',
					    	    	        module='".$id."',
					    	    	        username='".$username."',
					    	    	        changelog='".$currentTime."',
					    	    	        lang=".$this->langId;
                if($objDb2->Execute($query) !== false) {
                	$catid= $objDb2->Insert_ID();
        	    	$query= "INSERT INTO ".DBPREFIX."content SET
			    	        	       id=".$catid.",
			    	        	       title='".$name."'";
        	        $objDb2->Execute($query);
	    	   		return true;
    	    	}else{
					$this->errorHandling();
					return false;
    	    	}
	 	   }
	       return true;
    	}
    	else {
    	    return false;
    	}

    }




    function removeModules()
    {
    	global $objDatabase;

    	$errorMsg = "";
    	$objDb2 = getDatabaseObject($errorMsg, true);

    	if(isset($_POST['removeModule']) && is_array($_POST['removeModule'])){
    	    foreach ($_POST['removeModule'] AS $key => $elem){
    	    	$id = intval($key);

    	    	$query = "SELECT catid,
    	    	                 name
    	    	            FROM ".DBPREFIX."content_navigation,
    	    	                 ".DBPREFIX."modules
    	    	           WHERE module = '".$id."'
    	    	             AND module = id
    	    	             AND lang=".$this->langId;
    	    	$objResult = $objDatabase->Execute($query);
    	    	if ($objResult !== false) {
    	            while (!$objResult->EOF) {
    	            	$this->arrayRemovedModules[$objResult->fields['name']]=true;
    	            	$catid = $objResult->fields['catid'];
    	            	$query = "DELETE FROM ".DBPREFIX."content_navigation WHERE catid = '".$catid."'";
    	            	if ($objDb2->Execute($query) === false) {
    	            		$this->errorHandling();
    	            		return false;
    	            	}
    	            	$query = "DELETE FROM ".DBPREFIX."content WHERE id = '".$catid."'";
    	            	if ($objDb2->Execute($query) === false) {
    	            		$this->errorHandling();
    	            		return false;
    	            	}
    	            	$objResult->MoveNext();
    	            }
    	    	}
    	    	else {
    	    		$this->errorHandling();
    	    		return false;
    	    	}

    	    }
    	    return true;
    	}
    	else
    	{
    	    return false;
    	}

    }





    function manageModules()
    {
    	global $objDatabase, $_CORELANG, $objTemplate;
    	$objTemplate->setVariable("MODULE_ACTION","manage");

    	if(isset($_POST['installModule']) AND is_array($_POST['installModule'])){
    	    foreach ($_POST['installModule'] AS $key => $elem){
    	    	$id = intval($key);
    	    	$addOnValue= intval($elem);
    	    	$query = "UPDATE ".DBPREFIX."modules SET is_required = ".$addOnValue." WHERE id = ".$id;
    	    	$objDatabase->Execute($query);
    	    }
    	}

    	if(isset($_POST['removeModule']) AND is_array($_POST['removeModule'])){
    		foreach ($_POST['removeModule'] AS $key => $elem){
    	    	$id = intval($key);
    	    	$addOnValue= intval($elem);
    	    	$query = "UPDATE ".DBPREFIX."modules SET is_required = ".$addOnValue." WHERE id = ".$id;
    	    	$objDatabase->Execute($query);
    	    }
    	}
    	$query = "SELECT id,name, description_variable,is_required FROM ".DBPREFIX."modules WHERE id<>0 GROUP BY id";
    	$objResult = $objDatabase->Execute($query);
    	if ($objResult !== false) {
    		while (!$objResult->EOF) {
               if($objResult->fields['is_required']==1){
    		    	$objTemplate->setVariable(array(
    		    		'MODULE_REMOVE'		=> "<input type='checkbox' name='removeModule[".$objResult->fields['id']."]' value='0' />",
    		    		'MODULE_INSTALL'	=> "&nbsp;",
    		    		'MODULE_STATUS'		=> "<img src='images/icons/led_green.gif' alt='' />"
    		    	));
                }else {
    		    	$objTemplate->setVariable(array(
    		    		'MODULE_INSTALL'	=> "<input type='checkbox' name='installModule[".$objResult->fields['id']."]' value='1' />",
    		    		'MODULE_REMOVE'		=> "&nbsp;",
    		    		'MODULE_STATUS'		=> "<img src='images/icons/led_red.gif' alt='' />"
    		    	));
    		    }
    		    if (($i % 2) == 0) {$class="row1";} else {$class="row2";}
    		    $objTemplate->setVariable(array(
    		        'MODULE_ROWCLASS' => $class,
    		        'MODULE_NAME' => $objResult->fields['name'],
    		        'MODULE_DESCRIPTON' => $_CORELANG[$objResult->fields['description_variable']],
    		        'MODULE_ID'         => $objResult->fields['id']
    		    ));
    		    $objTemplate->parse("moduleRow");
    		    $i++;
    		    $objResult->MoveNext();
    		}
    	} else {
    	    $this->errorHandling();
    	    return false;
    	}
    }


    function errorHandling(){
    	global $_CORELANG;
        $this->strErrMessage.= " ".$_CORELANG['TXT_DATABASE_QUERY_ERROR']." ";
    }
}
?>