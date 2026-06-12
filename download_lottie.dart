import 'dart:io';
import 'package:dio/dio.dart';

void main() async {
  final dir = Directory('assets/lottie');
  if (!await dir.exists()) {
    await dir.create(recursive: true);
  }
  
  try {
    print('Downloading lottie...');
    await Dio().download(
      'https://raw.githubusercontent.com/LottieFiles/lottie-react-native/master/example/assets/PinJump.json',
      'assets/lottie/success.json'
    );
    print('Lottie downloaded successfully!');
  } catch (e) {
    print('Error downloading lottie: \$e');
  }
}
