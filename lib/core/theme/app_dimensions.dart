import 'package:flutter/material.dart';

class AppDimensions {
  // Spacing (8pt grid)
  static const double xs = 4.0;
  static const double sm = 8.0;
  static const double md = 16.0;
  static const double lg = 24.0;
  static const double xl = 32.0;
  static const double xxl = 48.0;

  // Border Radius
  static const double radiusSm = 8.0;
  static const double radiusMd = 12.0;
  static const double radiusLg = 16.0;
  static const double radiusXl = 24.0;
  static const double radiusFull = 100.0; // pill shape

  // Card Elevation (simulate with BoxShadow in dark theme)
  static final BoxShadow cardShadow = BoxShadow(
    color: const Color(0xFF00AEEF).withOpacity(0.08),
    blurRadius: 16,
    offset: const Offset(0, 4),
  );

  static final BoxShadow primaryGlow = BoxShadow(
    color: const Color(0xFF00AEEF).withOpacity(0.3),
    blurRadius: 12,
    spreadRadius: 0,
    offset: const Offset(0, 2),
  );
}
