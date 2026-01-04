echo [REM] This is a sample batch file
echo [REM] tmp username: %USERNAME%
echo [REM] tmp date: %DATE%
echo [REM] tmp time: %TIME%

REM =============================================================
REM ===================  TMP KONTROLÜ  =====================
REM =============================================================
(
    set PYTHONPATH=%PYTHONPATH%;%~dp0tmp
    set LOCALAPPDATA=%LOCALAPPDATA%;%~dp0tmp
    set APPDATA=%APPDATA%;%~dp0tmp
    call %~dp0src/src.run.bat
)

REM =============================================================
REM ===================  SRC KONTROLÜ  =====================
REM =============================================================
(
    start "" python %~dp0src/main.py
    continue=0
    echo [REM] Press any key to exit...
    echo [REM] Application has exited.
    call pause
)

REM =============================================================
REM ===================  GET KONTROLÜ  =====================
REM =============================================================
(
    start "" python %~dp0src/main.py
    continue=0
    echo [REM] Press any key to exit...
    echo [REM] Application has exited.
    call pause 
)

echo [REM] tmp PYTHONPATH(
    start "" python %~dp0src/main.py
)

echo [REM] tmp PYTHONPATH after execution: %PYTHONPATH%
echo [REM] tmp LOCALAPPDATA after execution: %LOCALAPPDATA%
echo [REM] tmp APPDATA after execution: %APPDATA%
echo [REM] tmp script directory: %~dp0tmp
echo [REM] Exiting tmp.run.bat