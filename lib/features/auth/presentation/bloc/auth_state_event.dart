import 'package:equatable/equatable.dart';
import '../../domain/entities/user.dart';

abstract class AuthEvent extends Equatable {
  const AuthEvent();
  @override
  List<Object?> get props => [];
}

class LoginWithEmailEvent extends AuthEvent {
  final String email;
  final String password;
  const LoginWithEmailEvent(this.email, this.password);
  @override
  List<Object?> get props => [email, password];
}

class RegisterWithEmailEvent extends AuthEvent {
  final String email;
  final String password;
  final String firstName;
  final String lastName;
  const RegisterWithEmailEvent(this.email, this.password, this.firstName, this.lastName);
  @override
  List<Object?> get props => [email, password, firstName, lastName];
}

class RequestWhatsAppOtpEvent extends AuthEvent {
  final String phone;
  const RequestWhatsAppOtpEvent(this.phone);
  @override
  List<Object?> get props => [phone];
}

class VerifyWhatsAppOtpEvent extends AuthEvent {
  final String phone;
  final String otp;
  const VerifyWhatsAppOtpEvent(this.phone, this.otp);
  @override
  List<Object?> get props => [phone, otp];
}

class CheckAuthStatusEvent extends AuthEvent {}

class LogoutEvent extends AuthEvent {}

abstract class AuthState extends Equatable {
  const AuthState();
  @override
  List<Object?> get props => [];
}

class AuthInitial extends AuthState {}

class AuthLoading extends AuthState {}

class AuthSuccess extends AuthState {
  final User user;
  const AuthSuccess(this.user);
  @override
  List<Object?> get props => [user];
}

class OtpSentSuccess extends AuthState {}

class AuthError extends AuthState {
  final String message;
  const AuthError(this.message);
  @override
  List<Object?> get props => [message];
}
