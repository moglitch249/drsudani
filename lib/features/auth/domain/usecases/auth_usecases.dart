import 'package:injectable/injectable.dart';
import '../entities/user.dart';
import '../repositories/auth_repository.dart';

@injectable
class LoginUseCase {
  final AuthRepository repository;

  LoginUseCase(this.repository);

  Future<User> call(String email, String password) {
    return repository.loginWithEmail(email, password);
  }
}

@injectable
class RegisterUseCase {
  final AuthRepository repository;

  RegisterUseCase(this.repository);

  Future<User> call(String email, String password, String firstName, String lastName) {
    return repository.registerWithEmail(email, password, firstName, lastName);
  }
}

@injectable
class RequestWhatsAppOtpUseCase {
  final AuthRepository repository;

  RequestWhatsAppOtpUseCase(this.repository);

  Future<void> call(String phone) {
    return repository.requestWhatsAppOtp(phone);
  }
}

@injectable
class VerifyWhatsAppOtpUseCase {
  final AuthRepository repository;

  VerifyWhatsAppOtpUseCase(this.repository);

  Future<User> call(String phone, String otp) {
    return repository.verifyWhatsAppOtp(phone, otp);
  }
}

@injectable
class CheckAuthStatusUseCase {
  final AuthRepository repository;
  CheckAuthStatusUseCase(this.repository);

  Future<bool> call() {
    return repository.isLoggedIn();
  }
}

@injectable
class LogoutUseCase {
  final AuthRepository repository;
  LogoutUseCase(this.repository);

  Future<void> call() {
    return repository.logout();
  }
}

@injectable
class GetCurrentUserUseCase {
  final AuthRepository repository;
  GetCurrentUserUseCase(this.repository);

  Future<User?> call() {
    return repository.getCurrentUser();
  }
}
