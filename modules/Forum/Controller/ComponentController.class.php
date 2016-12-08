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
 * Main controller for Forum
 *
 * @copyright   Cloudrexx AG
 * @author      Project Team SS4U <info@cloudrexx.com>
 * @package     cloudrexx
 * @subpackage  module_forum
 */

namespace Cx\Modules\Forum\Controller;

/**
 * Main controller for Forum
 *
 * @copyright   Cloudrexx AG
 * @author      Project Team SS4U <info@cloudrexx.com>
 * @package     cloudrexx
 * @subpackage  module_forum
 */
class ComponentController extends \Cx\Core\Core\Model\Entity\SystemComponentController {
    public function getControllerClasses() {
        // Return an empty array here to let the component handler know that there
        // does not exist a backend, nor a frontend controller of this component.
        return array();
    }

     /**
     * Returns a list of JsonAdapter class names
     *
     * @return array List of ComponentController classes
     */
    public function getControllersAccessableByJson()
    {
        return array('JsonForum');
    }
     /**
     * Load your component.
     *
     * @param \Cx\Core\ContentManager\Model\Entity\Page $page       The resolved page
     */
    public function load(\Cx\Core\ContentManager\Model\Entity\Page $page) {
        global $_CORELANG, $objTemplate, $subMenuTitle;
        switch ($this->cx->getMode()) {
            case \Cx\Core\Core\Controller\Cx::MODE_FRONTEND:
                $objForum = new Forum(\Env::get('cx')->getPage()->getContent());
                \Env::get('cx')->getPage()->setContent($objForum->getPage());
//                $moduleStyleFile = $this->getDirectory() . '/css/frontend_style.css';
                break;

            case \Cx\Core\Core\Controller\Cx::MODE_BACKEND:
                $this->cx->getTemplate()->addBlockfile('CONTENT_OUTPUT', 'content_master', 'LegacyContentMaster.html');
                $objTemplate = $this->cx->getTemplate();

                \Permission::checkAccess(106, 'static');
                $subMenuTitle = $_CORELANG['TXT_FORUM'];
                $objForum = new ForumAdmin();
                $objForum->getPage();
                break;
        }
    }

    /**
     * Do something before content is loaded from DB
     *
     * @param \Cx\Core\ContentManager\Model\Entity\Page $page       The resolved page
     */
    public function preContentLoad(\Cx\Core\ContentManager\Model\Entity\Page $page) {
        global $_CONFIG, $_LANGID, $forumHomeContentInPageContent, $forumHomeContentInPageTemplate,
               $forumHomeContentInThemesPage, $page_template, $themesPages,
               $_ARRAYLANG, $objInit, $objForum, $objForumHome,
               $forumHomeTagCloudInContent, $forumHomeTagCloudInTemplate, $forumHomeTagCloudInTheme,
               $forumHomeTagCloudInSidebar, $strTagCloudSource, $section;
        switch ($this->cx->getMode()) {
            case \Cx\Core\Core\Controller\Cx::MODE_FRONTEND:
                // get + replace forum latest entries content
                if ($_CONFIG['forumHomeContent'] == '1') {
                    $forumHomeContentInPageContent = false;
                    $forumHomeContentInPageTemplate = false;
                    $forumHomeContentInThemesPage = false;
                    if (strpos(\Env::get('cx')->getPage()->getContent(), '{FORUM_FILE}') !== false) {
                        $forumHomeContentInPageContent = true;
                    }
                    if (strpos($page_template, '{FORUM_FILE}') !== false) {
                        $forumHomeContentInPageTemplate = true;
                    }
                    if (strpos($themesPages['index'], '{FORUM_FILE}') !== false) {
                        $forumHomeContentInThemesPage = true;
                    }
                    $cache = \Cx\Core\Core\Controller\Cx::instanciate()
                            ->getComponent('Cache');
                    if ($forumHomeContentInPageContent
                            || $forumHomeContentInPageTemplate
                            || $forumHomeContentInThemesPage
                    ) {
                        $forumContent = $cache->getEsiContent(
                            'Forum',
                            'getForumContent',
                            array(
                                'theme'       => \Env::get('init')->getCurrentThemeId(),
                                'lang'        => $_LANGID,
                            )
                        );
                    }
                    if ($forumHomeContentInPageContent) {
                        \Env::get('cx')->getPage()->setContent(
                            str_replace(
                                '{FORUM_FILE}',
                                $forumContent,
                                \Env::get('cx')->getPage()->getContent()
                            )
                        );
                    }
                    if ($forumHomeContentInPageTemplate) {
                        $page_template = str_replace(
                            '{FORUM_FILE}',
                            $forumContent,
                            $page_template
                        );
                    }
                    if ($forumHomeContentInThemesPage) {
                        $themesPages['index'] = str_replace(
                            '{FORUM_FILE}',
                            $forumContent,
                            $themesPages['index']
                        );
                    }
                }

                // get + replace forum tagcloud
                if (!empty($_CONFIG['forumTagContent'])) {
                    $objForumHome = new ForumHomeContent('');
                    $page = $this->cx->getPage();
                    //Forum-TagCloud
                    $forumHomeTagCloudInContent = $objForumHome->searchKeywordInContent('FORUM_TAG_CLOUD', \Env::get('cx')->getPage()->getContent());
                    $forumHomeTagCloudInTemplate = $objForumHome->searchKeywordInContent('FORUM_TAG_CLOUD', $page_template);
                    $forumHomeTagCloudInTheme = $objForumHome->searchKeywordInContent('FORUM_TAG_CLOUD', $themesPages['index']);
                    $forumHomeTagCloudInSidebar = $objForumHome->searchKeywordInContent('FORUM_TAG_CLOUD', $themesPages['sidebar']);
                    if (   $forumHomeTagCloudInContent
                           || $forumHomeTagCloudInTemplate
                           || $forumHomeTagCloudInTheme
                           || $forumHomeTagCloudInSidebar
                       ) {
                            $strTagCloudSource = $objForumHome->getHomeTagCloud();
                            $page->setContent(
                                $objForumHome->fillVariableIfActivated(
                                    'FORUM_TAG_CLOUD',
                                    $strTagCloudSource,
                                    \Env::get('cx')->getPage()->getContent(),
                                    $forumHomeTagCloudInContent,
                                    '',
                                    $page
                                )
                            );
                            $page_template = $objForumHome->fillVariableIfActivated(
                                'FORUM_TAG_CLOUD',
                                $strTagCloudSource,
                                $page_template,
                                $forumHomeTagCloudInTemplate,
                                $section == 'Home'
                                    ? 'home.html' : 'content.html'
                            );
                            $themesPages['index'] = $objForumHome->fillVariableIfActivated(
                                'FORUM_TAG_CLOUD',
                                $strTagCloudSource,
                                $themesPages['index'],
                                $forumHomeTagCloudInTheme,
                                'index.html'
                            );
                            $themesPages['sidebar'] = $objForumHome->fillVariableIfActivated(
                                'FORUM_TAG_CLOUD',
                                $strTagCloudSource,
                                $themesPages['sidebar'],
                                $forumHomeTagCloudInSidebar,
                                'sidebar.html'
                            );
                    }
                }
                break;
        }
    }

    /**
     * Register the Event listeners
     *
     * @param \Cx\Core\ContentManager\Model\Entity\Page $page       The resolved page
     */
    public function preContentParse(\Cx\Core\ContentManager\Model\Entity\Page $page) {
        $eventListener = new \Cx\Modules\Forum\Model\Event\ForumEventListener($this->cx);
        $this->cx->getEvents()->addEventListener('SearchFindContent', $eventListener);
        $this->cx->getEvents()->addEventListener('clearEsiCache', $eventListener);
   }

}
