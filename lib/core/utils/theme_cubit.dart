import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:hive_flutter/hive_flutter.dart';

class ThemeCubit extends Cubit<ThemeMode> {
  ThemeCubit() : super(ThemeMode.light);

  void toggleTheme() {
    final isDark = state == ThemeMode.dark;
    final newMode = isDark ? ThemeMode.light : ThemeMode.dark;
    Hive.box('settings').put('themeMode', newMode == ThemeMode.dark ? 'dark' : 'light');
    emit(newMode);
  }

  void setThemeMode(ThemeMode mode) {
    Hive.box('settings').put('themeMode', mode == ThemeMode.dark ? 'dark' : 'light');
    emit(mode);
  }

  void loadSavedTheme() {
    final saved = Hive.box('settings').get('themeMode', defaultValue: 'light');
    emit(saved == 'dark' ? ThemeMode.dark : ThemeMode.light);
  }
}
