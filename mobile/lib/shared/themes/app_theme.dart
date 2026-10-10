import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

class AppTheme {
  // Color schemes
  static const ColorScheme _lightColorScheme = ColorScheme.light(
    primary: Color(0xFF2196F3),
    primaryContainer: Color(0xFFBBDEFB),
    secondary: Color(0xFF03DAC6),
    secondaryContainer: Color(0xFFB2DFDB),
    tertiary: Color(0xFF4CAF50),
    tertiaryContainer: Color(0xFFC8E6C9),
    error: Color(0xFFF44336),
    errorContainer: Color(0xFFFFCDD2),
    surface: Color(0xFFFFFBFE), // FIXED: Using surface instead of deprecated background
    onPrimary: Colors.white,
    onSecondary: Colors.white,
    onSurface: Color(0xFF1C1B1F), // FIXED: Using onSurface instead of onBackground
    onError: Colors.white,
    outline: Color(0xFF79747E),
    shadow: Colors.black54,
  );

  static const ColorScheme _darkColorScheme = ColorScheme.dark(
    primary: Color(0xFF90CAF9),
    primaryContainer: Color(0xFF1976D2),
    secondary: Color(0xFF80CBC4),
    secondaryContainer: Color(0xFF00695C),
    tertiary: Color(0xFF81C784),
    tertiaryContainer: Color(0xFF388E3C),
    error: Color(0xFFEF5350),
    errorContainer: Color(0xFFB71C1C),
    surface: Color(0xFF121212), // FIXED: Using surface instead of deprecated background
    onPrimary: Colors.black,
    onSecondary: Colors.black,
    onSurface: Color(0xFFE6E1E5), // FIXED: Using onSurface instead of onBackground
    onError: Colors.black,
    outline: Color(0xFF938F99),
    shadow: Colors.black87,
  );

  // Light theme
  static ThemeData get lightTheme {
    return ThemeData(
      useMaterial3: true,
      colorScheme: _lightColorScheme,
      brightness: Brightness.light,
      visualDensity: VisualDensity.adaptivePlatformDensity,
      
      // App bar theme
      appBarTheme: AppBarTheme(
        elevation: 0,
        backgroundColor: _lightColorScheme.surface,
        foregroundColor: _lightColorScheme.onSurface,
        titleTextStyle: TextStyle(
          color: _lightColorScheme.onSurface,
          fontSize: 20,
          fontWeight: FontWeight.w500,
        ),
        iconTheme: IconThemeData(color: _lightColorScheme.onSurface),
        actionsIconTheme: IconThemeData(color: _lightColorScheme.onSurface),
        // FIXED: Using systemOverlayStyle correctly
        systemOverlayStyle: SystemUiOverlayStyle(
          statusBarColor: Colors.transparent,
          statusBarIconBrightness: Brightness.dark,
          systemNavigationBarColor: _lightColorScheme.surface,
          systemNavigationBarIconBrightness: Brightness.dark,
        ),
      ),

      // Card theme - FIXED: Using CardThemeData directly
      cardTheme: CardThemeData(
        elevation: 2,
        shadowColor: _lightColorScheme.shadow,
        surfaceTintColor: Colors.transparent,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(12),
        ),
      ),

      // Bottom navigation bar theme - FIXED: Using BottomNavigationBarThemeData directly
      bottomNavigationBarTheme: BottomNavigationBarThemeData(
        type: BottomNavigationBarType.fixed,
        backgroundColor: _lightColorScheme.surface,
        selectedItemColor: _lightColorScheme.primary,
        unselectedItemColor: _lightColorScheme.onSurface.withValues(alpha: 0.6), // FIXED: Using withValues instead of withOpacity
        elevation: 8,
        selectedLabelStyle: const TextStyle(
          fontSize: 12,
          fontWeight: FontWeight.w500,
        ),
        unselectedLabelStyle: const TextStyle(
          fontSize: 12,
          fontWeight: FontWeight.normal,
        ),
      ),

      // Input decoration theme
      inputDecorationTheme: InputDecorationTheme(
        filled: true,
        fillColor: _lightColorScheme.surfaceContainerHighest,
        border: OutlineInputBorder(
          borderRadius: BorderRadius.circular(8),
          borderSide: BorderSide(color: _lightColorScheme.outline),
        ),
        focusedBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(8),
          borderSide: BorderSide(color: _lightColorScheme.primary, width: 2),
        ),
        errorBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(8),
          borderSide: BorderSide(color: _lightColorScheme.error),
        ),
        contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
      ),

      // Elevated button theme
      elevatedButtonTheme: _elevatedButtonTheme,
      
      // Text button theme
      textButtonTheme: _textButtonTheme,
      
      // Outlined button theme
      outlinedButtonTheme: _outlinedButtonTheme,

      // Floating action button theme
      floatingActionButtonTheme: _floatingActionButtonTheme,

      // Text theme
      textTheme: _textTheme,

      // Icon theme
      iconTheme: IconThemeData(
        color: _lightColorScheme.onSurface,
        size: 24,
      ),

      // Divider theme
      dividerTheme: DividerThemeData(
        color: _lightColorScheme.outline.withValues(alpha: 0.3), // FIXED: Using withValues
        thickness: 1,
        space: 1,
      ),

      // Chip theme
      chipTheme: ChipThemeData(
        backgroundColor: _lightColorScheme.surfaceContainerLow,
        selectedColor: _lightColorScheme.primaryContainer,
        disabledColor: _lightColorScheme.onSurface.withValues(alpha: 0.12), // FIXED: Using withValues
        deleteIconColor: _lightColorScheme.onSurface,
        labelStyle: TextStyle(color: _lightColorScheme.onSurface),
        side: BorderSide(color: _lightColorScheme.outline),
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(8),
        ),
      ),

      // Switch theme
      switchTheme: SwitchThemeData(
        thumbColor: WidgetStateProperty.resolveWith((states) {
          if (states.contains(WidgetState.selected)) {
            return _lightColorScheme.primary;
          }
          return _lightColorScheme.outline;
        }),
        trackColor: WidgetStateProperty.resolveWith((states) {
          if (states.contains(WidgetState.selected)) {
            return _lightColorScheme.primary.withValues(alpha: 0.5); // FIXED: Using withValues
          }
          return _lightColorScheme.onSurface.withValues(alpha: 0.3); // FIXED: Using withValues
        }),
      ),

      // Checkbox theme
      checkboxTheme: CheckboxThemeData(
        fillColor: WidgetStateProperty.resolveWith((states) {
          if (states.contains(WidgetState.selected)) {
            return _lightColorScheme.primary;
          }
          return Colors.transparent;
        }),
        checkColor: WidgetStateProperty.all(_lightColorScheme.onPrimary),
        side: BorderSide(color: _lightColorScheme.outline, width: 2),
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(2),
        ),
      ),

      // Radio theme
      radioTheme: RadioThemeData(
        fillColor: WidgetStateProperty.resolveWith((states) {
          if (states.contains(WidgetState.selected)) {
            return _lightColorScheme.primary;
          }
          return _lightColorScheme.outline;
        }),
      ),

      // Slider theme
      sliderTheme: SliderThemeData(
        activeTrackColor: _lightColorScheme.primary,
        inactiveTrackColor: _lightColorScheme.primary.withValues(alpha: 0.3), // FIXED: Using withValues
        thumbColor: _lightColorScheme.primary,
        overlayColor: _lightColorScheme.primary.withValues(alpha: 0.2), // FIXED: Using withValues
        valueIndicatorColor: _lightColorScheme.primary,
        valueIndicatorTextStyle: TextStyle(color: _lightColorScheme.onPrimary),
      ),

      // Progress indicator theme
      progressIndicatorTheme: ProgressIndicatorThemeData(
        color: _lightColorScheme.primary,
        linearTrackColor: _lightColorScheme.primary.withValues(alpha: 0.3), // FIXED: Using withValues
        circularTrackColor: _lightColorScheme.primary.withValues(alpha: 0.3), // FIXED: Using withValues
      ),

      // Tab bar theme - FIXED: Using TabBarThemeData directly
      tabBarTheme: TabBarThemeData(
        labelColor: _lightColorScheme.primary,
        unselectedLabelColor: _lightColorScheme.onSurface.withValues(alpha: 0.6), // FIXED: Using withValues
        indicator: UnderlineTabIndicator(
          borderSide: BorderSide(color: _lightColorScheme.primary, width: 2),
        ),
      ),

      // Dialog theme - FIXED: Using DialogThemeData directly
      dialogTheme: DialogThemeData(
        backgroundColor: _lightColorScheme.surface,
        surfaceTintColor: Colors.transparent,
        elevation: 24,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(16),
        ),
        titleTextStyle: TextStyle(
          color: _lightColorScheme.onSurface,
          fontSize: 20,
          fontWeight: FontWeight.w500,
        ),
        contentTextStyle: TextStyle(
          color: _lightColorScheme.onSurface,
          fontSize: 14,
        ),
      ),

      // Bottom sheet theme
      bottomSheetTheme: BottomSheetThemeData(
        backgroundColor: _lightColorScheme.surface,
        surfaceTintColor: Colors.transparent,
        elevation: 16,
        shape: const RoundedRectangleBorder(
          borderRadius: BorderRadius.vertical(top: Radius.circular(16)),
        ),
      ),

      // Snack bar theme
      snackBarTheme: SnackBarThemeData(
        backgroundColor: _lightColorScheme.inverseSurface,
        contentTextStyle: TextStyle(color: _lightColorScheme.onInverseSurface),
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(8),
        ),
        behavior: SnackBarBehavior.floating,
        elevation: 6,
      ),
    );
  }

  // Dark theme
  static ThemeData get darkTheme {
    return ThemeData(
      useMaterial3: true,
      colorScheme: _darkColorScheme,
      brightness: Brightness.dark,
      visualDensity: VisualDensity.adaptivePlatformDensity,
      
      // App bar theme
      appBarTheme: AppBarTheme(
        elevation: 0,
        backgroundColor: _darkColorScheme.surface,
        foregroundColor: _darkColorScheme.onSurface,
        titleTextStyle: TextStyle(
          color: _darkColorScheme.onSurface,
          fontSize: 20,
          fontWeight: FontWeight.w500,
        ),
        iconTheme: IconThemeData(color: _darkColorScheme.onSurface),
        actionsIconTheme: IconThemeData(color: _darkColorScheme.onSurface),
        systemOverlayStyle: SystemUiOverlayStyle(
          statusBarColor: Colors.transparent,
          statusBarIconBrightness: Brightness.light,
          systemNavigationBarColor: _darkColorScheme.surface,
          systemNavigationBarIconBrightness: Brightness.light,
        ),
      ),

      // Card theme - FIXED: Using CardThemeData directly
      cardTheme: CardThemeData(
        elevation: 2,
        shadowColor: _darkColorScheme.shadow,
        surfaceTintColor: Colors.transparent,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(12),
        ),
      ),

      // Bottom navigation bar theme - FIXED: Using BottomNavigationBarThemeData directly
      bottomNavigationBarTheme: BottomNavigationBarThemeData(
        type: BottomNavigationBarType.fixed,
        backgroundColor: _darkColorScheme.surface,
        selectedItemColor: _darkColorScheme.primary,
        unselectedItemColor: _darkColorScheme.onSurface.withValues(alpha: 0.6), // FIXED: Using withValues
        elevation: 8,
        selectedLabelStyle: const TextStyle(
          fontSize: 12,
          fontWeight: FontWeight.w500,
        ),
        unselectedLabelStyle: const TextStyle(
          fontSize: 12,
          fontWeight: FontWeight.normal,
        ),
      ),

      // Input decoration theme
      inputDecorationTheme: InputDecorationTheme(
        filled: true,
        fillColor: _darkColorScheme.surfaceContainerHighest,
        border: OutlineInputBorder(
          borderRadius: BorderRadius.circular(8),
          borderSide: BorderSide(color: _darkColorScheme.outline),
        ),
        focusedBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(8),
          borderSide: BorderSide(color: _darkColorScheme.primary, width: 2),
        ),
        errorBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(8),
          borderSide: BorderSide(color: _darkColorScheme.error),
        ),
        contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
      ),

      // Elevated button theme
      elevatedButtonTheme: _elevatedButtonTheme,
      
      // Text button theme
      textButtonTheme: _textButtonTheme,
      
      // Outlined button theme
      outlinedButtonTheme: _outlinedButtonTheme,

      // Floating action button theme
      floatingActionButtonTheme: _floatingActionButtonTheme,

      // Text theme
      textTheme: _textTheme,

      // Icon theme
      iconTheme: IconThemeData(
        color: _darkColorScheme.onSurface,
        size: 24,
      ),

      // Divider theme
      dividerTheme: DividerThemeData(
        color: _darkColorScheme.outline.withValues(alpha: 0.3), // FIXED: Using withValues
        thickness: 1,
        space: 1,
      ),

      // Similar other themes for dark mode...
    );
  }

  // Button themes
  static final ElevatedButtonThemeData _elevatedButtonTheme = ElevatedButtonThemeData(
    style: ElevatedButton.styleFrom(
      elevation: 2,
      padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 12),
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(8),
      ),
      textStyle: const TextStyle(
        fontSize: 14,
        fontWeight: FontWeight.w500,
      ),
    ),
  );

  static final TextButtonThemeData _textButtonTheme = TextButtonThemeData(
    style: TextButton.styleFrom(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(8),
      ),
      textStyle: const TextStyle(
        fontSize: 14,
        fontWeight: FontWeight.w500,
      ),
    ),
  );

  static final OutlinedButtonThemeData _outlinedButtonTheme = OutlinedButtonThemeData(
    style: OutlinedButton.styleFrom(
      padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 12),
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(8),
      ),
      side: const BorderSide(width: 1),
      textStyle: const TextStyle(
        fontSize: 14,
        fontWeight: FontWeight.w500,
      ),
    ),
  );

  static final FloatingActionButtonThemeData _floatingActionButtonTheme = FloatingActionButtonThemeData(
    elevation: 6,
    highlightElevation: 12,
    shape: RoundedRectangleBorder(
      borderRadius: BorderRadius.circular(16),
    ),
  );

  // Text theme
  static const TextTheme _textTheme = TextTheme(
    displayLarge: TextStyle(
      fontSize: 57,
      fontWeight: FontWeight.w400,
      letterSpacing: -0.25,
    ),
    displayMedium: TextStyle(
      fontSize: 45,
      fontWeight: FontWeight.w400,
    ),
    displaySmall: TextStyle(
      fontSize: 36,
      fontWeight: FontWeight.w400,
    ),
    headlineLarge: TextStyle(
      fontSize: 32,
      fontWeight: FontWeight.w400,
    ),
    headlineMedium: TextStyle(
      fontSize: 28,
      fontWeight: FontWeight.w400,
    ),
    headlineSmall: TextStyle(
      fontSize: 24,
      fontWeight: FontWeight.w400,
    ),
    titleLarge: TextStyle(
      fontSize: 22,
      fontWeight: FontWeight.w500,
    ),
    titleMedium: TextStyle(
      fontSize: 16,
      fontWeight: FontWeight.w500,
      letterSpacing: 0.15,
    ),
    titleSmall: TextStyle(
      fontSize: 14,
      fontWeight: FontWeight.w500,
      letterSpacing: 0.1,
    ),
    labelLarge: TextStyle(
      fontSize: 14,
      fontWeight: FontWeight.w500,
      letterSpacing: 0.1,
    ),
    labelMedium: TextStyle(
      fontSize: 12,
      fontWeight: FontWeight.w500,
      letterSpacing: 0.5,
    ),
    labelSmall: TextStyle(
      fontSize: 11,
      fontWeight: FontWeight.w500,
      letterSpacing: 0.5,
    ),
    bodyLarge: TextStyle(
      fontSize: 16,
      fontWeight: FontWeight.w400,
      letterSpacing: 0.15,
    ),
    bodyMedium: TextStyle(
      fontSize: 14,
      fontWeight: FontWeight.w400,
      letterSpacing: 0.25,
    ),
    bodySmall: TextStyle(
      fontSize: 12,
      fontWeight: FontWeight.w400,
      letterSpacing: 0.4,
    ),
  );

  // Utility methods
  static Color getSurfaceColor(BuildContext context) {
    return Theme.of(context).colorScheme.surface;
  }

  static Color getOnSurfaceColor(BuildContext context) {
    return Theme.of(context).colorScheme.onSurface;
  }

  static Color getPrimaryColor(BuildContext context) {
    return Theme.of(context).colorScheme.primary;
  }

  static Color getSecondaryColor(BuildContext context) {
    return Theme.of(context).colorScheme.secondary;
  }

  static bool isDarkMode(BuildContext context) {
    return Theme.of(context).brightness == Brightness.dark;
  }

  static TextTheme getTextTheme(BuildContext context) {
    return Theme.of(context).textTheme;
  }

  static ColorScheme getColorScheme(BuildContext context) {
    return Theme.of(context).colorScheme;
  }
}