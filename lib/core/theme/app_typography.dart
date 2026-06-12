import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'app_theme.dart';

class AppTypography {
  static TextTheme getTheme(String localeName) {
    // English -> Poppins, Arabic -> Cairo
    final isArabic = localeName == 'ar';

    final textTheme = TextTheme(
      displayLarge: TextStyle(
        fontSize: 24,
        fontWeight: FontWeight.w700,
        letterSpacing: -0.5,
      ),
      displayMedium: TextStyle(
        fontSize: 20,
        fontWeight: FontWeight.w700,
      ),
      titleLarge: TextStyle(
        fontSize: 16,
        fontWeight: FontWeight.w600,
      ),
      titleMedium: TextStyle(
        fontSize: 14,
        fontWeight: FontWeight.w600,
      ),
      titleSmall: TextStyle(
        fontSize: 12,
        fontWeight: FontWeight.w600,
      ),
      bodyLarge: TextStyle(
        fontSize: 13,
        fontWeight: FontWeight.w400,
        height: 1.5,
      ),
      bodyMedium: TextStyle(
        fontSize: 11,
        fontWeight: FontWeight.w400,
        height: 1.5,
      ),
      labelLarge: TextStyle(
        fontSize: 12,
        fontWeight: FontWeight.w600,
        letterSpacing: 0.3,
      ),
      labelSmall: TextStyle(
        fontSize: 10,
        fontWeight: FontWeight.w500,
        letterSpacing: 0.5,
      ),
    );

    if (isArabic) {
      return GoogleFonts.cairoTextTheme(textTheme);
    } else {
      return GoogleFonts.poppinsTextTheme(textTheme);
    }
  }

  static TextStyle get priceStyle => TextStyle(
        fontSize: 20,
        fontWeight: FontWeight.w700,
        color: AppTheme.primary, // Changed from gold
      );

  static TextStyle get priceSmallStyle => TextStyle(
        fontSize: 15,
        fontWeight: FontWeight.w600,
        color: AppTheme.primary, // Changed from gold
      );
}
