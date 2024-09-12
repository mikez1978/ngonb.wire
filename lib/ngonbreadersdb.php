<?php
/**
 * Created by Sublime Text
 * User: Mikhail Kostikov
 * www.ngonb.ru
 * @ НГОНБ - 2024
 */
namespace Ngonb\Wire;

use \Bitrix\Main;
use Bitrix\Main\Config\Option;
use \Ngonb\Wire\wire;

class ngonbreadersdb
{

    protected $process;
    protected $status;

	function __construct($data)
	{
        $this->process = $data['PROCESS'];
        switch ($this->process) {
        	case 'nsocard':
		        if (!Main\Loader::includeModule('ngonb.nsocard'))
		        {
        		    $this->status = false;
		        }
		        else
		        {
		        	$this->status = $this->handler($data);
		        }
        		break;
        	
        	default:
		        $this->status = false;
        		break;
        }
	}

    public function getStatus()
    {
        return $this->status;
    }

    public function handler($data)
    {
        switch ($data['SIGNAL']) {

            case 'new request file name': // качаем zip-файл заявлений с сервера
                $nsocard = new \Ngonb\Nsocard\nsocard;
                if(is_file($nsocard->data_files_dir . '/'.$data['SIGNAL_PARAMETERS']))
                {
                    // файл уже закачан
                    $this->status = 'delivered';
                }
                elseif($nsocard->checkTestMode() and $nsocard->checkDevMode())
                {
                    // с тестового сервера качаем
                    $this->status = $this->downloadRequestsFile_test($data, $nsocard);
                }
                elseif(!$nsocard->checkTestMode())
                {
                    // с продуктового сервера качаем
                    $this->status = $this->downloadRequestsFile_prod($data, $nsocard);
                }
                else
                {
                    // посылаем уведомление на ngonb-readersdb
                    $this->status = false;
                    if(mail("m.zlotnikov@nso.ru", "Уведомление ngonb.wire", "Надо скачать zip-файл заявлений. \n".print_r($data, true), "CC: m.kostikov@nso.ru\r\n"))
                    {
                        $this->status = 'delivered';
                    }
                }
                break;
            
            default:
                $this->status = false;
                break;
        }
        return $this->status;
    }

    public function downloadRequestsFile_test($data, $nsocard)
    {
        $command = "sshpass -p 'd\$Jfta!6XC' sftp -P 30901  sftpuser@185.138.128.10:ftp/export/".$data['SIGNAL_PARAMETERS']." ".$nsocard->data_files_dir."/".$data['SIGNAL_PARAMETERS'];
        $last_line = system($command, $retval);
        if(preg_match("/Fetching/", $last_line))
        {
            wire::write(array(
                'PROCESS'           => $nsocard->process,
                'ID_PARENT'         => (int)$data['ID'],
                'CODE_SOURCE'       => "ngonb-readersdb (local)",
                'CODE_RECIPIENT'    => $nsocard->code_source,
                'SIGNAL'            => 'received request file',
                'SIGNAL_PARAMETERS' => $data['SIGNAL_PARAMETERS']
            ));

            return "received";
        }
        else
        {
            wire::writeError(array(
                'PROCESS'           => $nsocard->process,
                'ID_PARENT'         => (int)$data['ID'],
                'CODE_SOURCE'       => $nsocard->code_source,
                'SIGNAL'            => $retval,
                'SIGNAL_PARAMETERS' => $last_line.'|'.$command
            ));
            return false;
        }
    }

    public function downloadRequestsFile_prod($data, $nsocard)
    {
        $remote_fpath = "http://10.0.24.109/get_file_from_readersdb.php?fname=".$data['SIGNAL_PARAMETERS'];
        $fpath = $nsocard->data_files_dir . '/'.$data['SIGNAL_PARAMETERS'];
        $content = file_get_contents($remote_fpath);
        if(strlen($content))
            $result = file_put_contents($fpath, $content);
        if($result)
        {
            wire::write(array(
                'PROCESS'           => $nsocard->process,
                'ID_PARENT'         => (int)$data['ID'],
                'CODE_SOURCE'       => "ngonb-readersdb (local)",
                'CODE_RECIPIENT'    => $nsocard->code_source,
                'SIGNAL'            => 'received request file',
                'SIGNAL_PARAMETERS' => $data['SIGNAL_PARAMETERS']
            ));
            return "received";
        }
        else
        {
           wire::writeError(array(
                'PROCESS'           => $nsocard->process,
                'ID_PARENT'         => (int)$data['ID'],
                'CODE_SOURCE'       => $nsocard->code_source,
                'SIGNAL'            => 'read goods file error (from ngonb-readersdb)',
            ));
            return false;
        }
    }

}
