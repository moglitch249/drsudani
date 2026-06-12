class ApiConstants {
  // رابط الموقع
  static const String baseUrl = 'https://drsudani.com';

  // مسارات الـ API
  static const String wcApiPath = '/wp-json/wc/v3';
  static const String jwtAuthPath = '/wp-json/simple-jwt-login/v1/auth';
  static const String walletPath = '/wp-json/drsudani/v1/wallet';

  // مهلة الاتصال
  static const int connectTimeout = 60000;
  static const int receiveTimeout = 60000;
}
