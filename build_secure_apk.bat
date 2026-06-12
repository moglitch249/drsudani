@echo off
echo =======================================================
echo Building Secure DrSudani APK (Obfuscated & Split Debug)
echo =======================================================

echo Cleaning old builds...
call D:\flutter\bin\flutter.bat clean
call D:\flutter\bin\flutter.bat pub get

echo.
echo Building release APK with Obfuscation...
call D:\flutter\bin\flutter.bat build apk --release --obfuscate --split-debug-info=build/app/outputs/symbols

echo.
echo =======================================================
echo Build complete!
echo Your secure APK is located at: build\app\outputs\flutter-apk\app-release.apk
echo Please backup the 'build/app/outputs/symbols' folder in case you need to read crash logs.
echo =======================================================
pause
