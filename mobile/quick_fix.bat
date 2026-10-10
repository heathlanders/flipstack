@echo off
echo =========================================
echo Quick Fix for Flutter Build Issues
echo =========================================
echo.

echo Step 1: Fix the conflicting user_model.dart
echo First, let's rename the old user_model to avoid conflicts:
echo.
ren "lib\features\authentication\data\models\user_model.dart" "lib\features\authentication\data\models\user_model_backup.dart"
echo Old user_model.dart renamed to backup
echo.

echo Step 2: Replace the problematic files with fixed versions
echo Please copy the fixed files I provided:
echo.
echo 1. Copy providers_fixed.dart content to: lib\presentation\providers\providers.dart
echo 2. Copy order_model_fixed.dart content to: lib\data\models\order_model.dart
echo.
echo Press any key when you've copied the fixed files...
pause

echo Step 3: Clean and rebuild
echo.
flutter clean
flutter pub get
dart run build_runner clean
dart run build_runner build

echo.
echo =========================================
echo Build should now work!
echo =========================================
echo.
echo If successful, you should see:
echo - No directive ordering errors
echo - No OrderStatus JSON errors
echo - All .freezed.dart and .g.dart files generated
echo.
pause
