import 'dart:async';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';
import 'package:pin_code_fields/pin_code_fields.dart';
import 'package:flutter_animate/flutter_animate.dart';
import '../../../../core/theme/app_theme.dart';
import '../../../../core/theme/app_dimensions.dart';
import '../../../../core/widgets/ds_button.dart';
import '../bloc/auth_bloc.dart';
import '../bloc/auth_state_event.dart';

class OtpScreen extends StatefulWidget {
  final String phone;

  const OtpScreen({Key? key, required this.phone}) : super(key: key);

  @override
  State<OtpScreen> createState() => _OtpScreenState();
}

class _OtpScreenState extends State<OtpScreen> {
  String currentText = "";
  int _secondsRemaining = 60;
  Timer? _timer;
  bool _hasError = false;
  int _attempts = 0;

  @override
  void initState() {
    super.initState();
    startTimer();
  }

  void startTimer() {
    setState(() {
      _secondsRemaining = 60;
    });
    _timer = Timer.periodic(const Duration(seconds: 1), (timer) {
      if (_secondsRemaining > 0) {
        setState(() {
          _secondsRemaining--;
        });
      } else {
        _timer?.cancel();
      }
    });
  }

  @override
  void dispose() {
    _timer?.cancel();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: SingleChildScrollView(
        child: SizedBox(
          height: MediaQuery.of(context).size.height,
          child: Column(
            children: [
              Expanded(
                flex: 3,
                child: Container(
                  width: double.infinity,
                  decoration: const BoxDecoration(
                    gradient: AppTheme.primaryGradient,
                  ),
                  child: SafeArea(
                    child: Center(
                      child: IconButton(
                        icon: const Icon(Icons.arrow_back, color: Colors.white, size: 30),
                        onPressed: () => context.pop(),
                      ),
                    ),
                  ),
                ),
              ),
              Expanded(
                flex: 7,
                child: Container(
                  width: double.infinity,
                  padding: const EdgeInsets.all(AppDimensions.xl),
                  decoration: const BoxDecoration(
                    color: AppTheme.surfaceVariant,
                    borderRadius: BorderRadius.only(
                      topLeft: Radius.circular(AppDimensions.radiusXl),
                      topRight: Radius.circular(AppDimensions.radiusXl),
                    ),
                  ),
                  child: BlocConsumer<AuthBloc, AuthState>(
                    listener: (context, state) {
                      if (state is AuthSuccess) {
                        context.go('/home');
                      } else if (state is AuthError) {
                        setState(() => _hasError = true);
                        ScaffoldMessenger.of(context).showSnackBar(
                          SnackBar(content: Text(state.message), backgroundColor: AppTheme.error),
                        );
                      }
                    },
                    builder: (context, state) {
                      return Column(
                        crossAxisAlignment: CrossAxisAlignment.stretch,
                        children: [
                          Text(
                            'Enter the code sent to your WhatsApp',
                            style: Theme.of(context).textTheme.titleLarge,
                            textAlign: TextAlign.center,
                          ),
                          const SizedBox(height: AppDimensions.sm),
                          Text(
                            '+249 ${widget.phone}',
                            style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                              color: AppTheme.primary,
                              fontWeight: FontWeight.bold,
                            ),
                            textAlign: TextAlign.center,
                            textDirection: TextDirection.ltr,
                          ),
                          const SizedBox(height: AppDimensions.xxl),
                          PinCodeTextField(
                            appContext: context,
                            length: 6,
                            obscureText: false,
                            animationType: AnimationType.fade,
                            pinTheme: PinTheme(
                              shape: PinCodeFieldShape.box,
                              borderRadius: BorderRadius.circular(AppDimensions.radiusSm),
                              fieldHeight: 50,
                              fieldWidth: 40,
                              activeFillColor: AppTheme.surfaceVariant,
                              inactiveFillColor: AppTheme.surfaceVariant,
                              selectedFillColor: AppTheme.primary.withOpacity(0.15),
                              activeColor: _hasError ? AppTheme.error : AppTheme.primary,
                              inactiveColor: _hasError ? AppTheme.error : AppTheme.divider,
                              selectedColor: _hasError ? AppTheme.error : AppTheme.primary,
                            ),
                            animationDuration: const Duration(milliseconds: 300),
                            backgroundColor: Colors.transparent,
                            enableActiveFill: true,
                            keyboardType: TextInputType.number,
                            onChanged: (value) {
                              setState(() {
                                currentText = value;
                                _hasError = false;
                              });
                            },
                            onCompleted: (v) {
                              if (_attempts >= 3) {
                                ScaffoldMessenger.of(context).showSnackBar(
                                  const SnackBar(content: Text('تم تجاوز عدد المحاولات المسموحة. يرجى إعادة المحاولة لاحقاً.'), backgroundColor: AppTheme.error),
                                );
                                return;
                              }
                              _attempts++;
                              context.read<AuthBloc>().add(
                                VerifyWhatsAppOtpEvent(widget.phone, v)
                              );
                            },
                          ).animate(target: _hasError ? 1 : 0).shake(duration: const Duration(milliseconds: 400)),
                          const SizedBox(height: AppDimensions.xl),
                          DsButton(
                            label: 'Verify',
                            isLoading: state is AuthLoading,
                            onPressed: currentText.length == 6
                                ? () {
                                    if (_attempts >= 3) {
                                      ScaffoldMessenger.of(context).showSnackBar(
                                        const SnackBar(content: Text('تم تجاوز عدد المحاولات المسموحة.'), backgroundColor: AppTheme.error),
                                      );
                                      return;
                                    }
                                    _attempts++;
                                    context.read<AuthBloc>().add(
                                      VerifyWhatsAppOtpEvent(widget.phone, currentText)
                                    );
                                  }
                                : null,
                          ),
                          const SizedBox(height: AppDimensions.lg),
                          TextButton(
                            onPressed: _secondsRemaining == 0
                                ? () {
                                    startTimer();
                                    context.read<AuthBloc>().add(
                                      RequestWhatsAppOtpEvent(widget.phone)
                                    );
                                  }
                                : null,
                            child: Text(
                              _secondsRemaining > 0 
                                  ? 'Resend in ${_secondsRemaining}s'
                                  : 'Resend Code',
                              style: TextStyle(
                                color: _secondsRemaining > 0 
                                    ? AppTheme.textSecondary 
                                    : AppTheme.primary,
                                fontWeight: FontWeight.bold,
                              ),
                            ),
                          ),
                        ],
                      );
                    },
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
