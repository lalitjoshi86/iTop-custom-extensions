REM copyright   Copyright (C) 2019-2020 Jeffrey Bostoen
REM license     https://www.gnu.org/licenses/gpl-3.0.en.html
REM version     2020-01-23 11:41:53

:restart
c:\xampp\php\php.exe c:\xampp\htdocs\itop\web\webservices\cron.php --auth_user=admin --auth_pwd=admin --verbose=1
pause
goto restart
