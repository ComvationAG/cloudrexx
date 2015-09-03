<?php

/**
 * Media Directory Inputfield Image Class
 *
 * @copyright   CONTREXX CMS - COMVATION AG
 * @author      Comvation Development Team <info@comvation.com>
 * @package     contrexx
 * @subpackage  module_mediadir
 * @todo        Edit PHP DocBlocks!
 */
namespace Cx\Modules\MediaDir\Model\Entity;
/**
 * Media Directory Inputfield Image Class
 *
 * @copyright   CONTREXX CMS - COMVATION AG
 * @author      COMVATION Development Team <info@comvation.com>
 * @package     contrexx
 * @subpackage  module_mediadir
 */
class MediaDirectoryInputfieldImage extends \Cx\Modules\MediaDir\Controller\MediaDirectoryLibrary implements Inputfield
{
    public $arrPlaceholders = array(
        'TXT_MEDIADIR_INPUTFIELD_NAME',
        'MEDIADIR_INPUTFIELD_VALUE',
        'MEDIADIR_INPUTFIELD_VALUE_SRC',
        'MEDIADIR_INPUTFIELD_VALUE_SRC_THUMB',
        'MEDIADIR_INPUTFIELD_VALUE_POPUP',
        'MEDIADIR_INPUTFIELD_VALUE_IMAGE',
        'MEDIADIR_INPUTFIELD_VALUE_THUMB',
        'MEDIADIR_INPUTFIELD_VALUE_FILENAME'
    );

    private $imagePath;
    private $imageWebPath;

    /**
     * Constructor
     */
    function __construct($name)
    {
        $this->imagePath = \Cx\Core\Core\Controller\Cx::instanciate()->getWebsiteImagesMediaDirPath() . '/';
        $this->imageWebPath = \Cx\Core\Core\Controller\Cx::instanciate()->getWebsiteImagesMediaDirWebPath() . '/';
        parent::__construct('.', $name);
        parent::getFrontendLanguages();
        parent::getSettings();
    }



function getInputfield($intView, $arrInputfield, $intEntryId=null)
    {
        global $objDatabase, $_ARRAYLANG, $objInit, $_LANGID;

        switch ($intView) {
            default:
            case 1:
                //modify (add/edit) View
                $intId = intval($arrInputfield['id']);
                $arrValue = array();
                if(!empty($intEntryId)) {
                    $objInputfieldValue = $objDatabase->Execute("
                        SELECT 
                            `value`,
                            `lang_id`                            
                          FROM ".DBPREFIX."module_mediadir_rel_entry_inputfields
                         WHERE field_id=$intId
                           AND entry_id=$intEntryId");
                    if ($objInputfieldValue) {
                        while (!$objInputfieldValue->EOF) {
                            $arrValue[intval($objInputfieldValue->fields['lang_id'])] = contrexx_raw2xhtml($objInputfieldValue->fields['value']);
                            $objInputfieldValue->MoveNext();
                        }
                        $arrValue[0] = isset($arrValue[$_LANGID]) ? $arrValue[$_LANGID] : null;
                    }
                } else {
                    $arrValue = null;
                }
                
                $countFrontendLang = count($this->arrFrontendLanguages);
                
                $minimize  = '';
                if ($objInit->mode == 'backend' || $this->arrSettings['settingsFrontendUseMultilang']) {
                    $minimize  = "<a href=\"javascript:ExpandMinimize('$intId');\">{$_ARRAYLANG['TXT_MEDIADIR_MORE']}&nbsp;&raquo;</a>";
                }
                $strDefaultValue = isset($arrValue[0]) ? $arrValue[0] : '';                    
                $strDefaultInput = $this->getInput($intId, $strDefaultValue, 0);
                $strInputfield   = <<<INPUT
                        <div id="{$this->moduleNameLC}Inputfield_{$intId}_Minimized" class="{$this->moduleNameLC}GroupMultilang" style="display: block; float:left;">
                            $strDefaultInput
                            $minimize
                        </div>
INPUT;
                if ($objInit->mode == 'backend' || $this->arrSettings['settingsFrontendUseMultilang']) {
                    
                    $strInputfield .= '<div id="'.$this->moduleNameLC.'Inputfield_'.$intId.'_Expanded" class="'. $this->moduleNameLC.'GroupMultilang" style="display: none; float:left;">';

                    foreach ($this->arrFrontendLanguages as $key => $arrLang) {
                        $intLangId = $arrLang['id'];
                        $minimize  = '';
                        if(($key+1) == $countFrontendLang) {
                            $minimize = "&nbsp;<a href=\"javascript:ExpandMinimize('".$intId."');\">&laquo;&nbsp;".$_ARRAYLANG['TXT_MEDIADIR_MINIMIZE']."</a>";
                        }

                        $value    = isset($arrValue[$intLangId]) ? $arrValue[$intLangId] : '';
                        $strInput = $this->getInput($intId, $value, $intLangId);
                        $strInputfield .= <<<INPUT
                            <div>
                                $strInput
                                $minimize
                            </div>
INPUT;
                    }
                    $strInputfield .= '</div>';
                }
                return $strInputfield;

                break;
            case 2:
                // search view
                break;
        }
        return null;
    }

    /**
     * Get input field based on language id and value
     *      
     * @param integer $id     Input field id
     * @param string  $value  input field value
     * @param integer $langId Language id
     * 
     * @return string Return input field based on language id and value
     */
    private function getInput($id = 0, $value = '', $langId = 0)
    {
        global $_ARRAYLANG;
        
        $cx = \Cx\Core\Core\Controller\Cx::instanciate();
        
        $strImagePreview = null;
        if(!empty($value) && file_exists($cx->getWebsitePath().$value.".thumb")) {
            $strImagePreview = '<img id="'. $this->moduleNameLC . 'Inputfield_' . $id .'_'. $langId.'_preview" src="'.$value.'.thumb" alt="" style="border: 1px solid rgb(10, 80, 161); margin: 0px 0px 3px;"  width="'.intval($this->arrSettings['settingsThumbSize']).'" />&nbsp;
                                <input 
                                    data-id="'.$id.'"
                                    type="checkbox" 
                                    class="'. (!$langId ? 'mediadirInputfieldDefaultDeleteFile' : '') .'"
                                    id="mediadirInputfield_delete_'.$id.'_'.$langId.'"    
                                    value="1" 
                                    name="deleteMedia['.$id.']['.$langId.']"
                                />'.$_ARRAYLANG['TXT_MEDIADIR_DELETE'].'<br />';
        }
        
        $flagPath   = $cx->getCodeBaseOffsetPath() . $cx->getCoreFolderName().'/Country/View/Media/Flag';
        $inputStyle =   !empty($langId)
                      ? 'background: #ffffff url(\''. $flagPath .'/flag_'. \FWLanguage::getLanguageCodeById($langId) .'.gif\') no-repeat 3px 3px;'
                      : '';
        $inputDefaultClass = empty($langId) ? $this->moduleNameLC . 'InputfieldDefault' : $this->moduleNameLC . 'LangInputfield';
        
        $mode = $cx->getMode();
        if ($mode == \Cx\Core\Core\Controller\Cx::MODE_BACKEND) {
            $strInputfield = <<<INPUT
            $strImagePreview
            <input type="text" name="{$this->moduleNameLC}Inputfield[$id][$langId]"
                value="$value" 
                data-id="$id"
                data-is-image="true"
                class="$inputDefaultClass"
                id="{$this->moduleNameLC}Inputfield_{$id}_$langId"
                style="$inputStyle" 
                autocomplete="off"
                onfocus="this.select();" />
            &nbsp;
            <input type="button" 
                onClick="getMediaBrowser(\$J(this));"
                data-is-image="true"
                data-input-id="{$this->moduleNameLC}Inputfield_{$id}_$langId"
                data-views="filebrowser"
                data-startmediatype="{$this->moduleNameLC}"
                value="{$_ARRAYLANG['TXT_BROWSE']}"
            />
INPUT;
        } else {
            if (empty($value) || $value == "new_image") {
                $strValueHidden = "new_image";
                $value = "";
            } else {
                $strValueHidden = $value;
            }
            $strInputfield = <<<INPUT
            $strImagePreview
            <input type="file" name="imageUpload_{$id}[$langId]"                
                id="{$this->moduleNameLC}Inputfield_{$id}_$langId"
                class="{$this->moduleNameLC}InputfieldImage"                        
                value="{$value}"
                onfocus="this.select();" />
            <input id="{$this->moduleNameLC}Inputfield_{$id}_{$langId}_hidden"
                name="{$this->moduleNameLC}Inputfield[$id][$langId]"
                value="{$strValueHidden}" type="hidden" />
            <span class="{$this->moduleNameLC}InputfieldFilesize">
                {$_ARRAYLANG['TXT_MEDIADIR_MAX_FILESIZE']}
                {$this->arrSettings['settingsImageFilesize']}
                KB
            </span>
INPUT;
        }
        return $strInputfield;
    }

    function saveInputfield($intInputfieldId, $strValue, $langId = 0)
    {
        global $objInit;
        static $strNewDefault = null;
        
        $deleteMedia = !empty($_POST["deleteMedia"]) && !empty($_POST["deleteMedia"][$intInputfieldId]);
        if($objInit->mode == 'backend') {
            if (   !$deleteMedia
                || $_POST["deleteMedia"][$intInputfieldId][$langId] != 1) {
                $this->checkThumbnail($strValue);
                $strValue = contrexx_input2raw($strValue);
            } else {
                $strValue = null;
            }
        } else {
            $inputFiles  = !empty($_FILES['imageUpload_'.$intInputfieldId]) ? $_FILES['imageUpload_'.$intInputfieldId] : array();
            
            if ($deleteMedia && $_POST["deleteMedia"][$intInputfieldId][$langId] == 1) {
                $strValue = null;
                $this->deleteImage($strValue);
            } elseif (!empty($inputFiles) && !empty($inputFiles['name'][$langId])) {
                $intFilsize = intval($this->arrSettings['settingsImageFilesize']*1024);
                if($inputFiles['size'][$langId] < $intFilsize) {
                    //delete image & thumb
                    $this->deleteImage($strValue);
                    //upload image
                    $strValue = $this->uploadMedia($intInputfieldId, $langId);                    
                } else {
                    $strValue = null;
                }
            } else {
                if (!$strNewDefault && !empty($langId)) {
                    // Needs $arrData instead of $_POST
                    $strMaster =  (isset($_POST[$this->moduleNameLC.'Inputfield'][$intInputfieldId][0])
                                ? $_POST[$this->moduleNameLC.'Inputfield'][$intInputfieldId][0]
                                : '');
                    $strNewDefault = $this->saveInputfield($intInputfieldId, $strMaster);
                }
                $strValue = $strNewDefault;
            }
        }

        return $strValue;
    }


    function checkThumbnail($strPathImage)
    {
        $this->createThumbnail($strPathImage);
    }

    function deleteImage($strPathImage)
    {
        if(!empty($strPathImage)) {
            $objFile = new \File();
            $arrImageInfo = pathinfo($strPathImage);
            $imageName    = $arrImageInfo['basename'];

            //delete thumb
            if (file_exists(\Env::get('cx')->getWebsitePath().$strPathImage.".thumb")) {
                $objFile->delFile($this->imagePath, $this->imageWebPath, 'images/'.$imageName.".thumb");
            }

            //delete image
            if (file_exists(\Env::get('cx')->getWebsitePath().$strPathImage)) {
                $objFile->delFile($this->imagePath, $this->imageWebPath, 'images/'.$imageName);
            }
        }
    }


    function uploadMedia($intInputfieldId, $langId)
    {
        if (empty($_FILES)) {
            return false;
        }
        
        $tmpImage   = $_FILES['imageUpload_'.$intInputfieldId]['tmp_name'][$langId];
        $imageName  = $_FILES['imageUpload_'.$intInputfieldId]['name'][$langId];
//        $imageType  = $_FILES['imageUpload_'.$intInputfieldId]['type'];
//        $imageSize  = $_FILES['imageUpload_'.$intInputfieldId]['size'];
        if ($imageName == '') {
            return false;
        }
        // get extension
        $arrImageInfo   = pathinfo($imageName);
        $imageExtension = !empty($arrImageInfo['extension']) ? '.'.$arrImageInfo['extension'] : '';
        $imageBasename  = $arrImageInfo['filename'];
        $randomSum      = rand(10, 99);
        // encode filename
        if ($this->arrSettings['settingsEncryptFilenames'] == 1) {
            $imageName = md5($randomSum.$imageBasename).$imageExtension;
        }
        // check filename
        if (file_exists($this->imagePath.'images/'.$imageName)) {
            $imageName = $imageBasename.'_'.time().$imageExtension;
        }
        // upload file
        if (!move_uploaded_file($tmpImage, $this->imagePath.'images/'.$imageName)) {
            return false;
        }
        $imageDimension = getimagesize($this->imagePath.'images/'.$imageName);
        $intNewWidth = $imageDimension[0];
        $intNewHeight = $imageDimension[1];
        $imageFormat = ($imageDimension[0] > $imageDimension[1]) ? 1 : 0;
        $setNewSize = 0;
        if ($imageDimension[0] > 640 && $imageFormat == 1) {
            $doubleFactorDimension = 640 / $imageDimension[0];
            $intNewWidth = 640;
            $intNewHeight = round($doubleFactorDimension * $imageDimension[1], 0);
            $setNewSize = 1;
        } elseif($imageDimension[1] > 480) {
            $doubleFactorDimension = 480 / $imageDimension[1];
            $intNewHeight = 480;
            $intNewWidth = round($doubleFactorDimension * $imageDimension[0], 0);
            $setNewSize = 1;
        }
        if ($setNewSize == 1) {
            $objImage = new \ImageManager();
            $objImage->loadImage($this->imagePath.'images/'.$imageName);
            $objImage->resizeImage($intNewWidth, $intNewHeight, 100);
            $objImage->saveNewImage($this->imagePath.'images/'.$imageName, true);
        }
        $objFile = new \File();
        $objFile->setChmod($this->imagePath, $this->imageWebPath, 'images/'.$imageName);
        // create thumbnail
        $this->checkThumbnail($this->imageWebPath.'images/'.$imageName);
        return $this->imageWebPath.'images/'.$imageName;
    }


    function createThumbnail($strPathImage)
    {
        global $objDatabase;

        $arrImageInfo = getimagesize(\Env::get('cx')->getWebsitePath().$strPathImage);

        if (   $arrImageInfo['mime'] == "image/gif"
            || $arrImageInfo['mime'] == "image/jpeg"
            || $arrImageInfo['mime'] == "image/jpg"
            || $arrImageInfo['mime'] == "image/png") {
            $objImage = new \ImageManager();

            $arrImageInfo = array_merge($arrImageInfo, pathinfo($strPathImage));

            $thumbWidth = intval($this->arrSettings['settingsThumbSize']);
            $thumbHeight = intval($thumbWidth / $arrImageInfo[0] * $arrImageInfo[1]);

            $objImage->loadImage(\Env::get('cx')->getWebsitePath().$strPathImage);
            $objImage->resizeImage($thumbWidth, $thumbHeight, 100);
            $objImage->saveNewImage(\Env::get('cx')->getWebsitePath().$strPathImage . '.thumb', true);
        }
    }


    function deleteContent($intEntryId, $intIputfieldId)
    {
        global $objDatabase;

        //get image path
        /*$objDatabase->Execute("
            SELECT value
              FROM ".DBPREFIX."module_".$this->moduleTablePrefix."_rel_entry_inputfields
             WHERE `entry_id`='".intval($intEntryId)."'
               AND `field_id`='".intval($intIputfieldId)."'");
        $strImagePath = $objResult->fields['value'];*/

        //delete relation
        $objResult = $objDatabase->Execute("
            DELETE FROM ".DBPREFIX."module_".$this->moduleTablePrefix."_rel_entry_inputfields
             WHERE `entry_id`='".intval($intEntryId)."'
               AND  `field_id`='".intval($intIputfieldId)."'");
        if ($objResult) {
            //delete image
            //$this->deleteImage($strImagePath);
            return true;
        }
        return false;
    }



    function getContent($intEntryId, $arrInputfield, $arrTranslationStatus)
    {
        global $objDatabase, $_LANGID;

        $intId = intval($arrInputfield['id']);
        $intEntryDefaultLang = $objDatabase->getOne("SELECT `lang_id` FROM ".DBPREFIX."module_".$this->moduleTablePrefix."_entries WHERE id=".intval($intEntryId)." LIMIT 1");        

        if($this->arrSettings['settingsTranslationStatus'] == 1) {
            $intLangId = in_array($_LANGID, $arrTranslationStatus) ? $_LANGID : contrexx_input2int($intEntryDefaultLang);
        } else {
            $intLangId = $_LANGID;
        }
        $objResult = $objDatabase->Execute("
            SELECT `value`
              FROM ".DBPREFIX."module_mediadir_rel_entry_inputfields
             WHERE field_id=$intId
               AND entry_id=$intEntryId
               AND lang_id=$intLangId
             LIMIT 1 ");
        
        if(empty($objResult->fields['value'])) {
            $objResult = $objDatabase->Execute("
                SELECT `value`
                  FROM ".DBPREFIX."module_mediadir_rel_entry_inputfields
                 WHERE field_id=$intId
                   AND entry_id=$intEntryId
                   AND lang_id=$intEntryDefaultLang
                 LIMIT 1 ");
        }
        $strValue = strip_tags(htmlspecialchars($objResult->fields['value'], ENT_QUOTES, CONTREXX_CHARSET));
        if (empty($strValue) || $strValue == 'new_image') {
            return null;
        }
        $arrImageInfo   = getimagesize(\Env::get('cx')->getWebsitePath().$strValue);
        $imageWidth     = $arrImageInfo[0]+20;
        $imageHeight    = $arrImageInfo[1]+20;
        $arrImageInfo   = pathinfo($strValue);
        $strImageName    = $arrImageInfo['basename'];

        return array(
            'TXT_MEDIADIR_INPUTFIELD_NAME' => htmlspecialchars(
                $arrInputfield['name'][0], ENT_QUOTES, CONTREXX_CHARSET),
            'MEDIADIR_INPUTFIELD_VALUE' =>
                '<a rel="shadowbox[1];options={slideshowDelay:5}" href="'.$strValue.'">'.
                '<img src="'.$strValue.'.thumb" alt="" border="0" title="" '.
                'width="'.intval($this->arrSettings['settingsThumbSize']).'" /></a>',
            'MEDIADIR_INPUTFIELD_VALUE_SRC' => $strValue,
            'MEDIADIR_INPUTFIELD_VALUE_FILENAME' => $strImageName,
            'MEDIADIR_INPUTFIELD_VALUE_SRC_THUMB' => $strValue.".thumb",
            'MEDIADIR_INPUTFIELD_VALUE_POPUP' =>
                '<a href="'.$strValue.'"'.
                ' onclick="window.open(this.href,\'\',\'resizable=no,location=no,menubar=no,scrollbars=no,status=no,toolbar=no,fullscreen=no,dependent=no,width='.$imageWidth.',height='.$imageHeight.',status\');return false">'.
                '<img src="'.$strValue.'.thumb" title="'.$arrInputfield['name'][0].'"'.
                ' width="'.intval($this->arrSettings['settingsThumbSize']).'"'.
                ' alt="'.$arrInputfield['name'][0].'" border="0" /></a>',
            'MEDIADIR_INPUTFIELD_VALUE_IMAGE' =>
                '<img src="'.$strValue.'" title="'.$arrInputfield['name'][0].'"'.
                ' alt="'.$arrInputfield['name'][0].'" />',
            'MEDIADIR_INPUTFIELD_VALUE_THUMB' =>
                '<img src="'.$strValue.'.thumb"'.
                ' width="'.intval($this->arrSettings['settingsThumbSize']).'"'.
                ' title="'.$arrInputfield['name'][0].'"'.
                ' alt="'.$arrInputfield['name'][0].'" />',
        );
    }


    function getJavascriptCheck()
    {
    	global $objInit;
    	
        $fieldName = $this->moduleNameLC."Inputfield_";
        
        if($objInit->mode == 'backend') {
            $hiddenField = "value_hidden = false";
        } else {
            $hiddenField = "value_hidden = document.getElementById('".$fieldName."' + field + '_0_hidden').value;";
        }
        $strJavascriptCheck = <<<EOF

            case 'image':
                value = document.getElementById('$fieldName' + field + '_0').value;
                $hiddenField
                filetype = value.substring(value.length-4);
                filetype = filetype.toLowerCase();

                if (value == "" && value_hidden == "" && isRequiredGlobal(inputFields[field][1], value)) {
                    isOk = false;
                    document.getElementById('$fieldName' + field + '_0').style.border = "#ff0000 1px solid";
                } else if (value != "" && filetype != ".jpg" && filetype != ".gif" && filetype != ".png" ) {
                    isOk = false;
                    document.getElementById('$fieldName' + field + '_0').style.border = "#ff0000 1px solid";
                } else {
                    document.getElementById('$fieldName' + field + '_0').style.borderColor = '';
                }
                break;

EOF;
        return $strJavascriptCheck;
    }


    function getFormOnSubmit($intInputfieldId)
    {
        return null;
    }
}
