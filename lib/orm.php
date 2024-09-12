<?php
/**
 * Created by Sublime Text
 * User: Mikhail Kostikov
 * www.ngonb.ru
 * @ НГОНБ - 2024
 */

namespace Ngonb\Wire;

use \Bitrix\Main\Entity;
use \Bitrix\Main\Type;
use Bitrix\Main\Diag;

class OrmTable extends Entity\DataManager
{
    public static function getTableName()
    {
        return 'ngonb_wire';
    }

    public static function getMap()
    {
        return array(
            // ID
            new Entity\IntegerField('ID', array(
                'primary' => true,
                'autocomplete' => true
            )),
            // ID сигнала-родителя
            new Entity\IntegerField('ID_PARENT', array(
                'default_value' => 0
            )),
            // Код процесса
            new Entity\StringField('PROCESS'),
            // Код источника сигнала
            new Entity\StringField('CODE_SOURCE', array(
                'required' => true,
            )),
            // Код получателя сигнала
            new Entity\StringField('CODE_RECIPIENT', array(
                'required' => true,
            )),
            // Статус сигнала 'sent' - отправлен, 'delivered' - доставлен (когда сами посылаем), 'received' - получен (когда получен через get-запрос)
            new Entity\EnumField('STATUS', array(
                'required' => true,
                'values' => array('sent', 'delivered', 'received'),
                'default_value' => 'sent'
            )),
            //Дата и время записи сигнала
            new Entity\DatetimeField('TIME_WRITE', array(
                'required' => true,
                'default_value' => new Type\DateTime
            )),

            //Дата и время доставки / получения сигнала
            new Entity\DatetimeField('TIME_READ'),
            // Сигнал
            new Entity\StringField('SIGNAL', array(
                'required' => true,
            )),
            // Доп.параметры сигнала
            new Entity\TextField('SIGNAL_PARAMETERS'),
        );
    }

    // При записи события проверяем, есть ли для него обработчик, и если есть - запускаем этот обработчик
    public static function onAfterAdd(Entity\Event $event)
    {
        $result = new \Bitrix\Main\Entity\EventResult;

        $data = $event->getParameter("fields");

        $className = 'Ngonb\Wire\\'.str_replace(array(' ','-','_'),'',$data['CODE_RECIPIENT']);
        if (class_exists($className))
        {
            Diag\Debug::dumpToFile($data, "onAfterAdd data");
            $row = OrmTable::getRow(array(
                'select'  => array('ID'),
                //'filter'  => array('PROCESS' => $data['PROCESS'], 'CODE_RECIPIENT' => $data['CODE_RECIPIENT'], '!STATUS' => 'received', 'SIGNAL' => strval($data['SIGNAL']), 'SIGNAL_PARAMETERS' => $data['SIGNAL_PARAMETERS']),
                'filter' => $data
            ));
            Diag\Debug::dumpToFile($row, "onAfterAdd row");
            $data['ID'] = $row['ID'];

            $rcptHandler = new $className($data);
            $status = $rcptHandler->getStatus();

            if($row['ID'] and !empty($status))
            {
                if(in_array($status, array('sent', 'delivered', 'received')))
                {
                    OrmTable::update($row['ID'], array(
                        'STATUS'    => $status,
                        'TIME_READ' => new Type\DateTime
                    ));
                }
                elseif($status == 'to delete') // чистим БД wire от пустых сигналов (для процесса nsocard: сигнал start)
                {
                    OrmTable::delete($row['ID']);
                }
            }
        }
        return $result;
    }
}