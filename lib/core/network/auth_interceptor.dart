import 'dart:convert';
import 'package:crypto/crypto.dart';
import 'package:dio/dio.dart';
import 'package:hive_flutter/hive_flutter.dart';

class AuthInterceptor extends Interceptor {
  // مفتاح التطبيق السري (يجب أن يتطابق مع DS_APP_SECRET في السيرفر)
  final String _appSecret = 'DrSudaniAppSec2026x9k2P';

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
    // لا نحذف التوكن تلقائياً هنا لأن 401 قد يكون من endpoint محدد
    // (مثل المحفظة) وليس بالضرورة أن التوكن منتهي
    // التعامل مع 401 يتم داخل كل Cubit على حدة
    super.onError(err, handler);
  }
}
