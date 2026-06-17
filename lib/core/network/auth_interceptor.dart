import 'dart:convert';
import 'package:crypto/crypto.dart';
import 'package:dio/dio.dart';
import 'package:hive_flutter/hive_flutter.dart';

class AuthInterceptor extends Interceptor {
  // App Secret — يُمرر عند البناء: flutter build --dart-define=DS_APP_SECRET=xxx
  // القيمة الافتراضية للتطوير فقط — في الإنتاج يجب تمريرها عبر --dart-define
  static const String _appSecret = String.fromEnvironment(
    'DS_APP_SECRET',
    defaultValue: 'DrSudaniAppSec2026x9k2P',
  );

  @override
  void onRequest(RequestOptions options, RequestInterceptorHandler handler) {
    // === Layer 1: App Secret — يُثبت أن الطلب من تطبيق DrSudani ===
    options.headers['X-App-Secret'] = _appSecret;

    // === Layer 2: HMAC Payload Signature (التوقيع الرقمي لمنع التلاعب) ===
    final timestamp = DateTime.now().millisecondsSinceEpoch.toString();
    options.headers['X-App-Timestamp'] = timestamp;

    final path = options.path;
    String signPath = path;
    if (signPath.startsWith('/wp-json')) {
      signPath = signPath.substring(8); // removes '/wp-json'
    }
    
    final bodyStr = options.data != null ? jsonEncode(options.data) : '';
    final payload = '$signPath$timestamp$bodyStr';

    final hmac = Hmac(sha256, utf8.encode(_appSecret));
    final digest = hmac.convert(utf8.encode(payload));
    options.headers['X-App-Signature'] = digest.toString();

    // === Layer 3: JWT Token (إذا كان المستخدم مسجلاً للدخول) ===
    final token = Hive.box('auth').get('jwtToken');
    if (token != null) {
      // يتم إرسال التوكن كـ Header فقط
      options.headers['Authorization'] = 'Bearer $token';
    }

    super.onRequest(options, handler);
  }


  @override
  void onError(DioException err, ErrorInterceptorHandler handler) {
    if (err.response?.statusCode == 401) {
      final token = Hive.box('auth').get('jwtToken');
      if (token != null) {
        // Clear locally
        Hive.box('auth').delete('jwtToken');
        Hive.box('auth').delete('userId');
        
        // Attempt to blacklist token on server
        Dio().post(
          'https://drsudani.com/wp-json/drsudani/v1/logout',
          options: Options(headers: {
            'Authorization': 'Bearer $token',
            'X-App-Secret': _appSecret,
          }),
        ).catchError((_) => Response(requestOptions: RequestOptions(path: '')));
      }
    }
    super.onError(err, handler);
  }
}
