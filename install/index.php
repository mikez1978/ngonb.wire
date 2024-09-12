<?php
/**
 * Created by Sublime Text
 * User: Mikhail Kostikov
 * www.ngonb.ru
 * @ НГОНБ - 2024
 */

use \Bitrix\Main\Localization\Loc;
use \Bitrix\Main\Config as Conf;
use \Bitrix\Main\Config\Option;
use \Bitrix\Main\Loader;
use \Bitrix\Main\Entity\Base;
use \Bitrix\Main\Application;

Loc::loadMessages(__FILE__);
Class ngonb_wire extends CModule
{
    var $exclusionAdminFiles;

	function __construct()
	{
		$arModuleVersion = array();
		include(__DIR__."/version.php");

        $this->exclusionAdminFiles=array(
            '..',
            '.',
            'menu.php',
            'operation_description.php',
            'task_description.php'
        );

        $this->MODULE_ID = 'ngonb.wire';
		$this->MODULE_VERSION = $arModuleVersion["VERSION"];
		$this->MODULE_VERSION_DATE = $arModuleVersion["VERSION_DATE"];
		$this->MODULE_NAME = Loc::getMessage("NGONB_WIRE_MODULE_NAME");
		$this->MODULE_DESCRIPTION = Loc::getMessage("NGONB_WIRE_MODULE_DESC");

		$this->PARTNER_NAME = Loc::getMessage("NGONB_WIRE_PARTNER_NAME");
		$this->PARTNER_URI = Loc::getMessage("NGONB_WIRE_PARTNER_URI");

        $this->MODULE_SORT = 1;
        $this->SHOW_SUPER_ADMIN_GROUP_RIGHTS='N';
        $this->MODULE_GROUP_RIGHTS = "N";
	}

    //Определяем место размещения модуля
    public function GetPath($notDocumentRoot=false)
    {
        if($notDocumentRoot) 
            return str_ireplace( array(Application::getDocumentRoot(), '/home/bitrix/shared'),'',dirname(__DIR__));
        else
            return dirname(__DIR__);
    }

    //Проверяем что система поддерживает D7
    public function isVersionD7()
    {
        return CheckVersion(\Bitrix\Main\ModuleManager::getVersion('main'), '14.00.00');
    }


    function InstallDB()
    {
        Loader::includeModule($this->MODULE_ID);

        if(!Application::getConnection(\Ngonb\Wire\OrmTable::getConnectionName())->isTableExists(
            Base::getInstance('\Ngonb\Wire\OrmTable')->getDBTableName()
            )
        )
        {
            Base::getInstance('\Ngonb\Wire\OrmTable')->createDbTable();
        }

        CAgent::AddAgent("\Ngonb\Wire\wire::checkUncompleteSignal();", $this->MODULE_ID, "N", 600); // раз в 10 минут
    }


    function UnInstallDB()
    {
        Loader::includeModule($this->MODULE_ID);

        Application::getConnection(\Ngonb\Wire\OrmTable::getConnectionName())->
            queryExecute('drop table if exists '.Base::getInstance('\Ngonb\Wire\OrmTable')->getDBTableName());

        Option::delete($this->MODULE_ID);

        CAgent::RemoveModuleAgents($this->MODULE_ID);
    }


    function InstallEvents()
    {
        $et = new CEventType;
        $arrCeventType = array(
            "LID" => SITE_ID,
            "EVENT_NAME" => "NGONB_WIRE_NOTIFICATION",
            "NAME" => Loc::getMessage("NGONB_WIRE_NOTIFICATION_NAME"),
            "DESCRIPTION" => Loc::getMessage("NGONB_WIRE_NOTIFICATION_DESC"),
        );
        $et->Add($arrCeventType);

        $arSites = array();
        $sites = CSite::GetList('', '', Array("LANGUAGE_ID"=>SITE_ID));
        while ($site = $sites->Fetch())
            $arSites[] = $site["LID"];

        $arrCeventTemplate = array(
                'ACTIVE'=> 'Y',
                'EVENT_NAME' => 'NGONB_WIRE_NOTIFICATION',
                'LID' => $arSites,
                'EMAIL_FROM' => '#DEFAULT_EMAIL_FROM#',
                'EMAIL_TO' => '#NOTIFICATION_EMAIL#',
                'SUBJECT' => Loc::getMessage("NGONB_WIRE_NOTIFICATION_MAIL_SUBJ"),
                'BODY_TYPE' => 'text',
                'MESSAGE' => Loc::getMessage("NGONB_WIRE_NOTIFICATION_MAIL_BODY"),
        );

        $em = new CEventMessage;
        $em->Add($arrCeventTemplate);

        return true;
    }


    function UnInstallEvents()
    {
        $statusMes = array("NGONB_WIRE_NOTIFICATION");

        $eventType = new CEventType;
        $eventM = new CEventMessage;
        foreach($statusMes as $v)
        {
            $eventType->Delete($v);
            $dbEvent = CEventMessage::GetList("id", "asc", Array("EVENT_NAME" => $v));
            while($arEvent = $dbEvent->Fetch())
            {
                $eventM->Delete($arEvent["ID"]);
            }
        }

        return true;
    }


	function InstallFiles($arParams = array())
	{
        $path=$this->GetPath()."/install/components";

        if(\Bitrix\Main\IO\Directory::isDirectoryExists($path))
            CopyDirFiles($path, $_SERVER["DOCUMENT_ROOT"]."/bitrix/components", true, true);

        if (\Bitrix\Main\IO\Directory::isDirectoryExists($path = $this->GetPath() . '/admin'))
        {
            CopyDirFiles($this->GetPath() . "/install/admin/", $_SERVER["DOCUMENT_ROOT"] . "/bitrix/admin"); //если есть файлы для копирования
            if ($dir = opendir($path))
            {
                while (false !== $item = readdir($dir))
                {
                    if (in_array($item,$this->exclusionAdminFiles))
                        continue;
                    file_put_contents($_SERVER['DOCUMENT_ROOT'].'/bitrix/admin/'.$this->MODULE_ID.'_'.$item,
                        '<'.'? require($_SERVER["DOCUMENT_ROOT"]."'.$this->GetPath(true).'/admin/'.$item.'");?'.'>');
                }
                closedir($dir);
            }
        }

        return true;
	}


	function UnInstallFiles()
	{
        \Bitrix\Main\IO\Directory::deleteDirectory($_SERVER["DOCUMENT_ROOT"] . '/bitrix/components/ngonb/wire/');

        if (\Bitrix\Main\IO\Directory::isDirectoryExists($path = $this->GetPath() . '/admin')) {
            DeleteDirFiles($_SERVER["DOCUMENT_ROOT"] . $this->GetPath() . '/install/admin/', $_SERVER["DOCUMENT_ROOT"] . '/bitrix/admin');
            if ($dir = opendir($path)) {
                while (false !== $item = readdir($dir)) {
                    if (in_array($item, $this->exclusionAdminFiles))
                        continue;
                    \Bitrix\Main\IO\File::deleteFile($_SERVER['DOCUMENT_ROOT'] . '/bitrix/admin/' . $this->MODULE_ID . '_' . $item);
                }
                closedir($dir);
            }
        }
		return true;
	}


	function DoInstall()
	{
		global $APPLICATION;
        if($this->isVersionD7())
        {
            \Bitrix\Main\ModuleManager::registerModule($this->MODULE_ID);

            $this->InstallDB();
            $this->InstallEvents();
            $this->InstallFiles();

        }
        else
        {
            $APPLICATION->ThrowException(Loc::getMessage("NGONB_WIRE_INSTALL_ERROR_VERSION"));
        }

        $APPLICATION->IncludeAdminFile(Loc::getMessage("NGONB_WIRE_INSTALL_TITLE"), $this->GetPath()."/install/step.php");
	}


	function DoUninstall()
	{
        global $APPLICATION;

        $context = Application::getInstance()->getContext();
        $request = $context->getRequest();

        if($request["step"]<2)
        {
            $APPLICATION->IncludeAdminFile(Loc::getMessage("NGONB_WIRE_UNINSTALL_TITLE"), $this->GetPath()."/install/unstep1.php");
        }
        elseif($request["step"]==2)
        {
            $this->UnInstallFiles();
			$this->UnInstallEvents();

            if($request["savedata"] != "Y")
                $this->UnInstallDB();

            \Bitrix\Main\ModuleManager::unRegisterModule($this->MODULE_ID);

            $APPLICATION->IncludeAdminFile(Loc::getMessage("NGONB_WIRE_UNINSTALL_TITLE"), $this->GetPath()."/install/unstep2.php");
        }
	}


    function GetModuleRightList()
    {
        return array(
            "reference_id" => array("D","K","S","W"),
            "reference" => array(
                "[D] ".Loc::getMessage("NGONB_WIRE_DENIED"),
                "[K] ".Loc::getMessage("NGONB_WIRE_READ_COMPONENT"),
                "[S] ".Loc::getMessage("NGONB_WIRE_WRITE_SETTINGS"),
                "[W] ".Loc::getMessage("NGONB_WIRE_FULL"))
        );
    }
}
?>