Cacti templates for SQL database of medialog application
based on Host template for Microsoft SQL Server 2000/2005/2008 from http://docs.cacti.net/usertemplate:host:microsoft:sqlserver


Установка

    Установите библиотеки PHP для работы с  MSSQL
	Для Cacti на linux-сервере
	  If using Red Hat based systems (RHEL, CentOS, Fedora, etc..), run 'yum install php-mssql'
	  If using Ubuntu based systems, run 'sudo apt-get install php5-sysbase'
	Для Cacti на windows-сервере.
	  Если используется версия PHP до 5.3, то используется встроенное расширение mssql. 
	  В более поздних версиях предлагается использовать расширение sqlsrv от компании Microsoft, для его использования придется переписать скрипт для использования функций sqlsrv. НЕ тестировалось.
    Распакуйте архив
    Для каждого сервера, для которого хотите получить графики, создайте пользователя с именем medistats, или выберите свое, и дайте доступ на чтение к базе данных медиалога.
    
    В текстовом редакторе откройте ss_win_mssql.php из каталога scripts
    Если вы планируете использовать одинаковые username/password/database на всех SQL серверах, на строчках 19-21 укажите нужные.
    Для ускорения работы используется MemCached (для разных графиков один и тот же запрос может вызываться несколько раз). Если вы не используете его, закоментируйте или удалите строки 25-30 and 103-104
	Установите сервис MemCached и библиотеки PHP
        You'll need to install the MemCached service as well as the PHP libraries which should be available through PECL
    Отдельные графики строются по определенным договорам FM_CONTR_ID, укажите нужные значения. Если переименовывать названия полей в скрипте, то их нужно будет поменять и в соостветсвующих шаблонах. 
      Если ничего не менять, то эти графики будут или пустые или показывать значения для случайных договоров. Можно вообще отключить эти графики и не создавать.
    Загрузите скрипт ss_win_medialog.php в каталог ./scripts где установлена Cacti 
    Импортируйте один шаблон хоста cacti_host_template_sql_server_-_medialog.xml или нужные шаблоны отдельных графиков cacti_graph_template через web-интерфейс
    Добавьте нужному хосту шаблон SQL Server - Medialog
    Создйте графики в настройках хоста
        Для каждого графика будут запрошены port, username,password, database. Оставьте пустые, если используются одинаковые настройки из скрипта, если нет, то заполните нужные значения.
        Для SQL-сервера порт по умолчанию 1433, если другой, то укажите нужный.

Тестировалось на:
    SQL Server 2005
    Medialog 7.10, 7.20

Для проверки работы скрипта, его можно запусать из командной строки на сервере, например:
php /var/www/cacti/scripts/ss_win_medialog.php 192.168.10.20 bill
результат должен быть вида:
cnt_pat:209 cnt_bill:266

Также рекомендуется установить оригинальный скрипт.

Дальнейший текст, инструкция от оригинального скрипта для мониторинга параметров Microsoft SQL Server

Host template for Microsoft SQL Server 2000/2005/2008 from http://docs.cacti.net/usertemplate:host:microsoft:sqlserver

Installation

    Install PHP's MSSQL libraries
        If using Red Hat based systems (RHEL, CentOS, Fedora, etc..), run 'yum install php-mssql'
        If using Ubuntu based systems, run 'sudo apt-get install php5-sysbase'
    Un-tar the archive
    For each SQL Server 2000 instance you want to graph:
        Launch the Enterprise Manager, open the file sql_server_2000.sql from under the sql scripts folder
        Update line 4 with the username/password combination you want to use
            If you changed the username on line 4, update the @loginname on lines 6, 8 & 10 to match
        Run the script
    For each SQL 2005/2008 instance you want to graph:
        Launch the SQL Management Studio, open the file sql_server_2005-2008.sql from under the sql scripts folder
        Update line 4 with the username/password combination you want to use
            If you changed the username on line 4, update the @loginname on lines 6, 8 & 10 to match
        Run the script
    In your preferred text editor open ss_win_mssql.php from under the scripts folder
    If you plan to use the same username/password combination on all your SQL Instances, update lines 19 & 20 with those you chose.
    I use MemCached to speed up the polling process so the code is setup to use it. If you choose not to, comment out (or delete) lines 24-29 and 72-73
        You'll need to install the MemCached service as well as the PHP libraries which should be available through PECL
    Upload ss_win_mssql.php to ./scripts
    Import cacti_host_template_windows_-_sql_server.xml via the console
    Assign the Windows - SQL Server host template to a device
    Create the graphs
        For each graph you'll be prompted for a port username and password. As mentioned in step 6, you can leave these blank if all the instances are setup the same. If they're not, fill in these fields.
        The default TCP port for the instance is setup to 1433. If that's not correct for the instance, specify that here as well.

Compatability

These templates have been tested with:

    SQL Server 2000, 2005 and 2008
    Windows 2003, 2008 & 2008 R2
