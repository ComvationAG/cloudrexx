<?php

namespace Cx\Core\Routing\Model\Entity;

/**
 * Exception from within this file
 * @copyright   CLOUDREXX CMS - CLOUDREXX AG
 * @author      Michael Ritter <drissg@gmail.com>
 * @version     1.0.0
 * @package     cloudrexx
 * @subpackage  core_routing
 */
class UrlException extends \Cx\Lib\Net\Model\Entity\UrlException {}

/**
 * Represents a Cloudrexx URL (knows about internal/external, frontend/backend/command, forces protocol, ports)
 * @todo add missing docblocks
 * @todo support cli
 */
abstract class Url extends \Cx\Lib\Net\Model\Entity\Url {
    
    public static function fromString($stringUrl) {
        return static::fromUrl(new \Cx\Lib\Net\Model\Entity\Url($stringUrl));
    }
    
    public static function fromUrl($url) {
        try {
            switch (static::getMode($url)) {
                case \Cx\Core\Core\Controller\Cx::MODE_FRONTEND:
                    return new FrontendUrl($stringUrl); // virtual language dirs and can be generated from pages and so
                    break;
                case \Cx\Core\Core\Controller\Cx::MODE_BACKEND:
                    return new BackendUrl($stringUrl); // can be generated from component backend commands
                    break;
                case \Cx\Core\Core\Controller\Cx::MODE_COMMAND:
                    return new CommandUrl($stringUrl); // can be generated from datasource/-access and component commands
                    break;
                default;
                    throw new UrlException('Unknown Url mode');
                    break;
            }
            
        // external url
        } catch (UrlException $e) {
            return $url;
        }
    }
    
    /**
     * Tells the mode for an internal Url
     * @param \Cx\Lib\Net\Model\Entity\Url Internal Url
     * @throws UrlException if Url is not internal
     * @return string One of the modes defined in Cx class
     */
    public static function getMode($internalUrl) {
        // sort out externals
        if (!static::isInternal($internalUrl)) {
            throw new UrlException('Not an internal Url');
        }
        
        $cx = \Cx\Core\Core\Controller\Cx::instanciate();
        $installationOffsetParts = explode('/', $cx->getWebsiteOffsetPath());
        $firstNonOffsetPartIndex = count($installationOffsetParts);
        $path = $internalUrl->getPathParts();
        $firstPathPart = '/';
        if (isset($path[$firstNonOffsetPartIndex])) {
            $firstPathPart .= $path[$firstNonOffsetPartIndex];
        }
        switch ($firstPathPart) {
            case \Cx\Core\Core\Controller\Cx::FOLDER_NAME_COMMAND_MODE:
                return \Cx\Core\Core\Controller\Cx::MODE_COMMAND;
                break;
            case 'admin':
            case \Cx\Core\Core\Controller\Cx::FOLDER_NAME_BACKEND:
                return \Cx\Core\Core\Controller\Cx::MODE_BACKEND;
                break;
            default:
                return \Cx\Core\Core\Controller\Cx::MODE_FRONTEND;
                break;
        }
    }
    
    /**
     * Tells wheter the given Url points to this CLX installation
     * @param \Cx\Lib\Net\Model\Entity\Url Url
     * @return boolean True if Url is internal, false otherwise
     */
    public static function isInternal($url) {
        $cx = \Cx\Core\Core\Controller\Cx::instanciate();
        
        // check domain
        $domainRepo = $cx->getDb()->getEntityManager()->getRepository(
            'Cx\Core\Net\Model\Entity\Domain'
        );
        if (!$domainRepo->findOneBy(array('name' => $url->getHost()))) {
            return false;
        }
        
        // check offset
        $installationOffset = $cx->getWebsiteOffsetPath();
        $providedOffset = $url->getPath();
        if (
            $installationOffset !=
            substr($providedOffset, 0, strlen($installationOffset))
        ) {
            return false;
        }
        return true;
    }
}
