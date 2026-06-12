import '../entities/user.dart';

abstract class AuthRepository {
  Future<User> loginWithEmail(String email, String password);
  Future<User> registerWithEmail(String email, String password, String firstName, String lastName);
  Future<void> requestWhatsAppOtp(String phone);
  Future<User> verifyWhatsAppOtp(String phone, String otp);
  Future<void> logout();
  Future<bool> isLoggedIn();
  Future<User?> getCurrentUser();
}
