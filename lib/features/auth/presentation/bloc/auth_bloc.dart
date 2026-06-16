import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:injectable/injectable.dart';
import 'auth_state_event.dart';
import '../../domain/entities/user.dart';
import '../../domain/usecases/auth_usecases.dart';

@injectable
class AuthBloc extends Bloc<AuthEvent, AuthState> {
  final LoginUseCase loginUseCase;
  final RegisterUseCase registerUseCase;
  final RequestWhatsAppOtpUseCase requestWhatsAppOtpUseCase;
  final VerifyWhatsAppOtpUseCase verifyWhatsAppOtpUseCase;
  final CheckAuthStatusUseCase checkAuthStatusUseCase;
  final GetCurrentUserUseCase getCurrentUserUseCase;
  final LogoutUseCase logoutUseCase;

  AuthBloc(
    this.loginUseCase,
    this.registerUseCase,
    this.requestWhatsAppOtpUseCase,
    this.verifyWhatsAppOtpUseCase,
    this.checkAuthStatusUseCase,
    this.getCurrentUserUseCase,
    this.logoutUseCase,
  ) : super(AuthInitial()) {

    on<CheckAuthStatusEvent>((event, emit) async {
      emit(AuthLoading());
      final user = await getCurrentUserUseCase();
      if (user != null) {
        emit(AuthSuccess(user));
      } else {
        emit(AuthUnauthenticated());
      }
    });

    on<LogoutEvent>((event, emit) async {
      await logoutUseCase();
      emit(AuthUnauthenticated());
    });

    on<LoginWithEmailEvent>((event, emit) async {
      emit(AuthLoading());
      try {
        final user = await loginUseCase(event.email, event.password);
        emit(AuthSuccess(user));
      } catch (e) {
        emit(AuthError(e.toString()));
      }
    });

    on<RegisterWithEmailEvent>((event, emit) async {
      emit(AuthLoading());
      try {
        final user = await registerUseCase(event.email, event.password, event.firstName, event.lastName);
        emit(AuthSuccess(user));
      } catch (e) {
        emit(AuthError(e.toString()));
      }
    });

    on<RequestWhatsAppOtpEvent>((event, emit) async {
      emit(AuthLoading());
      try {
        await requestWhatsAppOtpUseCase(event.phone);
        emit(OtpSentSuccess());
      } catch (e) {
        emit(AuthError(e.toString()));
      }
    });

    on<VerifyWhatsAppOtpEvent>((event, emit) async {
      emit(AuthLoading());
      try {
        final user = await verifyWhatsAppOtpUseCase(event.phone, event.otp);
        emit(AuthSuccess(user));
      } catch (e) {
        emit(AuthError(e.toString()));
      }
    });
  }
}
