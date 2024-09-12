Настройка модуля: https://ngonb.ru/bitrix/admin/settings.php?lang=ru&mid=ngonb.wire&mid_menu=1

Модуль работы с картами - /local/modules/ngonb.nsocard, классы и обработчики – в папке/local/modules/ngonb.nsocard/lib
Настройки модуля: https://ngonb.ru/bitrix/admin/settings.php?lang=ru&mid=ngonb.nsocard&mid_menu=1
Страница администрирования: https://ngonb.ru/bitrix/admin/ngonb.nsocard_dispatcher.php
Для получения значений текущего подключения можно использовать GET-запрос к https://ngonb.ru/integration/nsocard/?action=getConfig
Для получения строки из БД заявлений  можно использовать GET-запрос к https://ngonb.ru/integration/nsocard/?action=getStatement&statementId=<ID>
Во всех случаях возвращается JSON-структура с полями STATUS = OK | ERROR, DATA или ERROR (текст ошибки).
Для обмена сигналами между компонентами системы используется модуль ngonb.wire, компонент которого ngonb:controller размещается на сайте. Например, в папку SITE_DIR/integration/wire/
Реализовано два метода запросов: запись сигналов и получение сигналов.
Запись сигналов
https://<site_name>/integration/wire/put/
на который отправляется POST-запрос с json-строкой следующего вида:
{
"PROCESS":"test", // строка, идентификатор процесса
"CODE_SOURCE":"<code_source>", // строка, код источника сигнала
"CODE_RECIPIENT":"error_handler", // строка, код получателя сигнала
"STATUS":"sent", // строка статуса  из списка: 'sent', 'delivered', 'received'
"SIGNAL":"test", // строка, посылаемый сигнал
"SIGNAL_PARAMETERS":"test data" // строка, параметры сигнала
}
Запись сигналов можно производить на странице администрирования модуля: /bitrix/admin/ngonb.nsocard_dispatcher.php?lang=ru

Получение сигналов:

Метод 1. HTTP-Запрос 
https://<site_name>/integration/wire/get/?rcpt=<получатель>[&isTest=Y],  где <получатель> - один из списка: ngonb-web, ngonb-readersdb, ngonb-opac, а также служебный получатель error_handler (для записи ошибок)
Параметр isTest=Y служит для получения записей без установки флага о прочтении – таким образом можно считывать запись несколько раз. В противном случае после чтения запись помечается как «прочтённая» и далее не считывается.
Структура возвращаемого ответа имеет вид:
array(2) {
  ["METHOD"]=> string
  ["DATA"]=>
  array {	
    [0]=>
    array {
      ["ID"]=> string
      ["ID_PARENT"]=> string
      ["CODE_SOURCE"]=> string
      ["STATUS"]=> string
      ["TIME_WRITE"]=> object(Bitrix\Main\Type\DateTime)
      ["SIGNAL"]=> string
      ["SIGNAL_PARAMETERS"]=> string
    },
    [1]
………………….
  }
}

Метод 2. Обработчик событий
При наличии в модуле ngonb.wire  класса с именем, равным имени источника без служебных символов (т.е. : ngonbweb, ngonbreadersdb, ngonbopac) данные сигнала будут переданы в конструктор соответствующего класса. Внутри класса можно реализовать обработку и отправку данных на соответствующие сервера.
Для примера можно посмотреть класс errorhandler.

После вызова конструктора обработчика вызывается метод обработчика  getStatus(), который возвращает статус из списка: 'sent', 'delivered', 'received' – то данный статус устанавливается для прочитанного сигнала, а также устанавливатся поле TIME_READ

Периодический запуск процессов (агенты)
Реализован агент \Ngonb\Wire\wire::checkUncompleteSignal();, проверяющий сигналы, находящиеся в статусе sent (т.е. необработанные) и запускающий для них обработчики.
Изменить периодичность запуска агентом можно на странице администрирования /bitrix/admin/agent_list.php
В настройках модуля общей шины: /bitrix/admin/settings.php?mid=ngonb.wire&lang=ru можно задать параметры: за какое время проверять неполученные сигналы (в минутах). Например, еслиустановлено «60», то проверяются сигналы за последний час. И список (через запятую) получателей сигналов, которые проверяются (например, log_handler, error_handler нам не надо проверять на исполнение – их не перечисляем)
