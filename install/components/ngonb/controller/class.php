<?php
/**
 * Created by Sublime Text
 * User: Mikhail Kostikov
 * www.ngonb.ru
 * @ НГОНБ - 2024
 */

use \Bitrix\Main;
use Bitrix\Main\Application;
use \Bitrix\Main\Localization\Loc;
use \Bitrix\Main\Type;
//use \Ngonb\Wire\OrmTable;
use \Ngonb\Wire\wire;

class controller extends CBitrixComponent
{

    /**
     * проверяет подключение необходиимых модулей
     * @throws LoaderException
     */
    protected function checkModules()
    {
        if (!Main\Loader::includeModule('ngonb.wire'))
            throw new Main\LoaderException(Loc::getMessage('NGONB_WIRE_MODULE_NOT_INSTALLED'));
    }

   private function get($process, $rcpt, $isTest=false)
    {
        // в filter добавляем условие, чтобы читать только непрочитанные записи
        $result = wire::read(array(
            'select'  => array('ID', 'ID_PARENT','CODE_SOURCE','STATUS','TIME_WRITE','SIGNAL','SIGNAL_PARAMETERS'),
            'filter'  => array('PROCESS' => $process, 'CODE_RECIPIENT' => $rcpt, 'STATUS' => 'sent'),
            'order'   => array('TIME_WRITE'=>'ASC'),
            'limit'   => 10,
        ));

        $arRet = $result->fetchAll();

        if(!$isTest)
        {
            foreach($arRet as $rec)
            {
                wire::update($rec['ID'], array(
                    'STATUS'    => 'received',
                    'TIME_READ' => new Type\DateTime
                ));
            }
        }

        return $arRet;
    }

    private function put($process, $data)
    {
        $arData = json_decode($data, true);
        $ret = array();

        $result = wire::write(array(
            'PROCESS' => $process,
            'ID_PARENT' => (int)$arData['ID_PARENT'],
            'CODE_SOURCE' => $arData['CODE_SOURCE'],
            'CODE_RECIPIENT' => $arData['CODE_RECIPIENT'],
            'STATUS' => $arData['STATUS'],
            'SIGNAL' => $arData['SIGNAL'],
            'SIGNAL_PARAMETERS' => $arData['SIGNAL_PARAMETERS']
        ));

        if ($result->isSuccess())
        {
            $ret['STATUS'] = 'OK';
            $ret['DATA'] = $result->getId();
        }
        else
        {
            $ret['STATUS'] = 'ERROR';
            $ret['ERROR'] = $result->getErrorMessages();
        }

        return $ret;
    }

 

    public function executeComponent()
    {
        $this -> includeComponentLang('class.php');

        $this -> checkModules();

        $arDefaultUrlTemplates404 = array(
            "get" => "get/",
            "put" => "put/",
        );

        $arUrlTemplates = CComponentEngine::MakeComponentUrlTemplates(
            $arDefaultUrlTemplates404,
            $arParams['SEF_URL_TEMPLATES']
        );

        $arVariables = array();

        $componentPage = CComponentEngine::ParseComponentPath($this->arParams["SEF_FOLDER"], $arUrlTemplates, $arVariables);

        $arResult = array();

        $request = Application::getInstance()->getContext()->getRequest(); 
        $arRequest = $request->getQueryList()->toArray();

        $process = $arRequest['process'] ?: $this->arParams["PROCESS"];

        switch ($componentPage) {
            case 'get':
                $arResult['METHOD'] = "GET";
                //$arResult['DATA'] = $arRequest;
                $arResult['DATA'] = $this->get($this->arParams["PROCESS"], $arRequest['rcpt'], $arRequest['isTest']);
                break;
            
            case 'put':
                $arResult['METHOD'] = "PUT";

                $arFiles = $request->getFileList()->toArray();
                if(is_array($arFiles) and !empty($arFiles))
                {
                    // Загрузка файла
                    $arFile = array_shift($arFiles);
                    $to = $_SERVER["DOCUMENT_ROOT"] . $this->arParams["UPLOAD_DIR"] . "/".basename($arFile['name']);
                    if(move_uploaded_file($arFile['tmp_name'], $to))
                    {
                        $arResult['DATA'] = Loc::getMessage('FILE_UPLOAD_OK').": ".$to;
                    }
                    else
                    {
                        $arResult['DATA'] = Loc::getMessage('FILE_UPLOAD_ERROR').": ".$to;
                    }
                }
                else
                {
                    $request = file_get_contents('php://input');
                    $arResult['DATA'] = $this->put($this->arParams["PROCESS"], $request);
                }
                break;
            
            default: // help
                $arResult['METHOD'] = "UNKNOW";
                $arResult['DATA'] = "";
                break;
        }

        $this->arResult = $arResult;

        $this->includeComponentTemplate();
    }
};