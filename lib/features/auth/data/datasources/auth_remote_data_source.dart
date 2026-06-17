import 'dart:convert';
import 'package:dio/dio.dart';
import 'package:flutter/foundation.dart';
import 'package:injectable/injectable.dart';
import '../../../../core/network/api_service.dart';
import '../../../../core/constants/api_constants.dart';
import '../models/user_model.dart';

abstract class AuthRemoteDataSource {
  Future<UserModel> loginWithEmail(String email, String password);
  Future<UserModel> registerWithEmail(String email, String password, String firstName, String lastName);
  Future<void> requestWhatsAppOtp(String phone);
  Future<UserModel> verifyWhatsAppOtp(String phone, String otp);
  Future<void> logout(String token);
}

@LazySingleton(as: AuthRemoteDataSource)
class AuthRemoteDataSourceImpl implements AuthRemoteDataSource {
  final ApiService apiService;

  AuthRemoteDataSourceImpl(this.apiService);

  @override
  Future<UserModel> loginWithEmail(String email, String password) async {
    try {
      final response = await apiService.dio.post(
        ApiConstants.jwtAuthPath,
        data: {
          'login': email, // Using 'login' parameter which accepts BOTH username and email
          'password': password,
        },
      );
    
      final responseData = response.data;
      
      Map<String, dynamic> userData = {};
      String token = '';
      int id = 0;
      String userEmail = email;

      if (responseData is Map) {
        if (responseData.containsKey('data') && responseData['data'] is Map) {
          final innerData = responseData['data'] as Map;
          if (innerData.containsKey('user') && innerData['user'] is Map) {
            userData = Map<String, dynamic>.from(innerData['user']);
          } else {
            userData = Map<String, dynamic>.from(innerData);
          }
          token = innerData['jwt']?.toString() ?? innerData['token']?.toString() ?? responseData['token']?.toString() ?? '';
        } else {
          userData = Map<String, dynamic>.from(responseData);
          token = responseData['token']?.toString() ?? responseData['jwt']?.toString() ?? '';
        }
        
        final rawId = userData['id'] ?? userData['user_id'] ?? responseData['id'] ?? responseData['user_id'] ?? 0;
        if (rawId is String) {
          id = int.tryParse(rawId) ?? 0;
        } else if (rawId is int) {
          id = rawId;
        }
        
        userEmail = userData['user_email'] ?? userData['email'] ?? responseData['user_email'] ?? email;
      }
      
      String parsedName = userData['user_display_name']?.toString() ?? responseData['user_display_name']?.toString() ?? '';

      // === NEW: Decode JWT to reliably extract user ID ===
      if (token.isNotEmpty) {
        try {
          final parts = token.split('.');
          if (parts.length == 3) {
            final payload = parts[1];
            var normalized = base64Url.normalize(payload);
            final decodedStr = utf8.decode(base64Url.decode(normalized));
            final payloadMap = jsonDecode(decodedStr) as Map<String, dynamic>;
            
            if (payloadMap.containsKey('id')) {
              id = int.tryParse(payloadMap['id'].toString()) ?? 0;
            } else if (payloadMap.containsKey('data') && payloadMap['data'] is Map) {
              if (payloadMap['data'].containsKey('user')) {
                id = int.tryParse(payloadMap['data']['user']['id'].toString()) ?? 0;
              }
            }
          }
        } catch (e) {
          debugPrint('Failed to decode JWT manually: $e');
        }
      }

      if (parsedName.isEmpty) {
        parsedName = email.split('@')[0];
      }

      // Try fetching real name if it's still missing or just the email prefix
      if (token.isNotEmpty && (parsedName == email.split('@')[0] || parsedName.isEmpty)) {
        try {
          final profileRes = await apiService.dio.get(
            '/wp-json/wp/v2/users/me',
            options: Options(headers: {'Authorization': 'Bearer $token'}),
          );
          if (profileRes.statusCode == 200 && profileRes.data != null) {
            final profileData = profileRes.data as Map<String, dynamic>;
            final name = profileData['name']?.toString().trim() ?? '';
            if (name.isNotEmpty) {
              parsedName = name;
            }
            if (id == 0 && profileData.containsKey('id')) {
              id = int.tryParse(profileData['id'].toString()) ?? 0;
            }
          }
        } catch (e) {
          // Ignore error, fallback to parsedName and whatever id we have
        }
      }

      return UserModel(
        id: id,
        email: userEmail,
        firstName: parsedName,
        lastName: '',
        token: token,
      );
    } catch (e) {
      if (e is DioException) {
        String backendMessage = 'البيانات غير صحيحة. تأكد من الإيميل وكلمة المرور.';
        if (e.response != null && e.response?.data is Map) {
          final data = e.response?.data as Map;
          if (data.containsKey('message')) {
            backendMessage = data['message'].toString();
          } else if (data['data'] is Map && data['data'].containsKey('message')) {
            backendMessage = data['data']['message'].toString();
          }
        }
        
        if (e.response?.statusCode == 400 || e.response?.statusCode == 401) {
          throw Exception('خطأ في الدخول: $backendMessage');
        } else if (e.response?.statusCode == 403) {
          throw Exception('ليس لديك صلاحية للدخول.');
        } else if (e.response?.statusCode == 404) {
          throw Exception('هذا الحساب أو الرابط غير موجود.');
        }
        throw Exception('خطأ في الاتصال: ${e.message}');
      }
      throw Exception('حدث خطأ غير متوقع: $e');
    }
  }

  @override
  Future<UserModel> registerWithEmail(String email, String password, String firstName, String lastName) async {
    // Requires a custom WooCommerce registration endpoint or WP REST API
    throw UnimplementedError('Backend needs specific registration endpoint');
  }

  @override
  Future<void> requestWhatsAppOtp(String phone) async {
    // Requires a custom WooCommerce endpoint handling WhatsApp API
    throw UnimplementedError('WhatsApp OTP endpoint not yet defined on backend');
  }

  @override
  Future<UserModel> verifyWhatsAppOtp(String phone, String otp) async {
    // Requires a custom WooCommerce endpoint handling WhatsApp OTP validation
    throw UnimplementedError('WhatsApp OTP validation not yet defined');
  }

  @override
  Future<void> logout(String token) async {
    try {
      await apiService.dio.post('/wp-json/drsudani/v1/logout');
    } catch (_) {}
  }
}
