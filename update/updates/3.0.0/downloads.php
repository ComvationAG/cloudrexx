<?php
function _downloadsUpdate()
{
	global $objDatabase, $_ARRAYLANG, $_CORELANG;

    try{
        UpdateUtil::sql(
            "UPDATE ".DBPREFIX."module_downloads_download_locale l SET
              l.source = (SELECT source FROM ".DBPREFIX."module_downloads_download d WHERE d.id = l.download_id),
              l.source_name = (SELECT source_name FROM ".DBPREFIX."module_downloads_download d WHERE d.id = l.download_id);"
        );

        UpdateUtil::table(
            DBPREFIX.'module_downloads_download',
            array(
                'id'                 => array('type' => 'INT(10)', 'unsigned' => true, 'notnull' => true, 'auto_increment' => true),
                'type'               => array('type' => 'ENUM(\'file\',\'url\')', 'notnull' => true, 'default' => 'file', 'after' => 'id'),
                'mime_type'          => array('type' => 'ENUM(\'image\',\'document\',\'pdf\',\'media\',\'archive\',\'application\',\'link\')', 'notnull' => true, 'default' => 'image', 'after' => 'type'),
                'icon'               => array('type' => 'ENUM(\'_blank\',\'avi\',\'bmp\',\'css\',\'doc\',\'dot\',\'exe\',\'fla\',\'gif\',\'htm\',\'html\',\'inc\',\'jpg\',\'js\',\'mp3\',\'nfo\',\'pdf\',\'php\',\'png\',\'pps\',\'ppt\',\'rar\',\'swf\',\'txt\',\'wma\',\'xls\',\'zip\')', 'notnull' => true, 'default' => '_blank', 'after' => 'source_name'),
                'size'               => array('type' => 'INT(10)', 'unsigned' => true, 'notnull' => true, 'default' => '0', 'after' => 'icon'),
                'image'              => array('type' => 'VARCHAR(255)', 'notnull' => true, 'default' => '', 'after' => 'size'),
                'owner_id'           => array('type' => 'INT(5)', 'unsigned' => true, 'notnull' => true, 'default' => '0', 'after' => 'image'),
                'access_id'          => array('type' => 'INT(10)', 'unsigned' => true, 'notnull' => true, 'default' => '0', 'after' => 'owner_id'),
                'license'            => array('type' => 'VARCHAR(255)', 'notnull' => true, 'default' => '', 'after' => 'access_id'),
                'version'            => array('type' => 'VARCHAR(10)', 'notnull' => true, 'default' => '', 'after' => 'license'),
                'author'             => array('type' => 'VARCHAR(100)', 'notnull' => true, 'default' => '', 'after' => 'version'),
                'website'            => array('type' => 'VARCHAR(255)', 'notnull' => true, 'default' => '', 'after' => 'author'),
                'ctime'              => array('type' => 'INT(14)', 'unsigned' => true, 'notnull' => true, 'default' => '0', 'after' => 'website'),
                'mtime'              => array('type' => 'INT(14)', 'unsigned' => true, 'notnull' => true, 'default' => '0', 'after' => 'ctime'),
                'is_active'          => array('type' => 'TINYINT(3)', 'unsigned' => true, 'notnull' => true, 'default' => '0', 'after' => 'mtime'),
                'visibility'         => array('type' => 'TINYINT(1)', 'unsigned' => true, 'notnull' => true, 'default' => '1', 'after' => 'is_active'),
                'order'              => array('type' => 'INT(3)', 'unsigned' => true, 'notnull' => true, 'default' => '0', 'after' => 'visibility'),
                'views'              => array('type' => 'INT(10)', 'unsigned' => true, 'notnull' => true, 'default' => '0', 'after' => 'order'),
                'download_count'     => array('type' => 'INT(10)', 'unsigned' => true, 'notnull' => true, 'default' => '0', 'after' => 'views'),
                'expiration'         => array('type' => 'INT(14)', 'unsigned' => true, 'notnull' => true, 'default' => '0', 'after' => 'download_count'),
                'validity'           => array('type' => 'INT(10)', 'unsigned' => true, 'notnull' => true, 'default' => '0', 'after' => 'expiration')
            ),
            array(
                'is_active'          => array('fields' => array('is_active')),
                'visibility'         => array('fields' => array('visibility'))
            )
        );

        UpdateUtil::table(
            DBPREFIX.'module_downloads_download_locale',
            array(
                'lang_id'        => array('type' => 'INT(11)', 'unsigned' => true, 'notnull' => true, 'default' => '0'),
                'download_id'    => array('type' => 'INT(11)', 'unsigned' => true, 'notnull' => true, 'default' => '0', 'after' => 'lang_id'),
                'name'           => array('type' => 'VARCHAR(255)', 'notnull' => true, 'default' => '', 'after' => 'download_id'),
                'source'         => array('type' => 'VARCHAR(255)', 'after' => 'name'),
                'source_name'    => array('type' => 'VARCHAR(255)', 'after' => 'source'),
                'description'    => array('type' => 'text', 'after' => 'source_name'),
            ),
            array(
                'name'           => array('fields' => array('name'), 'type' => 'FULLTEXT'),
                'description'    => array('fields' => array('description'), 'type' => 'FULLTEXT')
            )
        );
    }
    catch (UpdateException $e) {
        // we COULD do something else here..
        return UpdateUtil::DefaultActionHandler($e);
    }

    return true;
}