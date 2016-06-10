<?php

namespace Cx\Model\Proxies;

/**
 * THIS CLASS WAS GENERATED BY THE DOCTRINE ORM. DO NOT EDIT THIS FILE.
 */
class CxModulesCalendarModelEntityCategoryProxy extends \Cx\Modules\Calendar\Model\Entity\Category implements \Doctrine\ORM\Proxy\Proxy
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

    public function setPos($pos)
    {
        $this->_load();
        return parent::setPos($pos);
    }

    public function getPos()
    {
        $this->_load();
        return parent::getPos();
    }

    public function setStatus($status)
    {
        $this->_load();
        return parent::setStatus($status);
    }

    public function getStatus()
    {
        $this->_load();
        return parent::getStatus();
    }

    public function addCategoryNames(\Cx\Modules\Calendar\Model\Entity\CategoryName $categoryNames)
    {
        $this->_load();
        return parent::addCategoryNames($categoryNames);
    }

    public function getCategoryNames()
    {
        $this->_load();
        return parent::getCategoryNames();
    }

    public function addEvents(\Cx\Modules\Calendar\Model\Entity\Event $events)
    {
        $this->_load();
        return parent::addEvents($events);
    }

    public function getEvents()
    {
        $this->_load();
        return parent::getEvents();
    }


    public function __sleep()
    {
        return array('__isInitialized__', 'id', 'pos', 'status', 'categoryNames', 'events');
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