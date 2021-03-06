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
 * Class BlogEventListener
 *
 * @copyright   Cloudrexx AG
 * @author Robin Glauser <robin.glauser@comvation.com>
 * @package     cloudrexx
 */

namespace Cx\Modules\Blog\Model\Event;

use Cx\Core\MediaSource\Model\Entity\MediaSourceManager;
use Cx\Core\MediaSource\Model\Entity\MediaSource;
use Cx\Core\Event\Model\Entity\DefaultEventListener;


/**
 * Class BlogEventListener
 *
 * @copyright   Cloudrexx AG
 * @author Robin Glauser <robin.glauser@comvation.com>
 * @package     cloudrexx
 */
class BlogEventListener extends DefaultEventListener {

    public function mediasourceLoad(MediaSourceManager $mediaBrowserConfiguration)
    {
        global $_ARRAYLANG;
        $mediaType = new MediaSource('blog',$_ARRAYLANG['TXT_BLOG_MODULE'],array(
            $this->cx->getWebsiteImagesBlogPath(),
            $this->cx->getWebsiteImagesBlogWebPath(),
        ),array(119));
        $mediaBrowserConfiguration->addMediaType($mediaType);
    }

}
