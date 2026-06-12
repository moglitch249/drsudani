import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'app_typography.dart';

class AppTheme {
  // DARK THEME
  static const Color background = Color(0xFF0D0D0D);
  static const Color surface = Color(0xFF1A1A1A);
  static const Color surfaceVariant = Color(0xFF242424);
  static const Color primary = Color(0xFF00AEEF);
  static const Color primaryDark = Color(0xFF007BB5);
  static const Color gold = Color(0xFFFFD700);
  static const Color textPrimary = Color(0xFFFFFFFF);
  static const Color textSecondary = Color(0xFFAAAAAA);
  static const Color textMuted = Color(0xFF555555);
  static const Color success = Color(0xFF00C853);
  static const Color warning = Color(0xFFFF9800);
  static const Color error = Color(0xFFFF3B3B);
  static const Color divider = Color(0xFF2A2A2A);

  // LIGHT THEME
  static const Color backgroundLight = Color(0xFFF5F5F5);
  static const Color surfaceLight = Color(0xFFFFFFFF);
  static const Color surfaceVariantLight = Color(0xFFEEEEEE);
  static const Color primaryLight = Color(0xFF00AEEF);

  // GRADIENTS
  static const Gradient primaryGradient = LinearGradient(
    colors: [Color(0xFF00AEEF), Color(0xFF0066CC)],
    begin: Alignment.topLeft,
    end: Alignment.bottomRight,
  );

  static const Gradient goldGradient = LinearGradient(
    colors: [Color(0xFFFFD700), Color(0xFFFF8C00)],
  );

  static const Gradient darkCardGradient = LinearGradient(
    colors: [Color(0xFF242424), Color(0xFF1A1A1A)],
    begin: Alignment.topLeft,
    end: Alignment.bottomRight,
  );

  static ThemeData getDarkTheme(String localeName) {
    final textTheme = AppTypography.getTheme(localeName);
    return ThemeData(
      brightness: Brightness.dark,
      scaffoldBackgroundColor: const Color(0xFF171026), // Deep purple/navy
      cardColor: const Color(0xFF24183E), // Slightly lighter for cards
      colorScheme: const ColorScheme.dark(
        primary: primary,
        secondary: primary,
        surface: Color(0xFF171026),
        error: error,
        onPrimary: Colors.white,
        onSecondary: Colors.white,
        onSurface: Colors.white,
        onError: Colors.white,
        brightness: Brightness.dark,
      ),
      bottomNavigationBarTheme: const BottomNavigationBarThemeData(
        backgroundColor: Color(0xFF24183E),
        selectedItemColor: primary,
        unselectedItemColor: Colors.white54,
      ),
      tabBarTheme: const TabBarThemeData(
        dividerColor: Colors.transparent, // Removes the ugly divider
      ),
      textTheme: GoogleFonts.cairoTextTheme(ThemeData.dark().textTheme).copyWith(
        displayLarge: GoogleFonts.cairo(fontSize: 57, fontWeight: FontWeight.bold, letterSpacing: -0.25),
        displayMedium: GoogleFonts.cairo(fontSize: 45, fontWeight: FontWeight.bold),
        displaySmall: GoogleFonts.cairo(fontSize: 36, fontWeight: FontWeight.bold),
        headlineLarge: GoogleFonts.cairo(fontSize: 32, fontWeight: FontWeight.bold),
        headlineMedium: GoogleFonts.cairo(fontSize: 28, fontWeight: FontWeight.w600),
        headlineSmall: GoogleFonts.cairo(fontSize: 24, fontWeight: FontWeight.w600),
        titleLarge: GoogleFonts.cairo(fontSize: 22, fontWeight: FontWeight.w600),
        titleMedium: GoogleFonts.cairo(fontSize: 16, fontWeight: FontWeight.w600, letterSpacing: 0.15),
        titleSmall: GoogleFonts.cairo(fontSize: 14, fontWeight: FontWeight.w500, letterSpacing: 0.1),
        labelLarge: GoogleFonts.cairo(fontSize: 14, fontWeight: FontWeight.w500, letterSpacing: 0.1),
        labelMedium: GoogleFonts.cairo(fontSize: 12, fontWeight: FontWeight.w500, letterSpacing: 0.5),
        labelSmall: GoogleFonts.cairo(fontSize: 11, fontWeight: FontWeight.w500, letterSpacing: 0.5),
        bodyLarge: GoogleFonts.cairo(fontSize: 16, fontWeight: FontWeight.w400, letterSpacing: 0.15),
        bodyMedium: GoogleFonts.cairo(fontSize: 14, fontWeight: FontWeight.w400, letterSpacing: 0.25),
        bodySmall: GoogleFonts.cairo(fontSize: 12, fontWeight: FontWeight.w400, letterSpacing: 0.4),
      ),
      dividerColor: divider,
      appBarTheme: const AppBarTheme(
        backgroundColor: Colors.transparent,
        elevation: 0,
        centerTitle: true,
        iconTheme: IconThemeData(color: textPrimary),
      ),
    );
  }

  static ThemeData getLightTheme(String localeName) {
    return ThemeData(
      brightness: Brightness.light,
      scaffoldBackgroundColor: backgroundLight,
      primaryColor: primaryLight,
      cardColor: surfaceLight,
      colorScheme: const ColorScheme.light(
        primary: primaryLight,
        secondary: primaryLight,
        surface: surfaceLight,
        error: error,
        onPrimary: Colors.white,
        onSecondary: Colors.white,
        onSurface: Color(0xFF1A1A1A),
        onError: Colors.white,
        brightness: Brightness.light,
      ),
      bottomNavigationBarTheme: const BottomNavigationBarThemeData(
        backgroundColor: surfaceLight,
        selectedItemColor: primaryLight,
        unselectedItemColor: Color(0xFF888888),
        type: BottomNavigationBarType.fixed,
      ),
      tabBarTheme: const TabBarThemeData(
        dividerColor: Colors.transparent,
      ),
      textTheme: GoogleFonts.cairoTextTheme(ThemeData.light().textTheme).copyWith(
        displayLarge: GoogleFonts.cairo(fontSize: 57, fontWeight: FontWeight.bold, letterSpacing: -0.25),
        displayMedium: GoogleFonts.cairo(fontSize: 45, fontWeight: FontWeight.bold),
        displaySmall: GoogleFonts.cairo(fontSize: 36, fontWeight: FontWeight.bold),
        headlineLarge: GoogleFonts.cairo(fontSize: 32, fontWeight: FontWeight.bold),
        headlineMedium: GoogleFonts.cairo(fontSize: 28, fontWeight: FontWeight.w600),
        headlineSmall: GoogleFonts.cairo(fontSize: 24, fontWeight: FontWeight.w600),
        titleLarge: GoogleFonts.cairo(fontSize: 22, fontWeight: FontWeight.w600),
        titleMedium: GoogleFonts.cairo(fontSize: 16, fontWeight: FontWeight.w600, letterSpacing: 0.15),
        titleSmall: GoogleFonts.cairo(fontSize: 14, fontWeight: FontWeight.w500, letterSpacing: 0.1),
        labelLarge: GoogleFonts.cairo(fontSize: 14, fontWeight: FontWeight.w500, letterSpacing: 0.1),
        labelMedium: GoogleFonts.cairo(fontSize: 12, fontWeight: FontWeight.w500, letterSpacing: 0.5),
        labelSmall: GoogleFonts.cairo(fontSize: 11, fontWeight: FontWeight.w500, letterSpacing: 0.5),
        bodyLarge: GoogleFonts.cairo(fontSize: 16, fontWeight: FontWeight.w400, letterSpacing: 0.15),
        bodyMedium: GoogleFonts.cairo(fontSize: 14, fontWeight: FontWeight.w400, letterSpacing: 0.25),
        bodySmall: GoogleFonts.cairo(fontSize: 12, fontWeight: FontWeight.w400, letterSpacing: 0.4),
      ),
      dividerColor: const Color(0xFFE0E0E0),
      appBarTheme: const AppBarTheme(
        backgroundColor: Colors.transparent,
        elevation: 0,
        centerTitle: true,
        iconTheme: IconThemeData(color: Color(0xFF1A1A1A)),
      ),
    );
  }
}
