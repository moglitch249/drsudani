import 'dart:convert';
import 'package:hive_flutter/hive_flutter.dart';
import 'package:injectable/injectable.dart';

abstract class AuthLocalDataSource {
  Future<void> saveToken(String token);
  Future<void> saveUser(int id, String name, String email);
  Future<void> clearToken();
  bool hasToken();
  int? getUserId();
  String? getUserName();
  String? getUserEmail();
}

@LazySingleton(as: AuthLocalDataSource)
class AuthLocalDataSourceImpl implements AuthLocalDataSource {
  final Box _authBox = Hive.box('auth');

  @override
  Future<void> saveToken(String token) async {
    await _authBox.put('jwtToken', token);
  }

  @override
  Future<void> saveUser(int id, String name, String email) async {
    await _authBox.put('userId', id);
    await _authBox.put('userName', name);
    await _authBox.put('userEmail', email);
  }

  @override
  Future<void> clearToken() async {
    await _authBox.delete('jwtToken');
    await _authBox.delete('userId');
    await _authBox.delete('userName');
    await _authBox.delete('userEmail');
  }

  @override
  bool hasToken() {
    final token = _authBox.get('jwtToken');
    if (token == null || token.toString().isEmpty) return false;
    
    // === التحقق من انتهاء صلاحية التوكن (Token Expiration) ===
    try {
      final parts = token.toString().split('.');
      if (parts.length != 3) return false;
      
      final payload = parts[1];
      final normalized = base64Url.normalize(payload);
      final decodedStr = utf8.decode(base64Url.decode(normalized));
      final payloadMap = jsonDecode(decodedStr) as Map<String, dynamic>;
      
      if (payloadMap.containsKey('exp')) {
        final exp = int.tryParse(payloadMap['exp'].toString()) ?? 0;
        final now = DateTime.now().millisecondsSinceEpoch ~/ 1000;
        if (exp < now) {
          clearToken(); // Auto clear expired token
          return false;
        }
      }
      return true;
    } catch (e) {
      return false; 
    }
  }

  @override
  int? getUserId() {
    return _authBox.get('userId');
  }

  @override
  String? getUserName() {
    return _authBox.get('userName');
  }

  @override
  String? getUserEmail() {
    return _authBox.get('userEmail');
  }
}
