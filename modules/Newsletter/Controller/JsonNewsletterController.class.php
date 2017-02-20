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
 * JSON Adapter for Newsletter
 * 
 * @copyright   Cloudrexx AG
 * @author      Project Team SS4U <info@cloudrexx.com>
 * @package     cloudrexx
 * @subpackage  module_newsletter
 */
namespace Cx\Modules\Newsletter\Controller;

class JsonNewsletterException extends \Exception {}

/**
 * JSON Adapter for Newsletter
 * 
 * @copyright   Cloudrexx AG
 * @author      Project Team SS4U <info@cloudrexx.com>
 * @package     cloudrexx
 * @subpackage  module_newsletter
 */
class JsonNewsletterController extends \Cx\Core\Core\Model\Entity\Controller implements \Cx\Core\Json\JsonAdapter
{
    /**
     * List of messages
     *
     * @var Array
     */
    protected $messages = array();

    /**
     * Returns the internal name used as identifier for this adapter
     *
     * @return String Name of this adapter
     */
    public function getName() 
    {
        return 'Newsletter';
    }
    
    /**
     * Returns default permission as object
     *
     * @return Object
     */
    public function getDefaultPermissions()
    {
        return new \Cx\Core_Modules\Access\Model\Entity\Permission(null, null, true);
    }
    
    /**
     * Returns an array of method names accessable from a JSON request
     *
     * @return array List of method names
     */
    public function getAccessableMethods()
    {
        return array(
            'setUserStatus',
        );
    }

    /**
     * Returns all messages as string
     * @return String HTML encoded error messages
     */
    public function getMessagesAsString() {
        return implode('<br />', $this->messages);
    }

    /**
     * Change the user status
     *
     * @param array $params  GET and POST parameters
     *
     * @return array
     * @throws JsonNewsletterException
     */
    public function setUserStatus($params)
    {
        global $_CORELANG, $_ARRAYLANG;

        $this->loadLanguageData();

        if (!\Permission::checkAccess(174, 'static', true)) {
            throw new JsonNewsletterException($_CORELANG['TXT_ACCESS_DENIED_DESCRIPTION']);
        }

        $userId = !empty($params['post']['id']) ? contrexx_input2int($params['post']['id']) : 0;
        $status = !empty($params['post']['status']) ? contrexx_input2int($params['post']['status']) : 0;

        if (!$userId) {
            \DBG::log(__METHOD__ . ': User id is empty');
            throw new JsonNewsletterException($_ARRAYLANG['TXT_NEWSLETTER_USER_STATUS_CHANGE_ERROR']);
        }

        $newsletterLib = new NewsletterLib();
        if (!$newsletterLib->changeRecipientStatus(array($userId), $status)) {
            \DBG::log(__METHOD__ . ': Could not change the user status. User id => '. $userId);
            throw new JsonNewsletterException($_ARRAYLANG['TXT_NEWSLETTER_USER_STATUS_CHANGE_ERROR']);
        }

        return array('status' => 'success', 'message' => $_ARRAYLANG['TXT_NEWSLETTER_USER_STATUS_CHANGE_SUCCESS']);
    }

    /**
     * Loads the Newsletter language data
     */
    public function loadLanguageData() {
        global $_ARRAYLANG, $objInit;

        $langData = $objInit->loadLanguageData('Newsletter');
        $_ARRAYLANG = array_merge($_ARRAYLANG, $langData);
    }
}
