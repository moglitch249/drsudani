import 'dart:convert';
import 'package:dio/dio.dart';

void main() async {
  final dio = Dio();
  dio.options.headers['X-App-Secret'] = 'DrSudaniAppSec2026x9k2P';
  
  try {
    print('Logging in...');
    final loginRes = await dio.post(
      'https://drsudani.com/wp-json/simple-jwt-login/v1/auth',
      data: {
        'login': 'salmmhmwdbas@gmail.com',
        'password': '(ateMnB)IJDWb9kJu13ch2G^'
      }
    );
    print('Login status: \${loginRes.statusCode}');
    
    String token = '';
    if (loginRes.data['data'] != null && loginRes.data['data']['jwt'] != null) {
      token = loginRes.data['data']['jwt'];
    }
    print('Got token: \$token');
    
    print('Fetching wallet...');
    final walletRes = await dio.get(
      'https://drsudani.com/wp-json/drsudani/v1/wallet',
      options: Options(
        headers: {
          'Authorization': 'Bearer \$token',
          'X-App-Secret': 'DrSudaniAppSec2026x9k2P',
        }
      )
    );
    print('Wallet status: \${walletRes.statusCode}');
    print('Wallet body: \${walletRes.data}');
    
  } on DioException catch (e) {
    print('Dio Error: \${e.response?.statusCode}');
    print('Error Body: \${e.response?.data}');
  } catch (e) {
    print('Error: \$e');
  }
}
