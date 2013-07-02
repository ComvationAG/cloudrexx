<?php
/**
 * Frontend controller to easily create a frontent view
 * @author Michael Ritter <michael.ritter@comvation.com>
 */

namespace Cx\Core\Core\Model\Entity;

/**
 * Frontend controller to easily create a frontent view
 * @author Michael Ritter <michael.ritter@comvation.com>
 */
abstract class SystemComponentFrontendController extends Controller {
    
    /**
     * This is called by the default ComponentController and does all the repeating work
     * 
     * This creates a template of the page content and calls parsePage($template)
     * @param \Cx\Core\ContentManager\Model\Entity\Page $page Resolved page
     */
    public function getPage(\Cx\Core\ContentManager\Model\Entity\Page $page) {
        global $_ARRAYLANG;
        
        // init component template
        $componentTemplate = new \Cx\Core\Html\Sigma('.');
        $componentTemplate->setErrorHandling(PEAR_ERROR_DIE);
        $componentTemplate->setTemplate($page->getContent());
        
        // default css and js
        if (file_exists($this->cx->getClassLoader()->getFilePath($this->getDirectory() . '/View/Style/Frontend.css'))) {
            \JS::registerCSS(substr($this->getDirectory(false, true) . '/View/Style/Frontend.css', 1));
        }
        if (file_exists($this->cx->getClassLoader()->getFilePath($this->getDirectory() . '/View/Script/Frontend.js'))) {
            \JS::registerJS(substr($this->getDirectory(false, true) . '/View/Script/Frontend.js', 1));
        }
        
        // parse page
        
        $componentTemplate->setVariable($_ARRAYLANG);
        $this->parsePage($componentTemplate);
        $page->setContent($componentTemplate->get());
    }
    
    /**
     * Use this to parse your frontend page
     * 
     * You will get a template based on the content of the resolved page
     * You can access Cx class using $this->cx
     * To show messages, use \Message class
     * @param \Cx\Core\Html\Sigma $template Template containing content of resolved page
     */
    public abstract function parsePage(\Cx\Core\Html\Sigma $template);
}
