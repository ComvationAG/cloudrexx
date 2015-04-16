<?php

/**
 * Class Uploader
 *
 * @copyright   CONTREXX CMS - Comvation AG Thun
 * @author      Robin Glauser <robin.glauser@comvation.com>
 * @package     contrexx
 * @subpackage  coremodule_uploader
 */

namespace Cx\Core_Modules\Uploader\Model\Entity;


use Cx\Core\Core\Controller\Cx;
use Cx\Core\Html\Sigma;
use Cx\Lib\FileSystem\File;
use Cx\Model\Base\EntityBase;

/**
 * Class Uploader
 *
 * @copyright   CONTREXX CMS - Comvation AG Thun
 * @author      Robin Glauser <robin.glauser@comvation.com>
 * @package     contrexx
 * @subpackage  coremodule_uploader
 */
class Uploader extends EntityBase
{

    /**
     * The uploader id
     *
     * @var int
     */
    protected $id;

    /**
     * @var Array
     */
    protected $options = array();

    /**
     * @var Cx
     */
    protected $cx;

    function __construct()
    {
        $this->cx = Cx::instanciate();
        $this->getComponentController()->addUploader($this);
        if (!isset($_SESSION['uploader'])) {
            $_SESSION['uploader'] = array();
        }
        if (!isset($_SESSION['uploader']['handlers'])) {
            $_SESSION['uploader']['handlers'] = array();
        }


        $lastKey = count($_SESSION['uploader']['handlers']);
        $i       = $lastKey++;

        $_SESSION['uploader']['handlers'][$i] = array('active' => true);

        $this->id = $i;

        $this->options = array(
            'data-pl-upload',
            'data-uploader-id' => $this->id,
            'class' => "uploader-button button"
        );
    }

    /**
     * Saves the callback in the session.
     *
     * @param $callback
     *
     * @return $this
     */
    function setFinishedCallback($callback)
    {
        if (!isset($_SESSION['uploader']['handlers'])) {
            $_SESSION['uploader']['handlers'] = array();
        }
        if (!isset($_SESSION['uploader']['handlers'][$this->id])) {
            $_SESSION['uploader']['handlers'][$this->id] = array();
        }
        $_SESSION['uploader']['handlers'][$this->id]['callback'] = $callback;
        return $this;
    }

    /**
     * @param $options
     */
    function setOptions($options)
    {
        $this->options = array_merge($this->options, $options);
        
        $uploadLimit   = isset($this->options['data-upload-limit']) ? $this->options['data-upload-limit'] : '';
        if (!empty($uploadLimit)) {
            if (!isset($_SESSION['uploader']['handlers'])) {
                $_SESSION['uploader']['handlers'] = array();
            }
            if (!isset($_SESSION['uploader']['handlers'][$this->id])) {
                $_SESSION['uploader']['handlers'][$this->id] = array();
            }
            if (!isset($_SESSION['uploader']['handlers'][$this->id]['config'])) {
                $_SESSION['uploader']['handlers'][$this->id]['config'] = array();
            }
            
            $_SESSION['uploader']['handlers'][$this->id]['config']['upload-limit'] = $uploadLimit;
        }
    }

    /**
     * @param $option
     *
     * @return string
     */
    function getOption($option)
    {
        if (isset($this->options[$option])) {
            return $this->options[$option];
        }
        return null;
    }

    /**
     * Get all options as a string.
     * @return string
     */
    function getOptionsString()
    {
        $optionsString = "";
        foreach ($this->options as $key => $value) {
            if (is_int($key)) {
                $optionsString .= $value . ' ';
            } else {
                if (!in_array($key, array('class', 'id', 'style','title','value'))){
                    if ( strpos($key, 'data') !== 0){
                        $key = 'data-'.$key;
                    }
                }
                $optionsString .= $key . '="' . $value . '" ';
            }
        }
        return $optionsString;
    }

    /**
     * @param string $buttonName
     *
     * @return string
     */
    function getXHtml($buttonName = "Upload")
    {
        $path = $this->cx->getCodeBaseCoreModulePath() . '/Uploader/View/Template/Backend/Uploader.html';
        $objFile = new File($path);
        return '<button ' . $this->getOptionsString() . ' >' . $buttonName . '</button>' . str_replace(
            '{UPLOADER_ID}', $this->id, $objFile->getData()
        );
    }

    /**
     * Set a javascript callback on a global function.
     *
     * @param String $string
     *
     * @return $this
     */
    public function setCallback($string)
    {
        $this->setOptions(array('data-on-file-uploaded' => $string));
        return $this;
    }

    /**
     * Set a file upload limit.
     * @param $limit
     *
     * @return self
     */
    public function setUploadLimit($limit){
        $this->setOptions(array('data-upload-limit' => $limit));
        return $this;
    }

    /**
     * Add a class to the button
     *
     * @param $class
     *
     * @return self
     */
    public function addClass($class){
        $classString = $this->getOption('class');
        $classes = explode(' ',$classString);
        if (!in_array($class, $classes)){
            $classes[] = $class;
        }
        $this->setOptions(array('class' => implode(' ', $classes)));
        return $this;
    }

    /**
     * Add additional data for the uploader
     *
     * @param $data
     *
     * @return $this
     */
    public function setData($data)
    {
        if (!isset($_SESSION['uploader']['handlers'])) {
            $_SESSION['uploader']['handlers'] = array();
        }
        if (!isset($_SESSION['uploader']['handlers'][$this->id])) {
            $_SESSION['uploader']['handlers'][$this->id] = array();
        }
        $_SESSION['uploader']['handlers'][$this->id]['data'] = $data;
        return $this;
    }

} 