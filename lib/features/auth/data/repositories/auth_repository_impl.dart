import 'package:injectable/injectable.dart';
import 'package:hive_flutter/hive_flutter.dart';
import '../../domain/entities/user.dart';
import '../../domain/repositories/auth_repository.dart';
import '../datasources/auth_remote_data_source.dart';
import '../datasources/auth_local_data_source.dart';
import 'dart:convert';
import 'package:dio/dio.dart';
import '../models/user_model.dart';

@LazySingleton(as: AuthRepository)
class AuthRepositoryImpl implements AuthRepository {
  final AuthRemoteDataSource remoteDataSource;
  final AuthLocalDataSource localDataSource;

  AuthRepositoryImpl(this.remoteDataSource, this.localDataSource);

  @override
  Future<User> loginWithEmail(String email, String password) async {
    final user = await remoteDataSource.loginWithEmail(email, password);
    await localDataSource.saveToken(user.token);
    await localDataSource.saveUser(user.id, user.firstName, user.email);
    return user;
  }

  @override
  Future<User> registerWithEmail(String email, String password, String firstName, String lastName) async {
    final user = await remoteDataSource.registerWithEmail(email, password, firstName, lastName);
    await localDataSource.saveToken(user.token);
    await localDataSource.saveUser(user.id, user.firstName, user.email);
    return user;
  }

  @override
  Future<void> requestWhatsAppOtp(String phone) {
    return remoteDataSource.requestWhatsAppOtp(phone);
  }

  @override
  Future<User> verifyWhatsAppOtp(String phone, String otp) async {
    final user = await remoteDataSource.verifyWhatsAppOtp(phone, otp);
    await localDataSource.saveToken(user.token);
    return user;
  }

  @override
  Future<void> logout() async {
    try {
      final token = Hive.box('auth').get('jwtToken');
      if (token != null) {
        await Dio().post(
          'https://drsudani.com/wp-json/drsudani/v1/logout',
          options: Options(headers: {
            'Authorization': 'Bearer $token',
            'X-App-Secret': 'DrSudaniAppSec2026x9k2P',
          }),
        );
      }
    } catch (_) {}
    await localDataSource.clearToken();
  }

  @override
  Future<bool> isLoggedIn() async {
    return localDataSource.hasToken();
  }

  bool _isTokenExpired(String token) {
    try {
      final parts = token.split('.');
      if (parts.length != 3) return true;
      final payloadStr = utf8.decode(base64Url.decode(base64Url.normalize(parts[1])));
      final payload = jsonDecode(payloadStr);
      final exp = payload['exp'] as int?;
      if (exp == null) return true;
      return exp < (DateTime.now().millisecondsSinceEpoch ~/ 1000);
    } catch (_) {
      return true;
    }
  }

  @override
  Future<User?> getCurrentUser() async {
    if (!localDataSource.hasToken()) return null;
    final token = Hive.box('auth').get('jwtToken')?.toString() ?? '';
    
    if (token.isEmpty || _isTokenExpired(token)) {
      await localDataSource.clearToken();
      return null;
    }

    return UserModel(
      id: localDataSource.getUserId() ?? 0,
      email: localDataSource.getUserEmail() ?? '',
      firstName: localDataSource.getUserName() ?? 'مستخدم',
      lastName: '',
      token: token,
    );
  }
}
