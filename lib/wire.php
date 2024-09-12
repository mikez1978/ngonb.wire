<?php
/**
 * Created by Sublime Text
 * User: Mikhail Kostikov
 * www.ngonb.ru
 * @ НГОНБ - 2024
 */
namespace Ngonb\Wire;

use \Bitrix\Main\Type;
use Bitrix\Main\Config\Option;
use Ngonb\Wire\OrmTable;

class wire
{
    // get -> read, post -> write

    public static function read($query)
    {
        $result = OrmTable::getList($query);

        return $result;
    }

    public static function readRow($query)
    {
        $result = OrmTable::getRow($query);

        return $result;
    }

    public static function write($fields)
    {
        $result = OrmTable::add($fields);

        return $result;
    }

    public static function writeError($fields)
    {
        // сначала делаем проверку, что такой сигнал есть и ещё не прочитан
        $fields['CODE_RECIPIENT'] = 'error_handler';
        $fields['STATUS'] = 'sent';
        $check = OrmTable::getRow(array(
            'select'  => array('ID'),
            'filter'  => array('PROCESS' => $fields['PROCESS'], 'CODE_RECIPIENT' => $fields['CODE_RECIPIENT'], '!STATUS' => 'received', 'SIGNAL' => $fields['SIGNAL'],'SIGNAL_PARAMETERS' => $fields['SIGNAL_PARAMETERS']),
        ));
        if(!$check['ID'])
        {
            $result = OrmTable::add($fields);
        }

        return true;
    }

    public static function writeLog($fields)
    {
        $fields['CODE_RECIPIENT'] = 'log_handler';
        $fields['STATUS'] = 'delivered';
        $result = OrmTable::add($fields);
        return true;
    }

    public static function update($id, $fields)
    {
        $result = OrmTable::update($id, $fields);

        return $result;
    }

    // находим цепочки запросов (в select можно передавать process)
    public static function getChain($filter=array(), $show_empty=true, $limit=1000)
    {
        $recAll = OrmTable::getList(array(
            'filter'    => $filter,//array('PROCESS' => $process),
            'limit'     => $limit,
            'order'     => array("ID"=>"DESC"),
            //'cache'     => array("ttl"=>36000)
        ))->fetchAll();

        $root = array();
        $child = array();
        $arData = array();
        foreach($recAll as $rec)
        {
            $arData[ $rec['ID'] ] = $rec;
            if($rec['ID_PARENT'] == 0)
            {
                $root[] = $rec['ID'];
            }
            else
            {
                $child[ $rec['ID_PARENT'] ][] = $rec['ID'];
            }
        }
        //PR($root);
        //PR($child);

        $result = array();
        foreach($root as $rootId)
        {
            //echo "<p>rootId = $rootId";
            $chain = array();
            $chain[] = $arData[$rootId];
            foreach($child[ $rootId ] as $childItem)
            {
                //echo "<p>foreach childItem (current) = $childItem";
                $current = $childItem;//$child[ $rootId ];
                $i = 0;
                while ($current) {
                    //echo "<p>while one $i current = $current";
                    if(is_array($child[ $current ]) and count($child[ $current ]) > 1) {
                        $chain[] = $arData[ $current ];
                        $j = 0;
                        $currentState = $current;
                        $current = $child[ $current ][$j];
                        while($current) {
                            //echo "<p>while two $j current = $current";
                            $chain[] = $arData[ $current ];
                            //PR($child[ $currentState ]);
                            $j++;
                            $current = $child[ $currentState ][$j];
                        }
                    }
                    else {
                        //echo "<p>else. current = $current";
                        $chain[] = $arData[ $current ];
                        $current = $child[ $current ][0];
                    }
                    $i++;
                }
            }
            if($i or $show_empty or ($chain[0]['SIGNAL'] != 'start') ) // можем не выводить одиночные сигналы START
            {
                $result[] = $chain;
            }
        }

        return $result;
    }


    // Запускаем обработчик для заданного сигнала
    public static function runHandler($id = false)
    {
        if (!$id) return false;

        $row = OrmTable::getRowById($id);

        $className = 'Ngonb\Wire\\'.str_replace(array(' ','-','_'),'',$row['CODE_RECIPIENT']);
        if (class_exists($className))
        {
            $rcptHandler = new $className($row);
            $status = $rcptHandler->getStatus();

            if(!empty($status) and in_array($status, array('sent', 'delivered', 'received')))
            {
               OrmTable::update($row['ID'], array(
                    'STATUS'    => $status,
                    'TIME_READ' => new Type\DateTime
                ));
            }
        }

        return $result;
    }


    // Ищем сигналы (последние в цепочке?) за заданное время для заданных получателей со статусом sent
    public static function getSentSignals($diff_minutes=60, $arRcpt=false, $limit=100)
    {
        $objDateTime = new Type\DateTime();
        $objDateTime->add("-".$diff_minutes." minutes");
        $filter = array(
            'STATUS'            => 'sent',
            '@CODE_RECIPIENT'   => $arRcpt, // @  IN (EXPR), в качестве значения передается массив или объект DB\SqlExpression
            '>TIME_WRITE'       => $objDateTime
        );

        $recAll = OrmTable::getList(array(
            'select'    => array('ID'),
            'filter'    => $filter,
            'limit'     => $limit,
            'order'     => array("ID"=>"ASC"),
        ))->fetchAll();

        return $recAll;
    }

    // АГЕНТ читаем сигналы <не start> со статусом sent из запускаем для них обработчик runHandler()
    public static function checkUncompleteSignal()
    {
        $arRcpt = explode(',', Option::get("ngonb.wire", "agent_recipient"));
        $arSignal = self::getSentSignals( Option::get("ngonb.wire", "agent_timediff"), $arRcpt );
        foreach($arSignal as $signal)
        {
            self::runHandler($signal['ID']);
        }
        return "\Ngonb\Wire\wire::checkUncompleteSignal();";
    }

}
