echo [REM] Adding src to PYTHONPATH
echo [REM] Current PYTHONPATH: %PYTHONPATH%
echo [REM] Script directory: %~dp0src
echo [REM] Updated PYTHONPATH: %PYTHONPATH%;%~dp0src
REM =============================================================
REM ===================  SRC KONTROLÜ  =====================
REM =============================================================
(
    set PYTHONPATH=%PYTHONPATH%;%~dp0src
    set LOCALAPPDATA=%LOCALAPPDATA%;%~dp0src
    set APPDATA=%APPDATA%;%~dp0src
)