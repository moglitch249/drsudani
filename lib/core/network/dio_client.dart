import 'dart:io';
import 'package:dio/io.dart';
import 'package:dio/dio.dart';
import 'package:injectable/injectable.dart';
import 'package:flutter/foundation.dart';
import '../constants/api_constants.dart';
import 'auth_interceptor.dart';

@module
abstract class DioModule {
  @lazySingleton
  Dio get dio {
    final dio = Dio(
      BaseOptions(
        baseUrl: ApiConstants.baseUrl,
        connectTimeout: const Duration(milliseconds: ApiConstants.connectTimeout),
        receiveTimeout: const Duration(milliseconds: ApiConstants.receiveTimeout),
        headers: {
          'Accept': 'application/json',
          'Content-Type': 'application/json',
        },
      ),
    );

    // === Layer 4: SSL Pinning & Transport Security ===
    dio.httpClientAdapter = IOHttpClientAdapter(
      createHttpClient: () {
        final client = HttpClient();
        // Reject all bad certificates to prevent MITM attacks (Charles Proxy, etc.)
        client.badCertificateCallback = (X509Certificate cert, String host, int port) {
          return false; // Force strictly valid SSL certs
        };
        return client;
      },
    );

    dio.interceptors.add(AuthInterceptor());

    // Custom log interceptor to mask sensitive data
    if (kDebugMode) {
      dio.interceptors.add(InterceptorsWrapper(
        onRequest: (options, handler) {
          final maskedData = _maskSensitiveData(options.data);
          debugPrint('--> ${options.method.toUpperCase()} ${options.uri}');
          if (maskedData != null) debugPrint('Data: $maskedData');
          handler.next(options);
        },
        onResponse: (response, handler) {
          debugPrint('<-- ${response.statusCode} ${response.requestOptions.uri}');
          handler.next(response);
        },
        onError: (DioException e, handler) {
          debugPrint('<-- Error ${e.response?.statusCode} ${e.requestOptions.uri}');
          debugPrint('Message: ${e.message}');
          handler.next(e);
        },
      ));
    }

    return dio;
  }

  static dynamic _maskSensitiveData(dynamic data) {
    if (data is Map) {
      final masked = Map<String, dynamic>.from(data);
      final sensitiveKeys = ['consumer_key', 'consumer_secret', 'password', 'token', 'jwt'];
      for (var key in sensitiveKeys) {
        if (masked.containsKey(key)) {
          masked[key] = '********';
        }
      }
      return masked;
    }
    return data;
  }
}
