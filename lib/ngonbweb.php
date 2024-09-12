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

class ngonbweb
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
		        	$nsocard = new \Ngonb\Nsocard\nsocard;
		        	$this->status = $nsocard->handler($data);
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

}
