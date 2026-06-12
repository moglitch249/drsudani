import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:hive_flutter/hive_flutter.dart';

class LocaleCubit extends Cubit<Locale> {
  LocaleCubit() : super(const Locale('ar'));

  void switchToArabic() {
    Hive.box('settings').put('locale', 'ar');
    emit(const Locale('ar'));
  }

  void switchToEnglish() {
    Hive.box('settings').put('locale', 'en');
    emit(const Locale('en'));
  }

  void loadSavedLocale() {
    final saved = Hive.box('settings').get('locale', defaultValue: 'ar');
    emit(Locale(saved));
  }
}
