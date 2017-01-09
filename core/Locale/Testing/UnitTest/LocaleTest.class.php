<?php

/**
 * Cloudrexx
 *
 * @link      http://www.cloudrexx.com
 * @copyright Cloudrexx AG 2007-2017
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
 * LocaleTest
 *
 * @copyright   CLOUDREXX CMS - CLOUDREXX AG
 * @author      Nicola Tommasi <nicola.tommasi@comvation.com>
 * @package     cloudrexx
 * @subpackage  core_locale
 */

namespace Cx\Core\Locale\Testing\UnitTest;

/**
 * LocaleTest
 *
 * @copyright   CLOUDREXX CMS - CLOUDREXX AG
 * @author      Nicola Tommasi <nicola.tommasi@comvation.com>
 * @package     cloudrexx
 * @subpackage  core_locale
 */
class LocaleTest extends \Cx\Core\Test\Model\Entity\DoctrineTestCase {

    /**
     * Tests short form generation for locales with language and country
     */
    public function testShortFormLangAndCountry() {
        // Arrange
        $iso1 = 'de';
        $alpha2 = 'CH';
        $expectedShortForm = $iso1 . '-' . $alpha2;
        $language = $this->getLanguageMock($iso1);
        $country = $this->getCountryMock($alpha2);
        $locale = new \Cx\Core\Locale\Model\Entity\Locale();
        // Act
        $locale->setIso1($language);
        $locale->setCountry($country);
        // Assert
        $this->assertEquals($expectedShortForm, $locale->getShortForm());
    }

    /**
     * Tests short form generation for locales without country
     */
    public function testShortFormLangOnly() {
        // Arrange
        $iso1 = 'de';
        $language = $this->getLanguageMock($iso1);
        $locale = new \Cx\Core\Locale\Model\Entity\Locale();
        // Act
        $locale->setIso1($language);
        // Assert
        $this->assertEquals($iso1, $locale->getShortForm());
    }

    /**
     * Tests if magic method __toString returns the locale's label
     */
    public function testToString() {
        // Arrange
        $label = 'testLocale';
        $locale = new \Cx\Core\Locale\Model\Entity\Locale();
        // Act
        $locale->setLabel($label);
        // Assert
        $this->assertEquals($label, $locale->__toString());
    }

    /**
     * @param $iso1
     * @return \PHPUnit_Framework_MockObject_MockObject
     */
    protected function getLanguageMock($iso1) {
        $mock = $this->getMock(
            '\Cx\Core\Locale\Model\Entity\Language',
            array('getIso1')
        );
        $mock->method('getIso1')->willReturn($iso1);
        return $mock;
    }

    /**
     * @param $alpha2
     * @return \PHPUnit_Framework_MockObject_MockObject
     */
    protected function getCountryMock($alpha2) {
        $mock = $this->getMock(
            '\Cx\Core\Country\Model\Entity\Country',
            array('getAlpha2')
        );
        $mock->method('getAlpha2')->willReturn($alpha2);
        return $mock;
    }

}