<?php
/**
 * Created by Sublime Text
 * User: Mikhail Kostikov
 * www.ngonb.ru
 * @ НГОНБ - 2024
 */

namespace Ngonb\Wire;

use Bitrix\Main\Config\Option;
use Bitrix\Main\Mail\Event;

class errorhandler
{
    protected $status;

    function __construct($data)
    {
        $this->status = false;
        $arEventFields = array(
            "NOTIFICATION_EMAIL"    => Option::get("ngonb.wire", "notification_email"),
            "TEXT"                  => print_r($data, true),
        );
        
        //if(mail(Option::get("ngonb.wire", "notification_email"), "Уведомление ngonb.wire", "Данные: ".print_r($data, true)))
        $mres = Event::send(array(
            "EVENT_NAME" => "NGONB_WIRE_NOTIFICATION",
            "LID" => "s1",
            "C_FIELDS" => $arEventFields,
        ));
        //if(CEvent::Send("NGONB_WIRE_NOTIFICATION", SITE_ID, $arEventFields))
        if($mres->getId())
        {
            $this->status = 'delivered';
        }
    }

    public function getStatus()
    {
        return $this->status;
    }
}