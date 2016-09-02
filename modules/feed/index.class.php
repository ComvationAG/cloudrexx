<?php

/**
<<<<<<< HEAD
 * Contrexx
 *
 * @link      http://www.contrexx.com
 * @copyright Comvation AG 2007-2014
 * @version   Contrexx 4.0
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
 * "Contrexx" is a registered trademark of Comvation AG.
 * The licensing of the program under the AGPLv3 does not imply a
 * trademark license. Therefore any rights, title and interest in
 * our trademarks remain entirely with us.
 */

/**
=======
>>>>>>> f7ee35166c3ea0314d3113cfac8fc8894c4d0211
 * Feed
 * @copyright   CONTREXX CMS - COMVATION AG
 * @author      Ivan Schmid <ivan.schmid@comvation.com>
 * @author      Paulo M. Santos <pmsantos@astalavista.net>
 * @package     contrexx
 * @subpackage  module_feed
 * @todo        Edit PHP DocBlocks!
 */

// SECURITY CHECK
if (eregi('index.class.php', $_SERVER['PHP_SELF'])) {
    CSRF::header('Location: '.CONTREXX_DIRECTORY_INDEX);
    die();
}

/**
 * Includes
 */
require_once ASCMS_LIBRARY_PATH . '/PEAR/XML/RSS.class.php';

/**
 * Feed
 *
 * News Syndication Class to manage XML feeds
 * @copyright   CONTREXX CMS - COMVATION AG
 * @author      Ivan Schmid <ivan.schmid@comvation.com>
 * @author      Paulo M. Santos <pmsantos@astalavista.net>
 * @package     contrexx
 * @subpackage  module_feed
 */
class feed extends feedLibrary
{
	/**
	 * @var    \Cx\Core\Html\Sigma
	 */
    public $_objTpl;
    public $pageTitle;
    public $statusMessage;
    public $feedpath;

    // CONSTRUCTOR
    function __construct($pageContent)
    {
        $this->pageContent = $pageContent;
        $this->_objTpl = new \Cx\Core\Html\Sigma('.');
        CSRF::add_placeholder($this->_objTpl);
        $this->_objTpl->setErrorHandling(PEAR_ERROR_DIE);
    }

    // GET PAGE
    function getFeedPage() {
        if (isset($_GET['cmd']) && $_GET['cmd'] == "newsML") {
            $this->_showNewsML();
        } else {
            $this->showNews();
        }
        return $this->_objTpl->get();
    }

    function _showNewsML()
    {
        global $objDatabase, $_CORELANG;

        $this->_objTpl->setTemplate($this->pageContent, true, true);

        $id = intval($_GET['id']);
        $catId = isset($_GET['catid']) ? intval($_GET['catid']) : 0;

        $objDocument = $objDatabase->Execute("SELECT providerId, publicIdentifier, urgency, headLine, dataContent, thisRevisionDate FROM ".DBPREFIX."module_feed_newsml_documents WHERE id=".$id);
        if ($objDocument !== false) {
            $pics = array();
            $objAssociated = $objDatabase->Execute("SELECT pId_slave FROM ".DBPREFIX."module_feed_newsml_association WHERE pId_master='".$objDocument->fields['publicIdentifier']."' ORDER BY pId_slave");
            if ($objAssociated !== false) {
                while (!$objAssociated->EOF) {
                    $objPic = $objDatabase->SelectLimit("SELECT properties, source FROM ".DBPREFIX."module_feed_newsml_documents WHERE publicIdentifier LIKE '".$objAssociated->fields['pId_slave']."%' AND media_type='Photo'", 1);
                    if ($objPic !== false) {
                        if ($objPic->RecordCount() == 1) {
                            $arrTmpProperties = explode(';', $objPic->fields['properties']);
                            foreach ($arrTmpProperties as $property) {
                                $arrPair = explode(':', $property);
                                $arrProperties[base64_decode($arrPair[0])] = base64_decode($arrPair[1]);
                            }

                            $objProvider = $objDatabase->Execute("SELECT path FROM ".DBPREFIX."module_feed_newsml_providers WHERE providerId='".$objDocument->fields['providerId']."'");

                            $pics[$objAssociated->fields['pId_slave']] = array(
                                'source'    => $objProvider->fields['path'].'/'.$objPic->fields['source'],
                                'label'        => isset($arrProperties['label']) ? $arrProperties['label'] : '',
                                'width'        => isset($arrProperties['Width']) ? $arrProperties['Width'] : '',
                                'height'    => isset($arrProperties['Height']) ? $arrProperties['Height'] : ''
                            );

                            if ($pics[$objAssociated->fields['pId_slave']]['width'] == 170 && $pics[$objAssociated->fields['pId_slave']]['height'] == 115) {
                                $pic = $pics[$objAssociated->fields['pId_slave']];
                                break;
                            }
                        }
                    }

                    $objAssociated->MoveNext();
                }

                if (!isset($pic) && count($pics) > 0) {
                    reset($pics);
                    $pic = current($pics);
                }
            }

            $showPics = isset($pic);
            if (isset($pic) && $catId) {
                $objCategory = $objDatabase->SelectLimit("SELECT `showPics` FROM `".DBPREFIX."module_feed_newsml_categories` WHERE `id`=".$catId, 1);
                if ($objCategory !== false) {
                    $showPics = $objCategory->fields['showPics'] == '1' ? true : false;
                }
            }

            $arrWeekDays = explode(',', $_CORELANG['TXT_DAY_ARRAY']);
            $arrMonths = explode(',', $_CORELANG['TXT_MONTH_ARRAY']);

            $this->_objTpl->setVariable(array(
                'NEWSML_IMAGE'    => $showPics ? '<img src="'.$pic['source'].'" width="'.$pic['width'].'" height="'.$pic['height'].'" alt="'.$pic['label'].'" />' : '',
                'NEWSML_TITLE'    => $objDocument->fields['headLine'],
                'NEWSML_TEXT'    => $objDocument->fields['dataContent'],
                'NEWSML_DATE'        => $arrWeekDays[date('w', $objDocument->fields['thisRevisionDate'])].', '.date('j', $objDocument->fields['thisRevisionDate']).'. '.$arrMonths[date('n', $objDocument->fields['thisRevisionDate'])-1].' '.date('Y', $objDocument->fields['thisRevisionDate']).' / '.date('G:i', $objDocument->fields['thisRevisionDate']).' h',
                'NEWSML_LONG_DATE'    => date(ASCMS_DATE_FORMAT, $objDocument->fields['thisRevisionDate']),
                'NEWSML_SHORT_DATE'    => date(ASCMS_DATE_FORMAT_DATE, $objDocument->fields['thisRevisionDate'])
            ));

            $objDocument->MoveNext();
        }
    }



    function showNews() {
        global $objDatabase, $_ARRAYLANG, $_LANGID;

        $this->_objTpl->setTemplate($this->pageContent, true, true);

        //feed path
        $this->feedpath = ASCMS_FEED_PATH . '/';

        //active (with $_LANGID) categories
        $query = "SELECT id,
                           name
                      FROM ".DBPREFIX."module_feed_category
                     WHERE status = '1'
                       AND lang = '".$_LANGID."'
                  ORDER BY pos";
        if(($objResult = $objDatabase->Execute($query)))

        while (!$objResult->EOF) {
            $cat_id[$objResult->fields['id']]   = $objResult->fields['id'];
            $cat_name[$objResult->fields['id']] = $objResult->fields['name'];
            $objResult->MoveNext();
        }


        //active news
        $query = "SELECT id,
                           subid,
                           name
                      FROM ".DBPREFIX."module_feed_news
                     WHERE status = '1'
                  ORDER BY pos";
        $objResult = $objDatabase->Execute($query);

        while (!$objResult->EOF) {
            $news_subid[$objResult->fields['subid']][$objResult->fields['id']] = $objResult->fields['subid'];
            $news_id[$objResult->fields['subid']][$objResult->fields['id']]    = $objResult->fields['id'];
            $news_name[$objResult->fields['subid']][$objResult->fields['id']]  = $objResult->fields['name'];
            $objResult->MoveNext();
        }

        //no empty categories
        if(is_array($cat_id)){
            foreach($cat_id as $x){
                if(!isset($news_id[$x])){
                    unset($cat_id[$x]);
                    unset($cat_name[$x]);
                }
            }
        }

        if(count($cat_id) == 0){
            unset($cat_id);
        }

        //output structure
        if(!is_array($cat_id)){
            if(!isset($_GET['cat']) and !isset($_GET['news'])){
                $this->_objTpl->setVariable('FEED_NO_NEWSFEED', $_ARRAYLANG['TXT_FEED_NO_NEWSFEED']);
            }else{
                CSRF::header("Location: ".CONTREXX_DIRECTORY_INDEX."?section=feed");
            }
        } else {
            if ($this->_objTpl->blockExists('feed_cat')) {
                foreach($cat_id as $x){
                    //out cat
                    $this->_objTpl->setVariable('FEED_CAT_NAME', $cat_name[$x]);

                    //out news
                    foreach($news_id[$x] as $y){
                        $this->_objTpl->setVariable(array(
                            'FEED_NEWS_LINK'   => CONTREXX_DIRECTORY_INDEX.'?section=feed&amp;cat='.$news_subid[$x][$y].'&amp;news='.$news_id[$x][$y],
                            'FEED_NEWS_NAME'   => strip_tags($news_name[$x][$y])
                        ));
                        $this->_objTpl->parse('feed_news');
                    }
                    $this->_objTpl->parse('feed_cat');
                }
            }

            // first access
            if(!isset($_GET['cat']) and !isset($_GET['news'])){
                reset($cat_id);
                $_GET['cat'] = current($cat_id);
                reset($news_id[$_GET['cat']]);
                $_GET['news'] = current($news_id[$_GET['cat']]);
                /*
                foreach($cat_id as $x)
                {
                    $_GET['cat'] = $cat_id[$x];

                    foreach($news_id[$x] as $y)
                    {
                        $_GET['news'] = $news_id[$x][$y];
                        break;
                    }
                    break;
                }*/
            }

            $getCat  = intval($_GET['cat']);
            $getNews = intval($_GET['news']);

            //refresh control
            $query = "SELECT time,
                               cache
                          FROM ".DBPREFIX."module_feed_news
                         WHERE id = '".$getNews."'
                           AND subid = '".$getCat."'
                           AND status = '1'";
            $objResult = $objDatabase->Execute($query);
            if($objResult->RecordCount() == 0){
                CSRF::header("Location: ".CONTREXX_DIRECTORY_INDEX."?section=feed");
                die;
            }


            $old_time = $objResult->fields['time'] + $objResult->fields['cache'];
            $time = time();

            if($time >= $old_time){
                $this->showNewsRefresh($getNews, $time, $this->feedpath);
            }

            $query = "SELECT name,
                               filename,
                               time,
                               articles,
<<<<<<< HEAD
                               image,
                               `channel_link`,
                               `channel_description`,
                               `channel_build_date`
=======
                               image
>>>>>>> f7ee35166c3ea0314d3113cfac8fc8894c4d0211
                          FROM ".DBPREFIX."module_feed_news
                         WHERE id = '".$getNews."'
                           AND subid = '".$getCat."'
                           AND status = '1'";
            $objResult = $objDatabase->Execute($query);

            //output selected news
            $news_name = $objResult->fields['name'];

            $this->_objTpl->setVariable(array(
                'FEED_CAT'    => $cat_name[$getCat],
                'FEED_PAGE'   => $news_name
            ));

            $filename = $this->feedpath.$objResult->fields['filename'];

            //rss class
<<<<<<< HEAD
            $rss = new RSSFeedParser($filename);
=======
            $rss = new XML_RSS($filename);
>>>>>>> f7ee35166c3ea0314d3113cfac8fc8894c4d0211
            $rss->parse();
            //channel info
            $out_title = strip_tags($rss->channel['title']);
            $out_time  = date(ASCMS_DATE_FORMAT, $objResult->fields['time']);

            //image
            foreach ($rss->getImages() as $img) {
                if($img['url'] != '' && $objResult->fields['image'] == 1) {
                    $out_image = '<img src="'.strip_tags($img['url']).'" alt="" /><br />';
                }
            }


            $this->_objTpl->setVariable(array(
                'FEED_IMAGE'            => $out_image,
                'FEED_TITLE'            => $out_title,
                'FEED_TIME'             => $out_time,
<<<<<<< HEAD
                'TXT_FEED_LAST_UPTDATE' => $_ARRAYLANG['TXT_FEED_LAST_UPDATE'],
                'FEED_CHANNEL_LINK'             => contrexx_raw2xhtml($objResult->fields['channel_link']),
                'FEED_CHANNEL_DESCRIPTION'      => contrexx_raw2xhtml($objResult->fields['channel_description']),
                'FEED_CHANNEL_LAST_BUILD_DATE'  => contrexx_raw2xhtml($objResult->fields['channel_build_date']),
                'FEED_CHANNEL_IMAGE'            => !empty($out_image) ? $out_image : '',
                'FEED_CHANNEL_TITLE'            => contrexx_raw2xhtml($rss->channel['title']),
                'FEED_FETCH_TIME'               => $out_time,
=======
                'TXT_FEED_LAST_UPTDATE' => $_ARRAYLANG['TXT_FEED_LAST_UPDATE']
>>>>>>> f7ee35166c3ea0314d3113cfac8fc8894c4d0211
            ));

            //items
            $x = 0;
            foreach ($rss->getItems() as $value){
<<<<<<< HEAD
                if ($x < $objResult->fields['articles']) {
                    $rowClass        = $x % 2 ? 'row2' : 'row1';
                    $publicationDate = date('d.m.Y', strtotime($value['pubdate']));
                    $feedLink        = !empty($value['guid'])
                                        ? $value['guid']
                                        : (!empty($value['link']) ? $value['link'] : '');
                    $this->_objTpl->setVariable(array(
                        'FEED_ROWCLASS' => $rowClass,
                        'FEED_DATE'     => $publicationDate,
                        'FEED_LINK'     => $feedLink,
                        'FEED_NAME'     => $value['title'],
                        'FEED_ITEM_AUTHOR'          => $value['author'] ? contrexx_raw2xhtml($value['author']) : '',
                        'FEED_ITEM_DESCRIPTION'     => $value['description'] ? contrexx_remove_script_tags($value['description']) : '',
                        'FEED_ITEM_SOURCE'          => $value['source'] ? contrexx_raw2xhtml($value['source']) : '',
                        'FEED_ITEM_GUID'            => $value['guid'] ? contrexx_raw2xhtml($value['guid']) : '',
                        'FEED_ITEM_SUBTITLE'        => $value['subtitle'] ? contrexx_raw2xhtml($value['subtitle']) : '',
                        'FEED_ITEM_CATEGORY'        => $value['category'] ? contrexx_raw2xhtml($value['category']) : '',
                        'FEED_ITEM_ROWCLASS'        => $rowClass,
                        'FEED_ITEM_PUBDATE'         => $publicationDate,
                        'FEED_ITEM_LINK'            => $feedLink,
                        'FEED_ITEM_TITLE'           => $value['title'] ? contrexx_raw2xhtml($value['title']) : '',
                        'FEED_ITEM_DC_TITLE'        => $value['dc:title'] ? contrexx_raw2xhtml($value['dc:title']) : '',
                        'FEED_ITEM_DC_CREATOR'      => $value['dc:creator'] ? contrexx_raw2xhtml($value['dc:creator']) : '',
                        'FEED_ITEM_DC_SUBJECT'      => $value['dc:subject'] ? contrexx_raw2xhtml($value['dc:subject']) : '',
                        'FEED_ITEM_DC_DESCRIPTION'  => $value['dc:description'] ? contrexx_raw2xhtml($value['dc:description']) : '',
                        'FEED_ITEM_DC_PUBLISHER'    => $value['dc:publisher'] ? contrexx_raw2xhtml($value['dc:publisher']) : '',
                        'FEED_ITEM_DC_CONTRIBUTOR'  => $value['dc:contributor'] ? contrexx_raw2xhtml($value['dc:contributor']) : '',
                        'FEED_ITEM_DC_DATE'         => $value['dc:date'] ? contrexx_raw2xhtml($value['dc:date']) : '',
                        'FEED_ITEM_DC_TYPE'         => $value['dc:type'] ? contrexx_raw2xhtml($value['dc:type']) : '',
                        'FEED_ITEM_DC_FORMAT'       => $value['dc:format'] ? contrexx_raw2xhtml($value['dc:format']) : '',
                        'FEED_ITEM_DC_IDENTIFIER'   => $value['dc:identifier'] ? contrexx_raw2xhtml($value['dc:identifier']) : '',
                        'FEED_ITEM_DC_SOURCE'       => $value['dc:source'] ? contrexx_raw2xhtml($value['dc:source']) : '',
                        'FEED_ITEM_DC_LANGUAGE'     => $value['dc:language'] ? contrexx_raw2xhtml($value['dc:language']) : '',
                        'FEED_ITEM_DC_RELATION'     => $value['dc:relation'] ? contrexx_raw2xhtml($value['dc:relation']) : '',
                        'FEED_ITEM_DC_COVERAGE'     => $value['dc:coverage'] ? contrexx_raw2xhtml($value['dc:coverage']) : '',
                        'FEED_ITEM_DC_RIGHTS'       => $value['dc:rights'] ? contrexx_raw2xhtml($value['dc:rights']) : '',
=======
                if($x < $objResult->fields['articles']){
                    $this->_objTpl->setVariable(array(
                        'FEED_ROWCLASS' => $x % 2 ? 'row2' : 'row1',
                        'FEED_DATE'     => date('d.m.Y', strtotime($value['pubdate'])),
                        'FEED_LINK'     => $value['link'],
                        'FEED_NAME'     => $value['title'],
>>>>>>> f7ee35166c3ea0314d3113cfac8fc8894c4d0211
                    ));
                    $this->_objTpl->parse('feed_output_news');
                    $x++;
                }
            }
            $this->_objTpl->parse('feed_show_news');
        }
    }
}
?>