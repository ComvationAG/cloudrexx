<?php

namespace Cx\Model\Proxies;

/**
 * THIS CLASS WAS GENERATED BY THE DOCTRINE ORM. DO NOT EDIT THIS FILE.
 */
class CxCoreLocaleModelEntityLocaleProxy extends \Cx\Core\Locale\Model\Entity\Locale implements \Doctrine\ORM\Proxy\Proxy
{
    private $_entityPersister;
    private $_identifier;
    public $__isInitialized__ = false;
    public function __construct($entityPersister, $identifier)
    {
        $this->_entityPersister = $entityPersister;
        $this->_identifier = $identifier;
    }
    private function _load()
    {
        if (!$this->__isInitialized__ && $this->_entityPersister) {
            $this->__isInitialized__ = true;
            if ($this->_entityPersister->load($this->_identifier, $this) === null) {
                throw new \Doctrine\ORM\EntityNotFoundException();
            }
            unset($this->_entityPersister, $this->_identifier);
        }
    }

    
    public function getId()
    {
        $this->_load();
        return parent::getId();
    }

    public function setIso1($iso1)
    {
        $this->_load();
        return parent::setIso1($iso1);
    }

    public function getIso1()
    {
        $this->_load();
        return parent::getIso1();
    }

    public function setLabel($label)
    {
        $this->_load();
        return parent::setLabel($label);
    }

    public function getLabel()
    {
        $this->_load();
        return parent::getLabel();
    }

    public function setFallback($fallback)
    {
        $this->_load();
        return parent::setFallback($fallback);
    }

    public function getFallback()
    {
        $this->_load();
        return parent::getFallback();
    }

    public function setSourceLanguage($sourceLanguage)
    {
        $this->_load();
        return parent::setSourceLanguage($sourceLanguage);
    }

    public function getSourceLanguage()
    {
        $this->_load();
        return parent::getSourceLanguage();
    }

    public function addLocales(\Cx\Core\Locale\Model\Entity\Locale $locales)
    {
        $this->_load();
        return parent::addLocales($locales);
    }

    public function getLocales()
    {
        $this->_load();
        return parent::getLocales();
    }

    public function setLanguageRelatedByIso1(\Cx\Core\Locale\Model\Entity\Language $languageRelatedByIso1)
    {
        $this->_load();
        return parent::setLanguageRelatedByIso1($languageRelatedByIso1);
    }

    public function getLanguageRelatedByIso1()
    {
        $this->_load();
        return parent::getLanguageRelatedByIso1();
    }

    public function setCountry(\Cx\Core\Country\Model\Entity\Country $country = NULL)
    {
        $this->_load();
        return parent::setCountry($country);
    }

    public function getCountry()
    {
        $this->_load();
        return parent::getCountry();
    }

    public function setLocale(\Cx\Core\Locale\Model\Entity\Locale $locale)
    {
        $this->_load();
        return parent::setLocale($locale);
    }

    public function getLocale()
    {
        $this->_load();
        return parent::getLocale();
    }

    public function setLanguageRelatedBySourceLanguage(\Cx\Core\Locale\Model\Entity\Language $languageRelatedBySourceLanguage)
    {
        $this->_load();
        return parent::setLanguageRelatedBySourceLanguage($languageRelatedBySourceLanguage);
    }

    public function getLanguageRelatedBySourceLanguage()
    {
        $this->_load();
        return parent::getLanguageRelatedBySourceLanguage();
    }

    public function __get($name)
    {
        $this->_load();
        return parent::__get($name);
    }

    public function getComponentController()
    {
        $this->_load();
        return parent::getComponentController();
    }

    public function setVirtual($virtual)
    {
        $this->_load();
        return parent::setVirtual($virtual);
    }

    public function isVirtual()
    {
        $this->_load();
        return parent::isVirtual();
    }

    public function validate()
    {
        $this->_load();
        return parent::validate();
    }

    public function __toString()
    {
        $this->_load();
        return parent::__toString();
    }


    public function __sleep()
    {
        return array('__isInitialized__', 'id', 'iso1', 'label', 'fallback', 'sourceLanguage', 'locales', 'languageRelatedByIso1', 'country', 'locale', 'languageRelatedBySourceLanguage');
    }

    public function __clone()
    {
        if (!$this->__isInitialized__ && $this->_entityPersister) {
            $this->__isInitialized__ = true;
            $class = $this->_entityPersister->getClassMetadata();
            $original = $this->_entityPersister->load($this->_identifier);
            if ($original === null) {
                throw new \Doctrine\ORM\EntityNotFoundException();
            }
            foreach ($class->reflFields AS $field => $reflProperty) {
                $reflProperty->setValue($this, $reflProperty->getValue($original));
            }
            unset($this->_entityPersister, $this->_identifier);
        }
        
    }
}