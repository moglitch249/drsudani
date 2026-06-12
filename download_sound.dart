import 'dart:io';
import 'package:dio/dio.dart';

void main() async {
  final dir = Directory('assets/sounds');
  if (!await dir.exists()) {
    await dir.create(recursive: true);
  }
  
  try {
    print('Downloading sound...');
    await Dio().download(
      'https://actions.google.com/sounds/v1/cartoon/magic_chime_chord.ogg',
      'assets/sounds/success.ogg'
    );
    print('Sound downloaded successfully!');
  } catch (e) {
    print('Error downloading sound: \$e');
  }
}
