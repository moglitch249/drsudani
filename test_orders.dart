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
    
    print('Fetching orders...');
    final ordersRes = await dio.get(
      'https://drsudani.com/wp-json/wc/v3/orders?customer=0',
      options: Options(
        headers: {
          'Authorization': 'Bearer \$token',
          'X-App-Secret': 'DrSudaniAppSec2026x9k2P',
        }
      )
    );
    print('Orders status: \${ordersRes.statusCode}');
    print('Orders body: \${ordersRes.data}');
    
  } on DioException catch (e) {
    print('Dio Error: \${e.response?.statusCode}');
    print('Error Body: \${e.response?.data}');
  } catch (e) {
    print('Error: \$e');
  }
}
