import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:local_auth/local_auth.dart';
import 'package:flutter_jailbreak_detection/flutter_jailbreak_detection.dart';
import 'package:flutter/services.dart';
import 'package:hive_flutter/hive_flutter.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'core/l10n/app_localizations.dart';
import 'core/network/api_service.dart';

import 'core/router/app_router.dart';
import 'core/theme/app_theme.dart';
import 'core/utils/locale_cubit.dart';
import 'core/utils/theme_cubit.dart';
import 'core/di/injection.dart';
import 'features/auth/presentation/bloc/auth_bloc.dart';
import 'features/auth/presentation/bloc/auth_state_event.dart';
import 'features/home/presentation/bloc/home_cubit.dart';
import 'features/product/presentation/bloc/product_cubit.dart';
import 'features/cart/presentation/bloc/cart_cubit.dart';
import 'features/profile/presentation/bloc/wallet_cubit.dart';
import 'features/notifications/presentation/bloc/notifications_cubit.dart';
import 'features/auth/data/datasources/auth_local_data_source.dart';

import 'dart:convert';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

void main() async {
  WidgetsFlutterBinding.ensureInitialized();

  configureDependencies();

  // Initialize Hive
  await Hive.initFlutter();
  
  // === 1. إعداد مفتاح التشفير (Encryption Key) ===
  const secureStorage = FlutterSecureStorage();
  final containsEncryptionKey = await secureStorage.containsKey(key: 'ds_hive_key');
  if (!containsEncryptionKey) {
    final key = Hive.generateSecureKey();
    await secureStorage.write(key: 'ds_hive_key', value: base64UrlEncode(key));
  }
  
  final encryptionKeyString = await secureStorage.read(key: 'ds_hive_key');
  final encryptionKeyUint8List = base64Url.decode(encryptionKeyString!);
  final cipher = HiveAesCipher(encryptionKeyUint8List);

  // === 2. فتح الصناديق بتشفير قوي ===
  await Hive.openBox('settings'); // إعدادات التطبيق لا تحتاج تشفير قوي
  await Hive.openBox('notificationsBox');
  
  try {
    await Hive.openBox('auth', encryptionCipher: cipher);
  } catch (e) {
    // إذا كانت البيانات القديمة غير مشفرة، نحذفها وننشئها مشفرة (سيحتاج المستخدم لتسجيل الدخول مجدداً)
    await Hive.deleteBoxFromDisk('auth');
    await Hive.openBox('auth', encryptionCipher: cipher);
  }

  try {
    await Hive.openBox('cart', encryptionCipher: cipher);
  } catch (e) {
    await Hive.deleteBoxFromDisk('cart');
    await Hive.openBox('cart', encryptionCipher: cipher);
  }

  // === cartBox (بيانات السلة بما فيها الـ Player ID - يجب تشفيرها) ===
  try {
    await Hive.openBox('cartBox', encryptionCipher: cipher);
  } catch (e) {
    await Hive.deleteBoxFromDisk('cartBox');
    await Hive.openBox('cartBox', encryptionCipher: cipher);
  }

  runApp(const MyApp());
}



// === مدير دورة حياة التطبيق وحماية الشاشة والأجهزة المروّتة ===
class AppLifecycleManager extends StatefulWidget {
  final Widget child;
  const AppLifecycleManager({Key? key, required this.child}) : super(key: key);

  @override
  _AppLifecycleManagerState createState() => _AppLifecycleManagerState();
}

class _AppLifecycleManagerState extends State<AppLifecycleManager> with WidgetsBindingObserver {
  bool _isLocked = false;
  bool _isJailbroken = false;
  DateTime? _backgroundTime;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    _secureScreen();
    _checkJailbreak();
  }

  Future<void> _checkJailbreak() async {
    try {
      bool jailbroken = await FlutterJailbreakDetection.jailbroken;
      
      // We removed developerMode check because many normal users have Developer Options enabled
      // which causes a false positive "Root Detected" error.
      
      if (jailbroken) {
        setState(() => _isJailbroken = true);
      }
    } on PlatformException catch (e) {
      debugPrint('Jailbreak detection failed: $e');
    }
  }

  Future<void> _secureScreen() async {
    // منع أخذ لقطات شاشة (Screenshot Prevention)
    // Now handled natively in MainActivity.kt
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    super.dispose();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.paused) {
      _backgroundTime = DateTime.now();
      setState(() => _isLocked = true);
    } else if (state == AppLifecycleState.resumed) {
      if (_backgroundTime != null) {
        final diff = DateTime.now().difference(_backgroundTime!);
        if (diff.inMinutes >= 1) {
          // بعد دقيقة من الخمول سيتم قفل التطبيق (Background Lock)
          setState(() => _isLocked = true);
        } else {
          setState(() => _isLocked = false);
        }
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_isJailbroken) {
      return const MaterialApp(
        debugShowCheckedModeBanner: false,
        home: Scaffold(
          backgroundColor: Colors.red,
          body: Center(
            child: Padding(
              padding: EdgeInsets.all(20.0),
              child: Column(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  Icon(Icons.gpp_bad, size: 80, color: Colors.white),
                  SizedBox(height: 20),
                  Text(
                    'تم إيقاف التطبيق لأسباب أمنية',
                    style: TextStyle(color: Colors.white, fontSize: 24, fontWeight: FontWeight.bold),
                  ),
                  SizedBox(height: 10),
                  Text(
                    'لا يمكن تشغيل التطبيق على أجهزة تحتوي على Root أو Jailbreak لحماية بياناتك المالية.',
                    textAlign: TextAlign.center,
                    style: TextStyle(color: Colors.white, fontSize: 16),
                  ),
                ],
              ),
            ),
          ),
        ),
      );
    }

    return Stack(
      children: [
        widget.child,
        if (_isLocked)
          Material(
            color: Colors.black87,
            child: Center(
              child: Column(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  const Icon(Icons.lock_outline, size: 80, color: Colors.white),
                  const SizedBox(height: 20),
                  const Text('تم قفل التطبيق لحماية بياناتك', style: TextStyle(color: Colors.white, fontSize: 18)),
                  const SizedBox(height: 20),
                  ElevatedButton(
                    onPressed: () async {
                      final LocalAuthentication auth = LocalAuthentication();
                      final bool canAuthenticateWithBiometrics = await auth.canCheckBiometrics;
                      final bool canAuthenticate = canAuthenticateWithBiometrics || await auth.isDeviceSupported();
                      
                      if (canAuthenticate) {
                        try {
                          final bool didAuthenticate = await auth.authenticate(
                            localizedReason: 'يرجى التحقق من هويتك لفتح التطبيق',
                            options: const AuthenticationOptions(
                              biometricOnly: false,
                              stickyAuth: true,
                            ),
                          );
                          if (didAuthenticate) {
                            setState(() => _isLocked = false);
                          }
                        } catch (e) {
                          // Fallback to unlock if error happens
                          setState(() => _isLocked = false);
                        }
                      } else {
                        // If no biometrics, just unlock
                        setState(() => _isLocked = false);
                      }
                    },
                    style: ElevatedButton.styleFrom(backgroundColor: const Color(0xFF6366F1)),
                    child: const Text('فتح التطبيق', style: TextStyle(color: Colors.white)),
                  )
                ],
              ),
            ),
          )
      ],
    );
  }
}

class MyApp extends StatelessWidget {
  const MyApp({Key? key}) : super(key: key);

  @override
  Widget build(BuildContext context) {
    return MultiBlocProvider(
      providers: [
        BlocProvider<LocaleCubit>(create: (_) => LocaleCubit()..loadSavedLocale()),
        BlocProvider<ThemeCubit>(create: (_) => ThemeCubit()..loadSavedTheme()),
        BlocProvider<AuthBloc>(create: (_) => getIt<AuthBloc>()),
        BlocProvider<ProductCubit>(create: (_) => getIt<ProductCubit>()),
        BlocProvider<CartCubit>(create: (_) => CartCubit()),
        BlocProvider<WalletCubit>(create: (_) => WalletCubit(getIt<ApiService>())),
        BlocProvider<NotificationsCubit>(create: (_) {
          final localDs = getIt<AuthLocalDataSource>();
          final customerId = localDs.getUserId() ?? 0;
          return NotificationsCubit(
            apiService: getIt<ApiService>(),
            customerId: customerId,
          )..loadNotifications();
        }),
      ],
      child: BlocListener<AuthBloc, AuthState>(
        listener: (context, authState) {
          if (authState is AuthSuccess) {
            context.read<WalletCubit>().fetchWalletData(authToken: authState.user.token);
            context.read<NotificationsCubit>().checkForUpdates();
          } else if (authState is AuthInitial || authState is AuthError) {
            context.read<CartCubit>().clearCart();
          }
        },
        child: BlocBuilder<LocaleCubit, Locale>(
          builder: (context, locale) {
            return BlocBuilder<ThemeCubit, ThemeMode>(
              builder: (context, themeMode) {
                return AppLifecycleManager(
                  child: MaterialApp.router(
                    title: 'Dr. Sudani',
                    debugShowCheckedModeBanner: false,
                    themeMode: themeMode,
                    theme: AppTheme.getLightTheme(locale.languageCode),
                    darkTheme: AppTheme.getDarkTheme(locale.languageCode),
                    locale: locale,
                    supportedLocales: const [
                      Locale('en'),
                      Locale('ar'),
                    ],
                    localizationsDelegates: const [
                      AppLocalizations.delegate,
                      GlobalMaterialLocalizations.delegate,
                      GlobalWidgetsLocalizations.delegate,
                      GlobalCupertinoLocalizations.delegate,
                    ],
                    routerConfig: AppRouter.router,
                    builder: (context, child) {
                      return Directionality(
                        textDirection: locale.languageCode == 'ar' ? TextDirection.rtl : TextDirection.ltr,
                        child: child!,
                      );
                    },
                  ),
                );
              },
            );
          },
        ),
      ),
    );
  }
}

