<?php

namespace Cx\Model\Proxies;

/**
 * THIS CLASS WAS GENERATED BY THE DOCTRINE ORM. DO NOT EDIT THIS FILE.
 */
class CxModulesCalendarModelEntityRegistrationProxy extends \Cx\Modules\Calendar\Model\Entity\Registration implements \Doctrine\ORM\Proxy\Proxy
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

    public function setDate($date)
    {
        $this->_load();
        return parent::setDate($date);
    }

    public function getDate()
    {
        $this->_load();
        return parent::getDate();
    }

    public function setSubmissionDate($submissionDate)
    {
        $this->_load();
        return parent::setSubmissionDate($submissionDate);
    }

    public function getSubmissionDate()
    {
        $this->_load();
        return parent::getSubmissionDate();
    }

    public function setHostName($hostName)
    {
        $this->_load();
        return parent::setHostName($hostName);
    }

    public function getHostName()
    {
        $this->_load();
        return parent::getHostName();
    }

    public function setIpAddress($ipAddress)
    {
        $this->_load();
        return parent::setIpAddress($ipAddress);
    }

    public function getIpAddress()
    {
        $this->_load();
        return parent::getIpAddress();
    }

    public function setType($type)
    {
        $this->_load();
        return parent::setType($type);
    }

    public function getType()
    {
        $this->_load();
        return parent::getType();
    }

    public function setKey($key)
    {
        $this->_load();
        return parent::setKey($key);
    }

    public function getKey()
    {
        $this->_load();
        return parent::getKey();
    }

    public function setUserId($userId)
    {
        $this->_load();
        return parent::setUserId($userId);
    }

    public function getUserId()
    {
        $this->_load();
        return parent::getUserId();
    }

    public function setLangId($langId)
    {
        $this->_load();
        return parent::setLangId($langId);
    }

    public function getLangId()
    {
        $this->_load();
        return parent::getLangId();
    }

    public function setExport($export)
    {
        $this->_load();
        return parent::setExport($export);
    }

    public function getExport()
    {
        $this->_load();
        return parent::getExport();
    }

    public function setPaymentMethod($paymentMethod)
    {
        $this->_load();
        return parent::setPaymentMethod($paymentMethod);
    }

    public function getPaymentMethod()
    {
        $this->_load();
        return parent::getPaymentMethod();
    }

    public function setPaid($paid)
    {
        $this->_load();
        return parent::setPaid($paid);
    }

    public function getPaid()
    {
        $this->_load();
        return parent::getPaid();
    }

    public function addRegistrationFormFieldValue(\Cx\Modules\Calendar\Model\Entity\RegistrationFormFieldValue $registrationFormFieldValue)
    {
        $this->_load();
        return parent::addRegistrationFormFieldValue($registrationFormFieldValue);
    }

    public function setRegistrationFormFieldValues($registrationFormFieldValues)
    {
        $this->_load();
        return parent::setRegistrationFormFieldValues($registrationFormFieldValues);
    }

    public function getRegistrationFormFieldValueByFieldId($fieldId)
    {
        $this->_load();
        return parent::getRegistrationFormFieldValueByFieldId($fieldId);
    }

    public function getRegistrationFormFieldValues()
    {
        $this->_load();
        return parent::getRegistrationFormFieldValues();
    }

    public function setEvent(\Cx\Modules\Calendar\Model\Entity\Event $event)
    {
        $this->_load();
        return parent::setEvent($event);
    }

    public function getEvent()
    {
        $this->_load();
        return parent::getEvent();
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
        return array('__isInitialized__', 'id', 'date', 'submissionDate', 'hostName', 'ipAddress', 'type', 'key', 'userId', 'langId', 'export', 'paymentMethod', 'paid', 'registrationFormFieldValues', 'event');
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