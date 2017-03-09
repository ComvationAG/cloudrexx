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
 * Class EsiWidgetController
 *
 * @copyright   CLOUDREXX CMS - Cloudrexx AG Thun
 * @author      Project Team SS4U <info@comvation.com>
 * @package     cloudrexx
 * @subpackage  module_mediadir
 * @version     1.0.0
 */

namespace Cx\Modules\MediaDir\Controller;

/**
 * JsonAdapter Controller to handle EsiWidgets
 * Usage:
 * - Create a subclass that implements parseWidget()
 * - Register it as a Controller in your ComponentController
 *
 * @copyright   CLOUDREXX CMS - Cloudrexx AG Thun
 * @author      Project Team SS4U <info@comvation.com>
 * @package     cloudrexx
 * @subpackage  module_mediadir
 * @version     1.0.0
 */

class EsiWidgetController extends \Cx\Core_Modules\Widget\Controller\EsiWidgetController {
    /**
     * currentThemeId
     *
     * @var integer
     */
    protected $currentThemeId;

    /**
     * Parses a widget
     * @param string $name Widget name
     * @param \Cx\Core\Html\Sigma Widget template
     * @param string $locale RFC 3066 locale identifier
     */
    public function parseWidget($name, $template, $locale)
    {
        global $_ARRAYLANG, $_LANGID;

        // Show Level/Category Navbar
        $_LANGID = \FWLanguage::getLangIdByIso639_1($locale);
        $mediaDirPlaceholders = new MediaDirectoryPlaceholders('MediaDir');
        if ($name == 'MEDIADIR_NAVBAR') {
            $template->setVariable(
                $name,
                $mediaDirPlaceholders->getNavigationPlacholder()
            );
            return;
        }

        // Show Latest Entries
        if ($name == 'MEDIADIR_LATEST') {
            $template->setVariable(
                $name,
                $mediaDirPlaceholders->getLatestPlacholder()
            );
            return;
        }

        //Show Latest MediaDir entries
        $_ARRAYLANG = array_merge(
            $_ARRAYLANG,
            \Env::get('init')->loadLanguageData('MediaDir')
        );
        $mediadir   = new MediaDirectory('', 'MediaDir');
        $matches    = null;
        if (
            preg_match(
                '/^mediadirLatest_row_(\d{1,2})_(\d{1,2})/',
                $name,
                $matches
            )
        ) {
            $template->setVariable(
                'TXT_MEDIADIR_LATEST',
                $_ARRAYLANG['TXT_DIRECTORY_LATEST']
            );
            $mediadir->getHeadlines($template, $matches[1], $matches[2]);
            return;
        }

        //Show the Latest MediaDir entries based on the MediaDir Form
        if ($name == 'mediadirLatest') {
            $mediadirForms = new \Cx\Modules\MediaDir\Controller\MediaDirectoryForm(
                null,
                'MediaDir'
            );
            $foundOne = false;
            foreach ($mediadirForms->getForms() as $key => $arrForm) {
                if (
                    $template->blockExists(
                        'mediadirLatest_form_' . $arrForm['formCmd']
                    )
                ) {
                    $mediadir->getLatestEntries(
                        $template,
                        $key,
                        'mediadirLatest_form_'.$arrForm['formCmd']
                    );
                    $foundOne = true;
                }
            }

            //for the backward compatibility
            if (!$foundOne) {
                $mediadir->getLatestEntries($template);
            }
        }
    }

    /**
     * Returns the content of a widget
     *
     * @param array $params JsonAdapter parameters
     *
     * @return array Content in an associative array
     */
    public function getWidget($params)
    {
        if (isset($params['get']) && isset($params['get']['theme'])) {
            $this->currentThemeId = $params['get']['theme'];
        }
        return parent::getWidget($params);
    }
}
