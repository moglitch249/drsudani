import 'dart:ui';
import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:flutter_animate/flutter_animate.dart';
import 'package:flutter/cupertino.dart';
import 'package:google_fonts/google_fonts.dart';
import '../l10n/app_localizations.dart';

import '../../features/home/presentation/screens/home_screen.dart';
import '../../features/shop/presentation/screens/shop_screen.dart';
import '../../features/shop/presentation/screens/category_screen.dart';
import '../../features/orders/presentation/screens/orders_screen.dart';
import '../../features/auth/presentation/screens/login_screen.dart';
import '../../features/auth/presentation/screens/onboarding_screen.dart';
import '../../features/auth/presentation/screens/otp_screen.dart';
import '../../features/auth/presentation/screens/register_screen.dart';
import '../../features/profile/presentation/screens/profile_screen.dart';
import '../../features/checkout/data/models/checkout_item_model.dart';
import '../../features/profile/presentation/screens/settings_screen.dart';
import '../../features/profile/presentation/screens/wallet_screen.dart';
import '../../features/product/presentation/screens/product_detail_screen.dart';
import '../../features/checkout/presentation/screens/order_success_screen.dart';
import '../../features/orders/presentation/screens/order_detail_screen.dart';
import '../../features/orders/data/models/order_model.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import '../di/injection.dart';
import '../../features/auth/presentation/bloc/auth_bloc.dart';
import '../../features/auth/presentation/bloc/auth_state_event.dart';
import '../../features/product/presentation/bloc/product_cubit.dart';
import '../../features/home/presentation/bloc/home_cubit.dart';
import 'package:showcaseview/showcaseview.dart';
import '../utils/tour_keys.dart';
import 'package:flutter/services.dart';
import 'package:hive_flutter/hive_flutter.dart';
import '../theme/app_theme.dart';
import '../../features/checkout/presentation/screens/checkout_screen.dart';
import '../../features/notifications/presentation/screens/notifications_screen.dart';

// Splash Screen
class SplashScreen extends StatefulWidget {
  const SplashScreen({Key? key}) : super(key: key);

  @override
  State<SplashScreen> createState() => _SplashScreenState();
}

class _SplashScreenState extends State<SplashScreen> {
  @override
  void initState() {
    super.initState();
    // تأخير بسيط لعرض الأنيميشن
    Future.delayed(const Duration(milliseconds: 1500), () {
      if (mounted) {
        context.read<AuthBloc>().add(CheckAuthStatusEvent());
      }
    });
  }

  @override
  Widget build(BuildContext context) {
    return BlocListener<AuthBloc, AuthState>(
      listener: (context, state) {
        if (state is AuthInitial || state is AuthLoading) return;
        
        final isFirstTime = Hive.box('settings').get('isFirstTime', defaultValue: true);
        if (isFirstTime) {
          context.go('/onboarding');
        } else if (state is AuthSuccess) {
          context.go('/home');
        } else {
          // حالة AuthUnauthenticated أو Error
          context.go('/login');
        }
      },
      child: Scaffold(
      backgroundColor: Theme.of(context).scaffoldBackgroundColor,
      body: Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Image.asset(
              'assets/images/drsudani.png',
              width: 280,
              errorBuilder: (context, error, stackTrace) => 
                const Icon(Icons.gamepad, size: 100, color: AppTheme.primary),
            )
            .animate(onPlay: (controller) => controller.repeat(reverse: true))
            .fade(begin: 0.3, end: 1.0, duration: 800.ms, curve: Curves.easeInOut)
            .scale(begin: const Offset(0.95, 0.95), end: const Offset(1.0, 1.0), duration: 800.ms),
          ],
        ),
      ),
      ),
    );
  }
}

// Scaffold with Navigation Bar (Bottom Tabs)
class ScaffoldWithNavBar extends StatelessWidget {
  final StatefulNavigationShell navigationShell;

  const ScaffoldWithNavBar({
    Key? key,
    required this.navigationShell,
  }) : super(key: key);

  void _goBranch(int index) {
    navigationShell.goBranch(
      index,
      initialLocation: index == navigationShell.currentIndex,
    );
  }

  @override
  Widget build(BuildContext context) {
    return ShowCaseWidget(
      builder: (context) => Scaffold(
          extendBody: true,
          body: navigationShell,
          bottomNavigationBar: Padding(
            padding: const EdgeInsets.only(left: 24, right: 24, bottom: 24),
            child: ClipRRect(
              borderRadius: BorderRadius.circular(30),
              child: BackdropFilter(
                filter: ImageFilter.blur(sigmaX: 15, sigmaY: 15),
                child: Container(
                  height: 70,
                  decoration: BoxDecoration(
                    color: Theme.of(context).cardColor.withOpacity(0.6),
                    border: Border.all(color: Colors.white.withOpacity(0.1), width: 1),
                    borderRadius: BorderRadius.circular(30),
                    boxShadow: [
                      BoxShadow(color: Colors.black.withOpacity(0.2), blurRadius: 20, offset: const Offset(0, 10)),
                    ],
                  ),
                  child: Row(
                    mainAxisAlignment: MainAxisAlignment.spaceEvenly,
                    children: [
                      _NavBarItem(
                        icon: CupertinoIcons.home,
                        label: AppLocalizations.of(context)!.home,
                        isSelected: navigationShell.currentIndex == 0,
                        onTap: () => _goBranch(0),
                      ),
                      Showcase(
                        key: TourKeys.shopTabKey,
                        title: 'الأقسام السريعة',
                        description: 'تصفح ألعابك وخدماتك المفضلة بسرعة من خلال هذه التصنيفات. 🛒',
                        child: _NavBarItem(
                          icon: CupertinoIcons.bag,
                          label: AppLocalizations.of(context)!.shop,
                          isSelected: navigationShell.currentIndex == 1,
                          onTap: () => _goBranch(1),
                        ),
                      ),
                      Showcase(
                        key: TourKeys.ordersTabKey,
                        title: 'زر الطلبات',
                        description: 'تابع طلباتك السابقة وتعرف على حالتها (قيد المعالجة، مكتملة) من هنا. 📦',
                        child: _NavBarItem(
                          icon: CupertinoIcons.doc_text,
                          label: AppLocalizations.of(context)!.orders,
                          isSelected: navigationShell.currentIndex == 2,
                          onTap: () => _goBranch(2),
                        ),
                      ),
                      Showcase(
                        key: TourKeys.profileTabKey,
                        title: 'زر الإعدادات والبروفايل',
                        description: 'تحكم في حسابك، قم بتغيير لغة التطبيق، أو بدّل بين الوضع المظلم والفاتح. ⚙️',
                        child: _NavBarItem(
                          icon: CupertinoIcons.settings,
                          label: AppLocalizations.of(context)!.settings,
                          isSelected: navigationShell.currentIndex == 3,
                          onTap: () => _goBranch(3),
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            ),
          ),
        ),
    );
  }
}

class _NavBarItem extends StatelessWidget {
  final IconData icon;
  final String label;
  final bool isSelected;
  final VoidCallback onTap;

  const _NavBarItem({
    required this.icon,
    required this.label,
    required this.isSelected,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    final color = isSelected ? AppTheme.primary : Theme.of(context).colorScheme.onSurface.withOpacity(0.5);
    return GestureDetector(
      onTap: () {
        HapticFeedback.selectionClick();
        onTap();
      },
      behavior: HitTestBehavior.opaque,
      child: Container(
        width: 60,
        padding: const EdgeInsets.symmetric(vertical: 8),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(icon, color: color, size: 24)
                .animate(target: isSelected ? 1 : 0)
                .scale(begin: const Offset(1, 1), end: const Offset(1.1, 1.1), duration: 200.ms)
                .tint(color: AppTheme.primary),
            const SizedBox(height: 4),
            Text(
              label,
              style: TextStyle(
                color: color,
                fontSize: 10,
                fontWeight: isSelected ? FontWeight.bold : FontWeight.normal,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class AppRouter {
  static CustomTransitionPage _buildPageWithTransition<T>({
    required BuildContext context,
    required GoRouterState state,
    required Widget child,
  }) {
    // استخدمنا FadeTransition بدلاً من SlideTransition الثقيل لتحسين الأداء وتسريع التنقل
    return CustomTransitionPage<T>(
      key: state.pageKey,
      child: child,
      transitionsBuilder: (context, animation, secondaryAnimation, child) {
        return FadeTransition(
          opacity: animation,
          child: child,
        );
      },
    );
  }

  static final router = GoRouter(
    initialLocation: '/splash',
    redirect: (context, state) {
      final protectedRoutes = ['/wallet', '/checkout', '/profile', '/settings'];
      final isProtectedRoute = protectedRoutes.contains(state.uri.path);
      
      if (isProtectedRoute) {
        final token = Hive.box('auth').get('jwtToken');
        if (token == null || token.toString().isEmpty) {
          return '/login';
        }
      }
      return null;
    },
    routes: [
      GoRoute(
        path: '/splash',
        builder: (context, state) => const SplashScreen(),
      ),
      GoRoute(
        path: '/checkout',
        builder: (context, state) {
          final item = state.extra as CheckoutItem?;
          if (item == null) {
            WidgetsBinding.instance.addPostFrameCallback((_) {
              context.go('/home');
            });
            return const Scaffold(body: Center(child: CircularProgressIndicator()));
          }
          return CheckoutScreen(item: item);
        },
      ),
      GoRoute(
        path: '/notifications',
        pageBuilder: (context, state) => _buildPageWithTransition(context: context, state: state, child: const NotificationsScreen()),
      ),
      GoRoute(
        path: '/onboarding',
        pageBuilder: (context, state) => _buildPageWithTransition(context: context, state: state, child: const OnboardingScreen()),
      ),
      GoRoute(
        path: '/login',
        pageBuilder: (context, state) => _buildPageWithTransition(context: context, state: state, child: const LoginScreen()),
      ),
      GoRoute(
        path: '/register',
        pageBuilder: (context, state) => _buildPageWithTransition(context: context, state: state, child: const RegisterScreen()),
      ),
      GoRoute(
        path: '/otp',
        pageBuilder: (context, state) {
          final phone = state.extra as String? ?? '';
          return _buildPageWithTransition(context: context, state: state, child: OtpScreen(phone: phone));
        },
      ),
      GoRoute(
        path: '/product/:id',
        pageBuilder: (context, state) {
          final id = state.pathParameters['id']!;
          return _buildPageWithTransition(context: context, state: state, child: ProductDetailScreen(productId: id));
        },
      ),
      GoRoute(
        path: '/category/:id',
        pageBuilder: (context, state) {
          final id = int.tryParse(state.pathParameters['id'] ?? '0') ?? 0;
          final name = state.uri.queryParameters['name'] ?? '';
          return _buildPageWithTransition(context: context, state: state, child: CategoryScreen(categoryId: id, categoryName: name));
        },
      ),
      // Settings moved to shell route
      GoRoute(
        path: '/profile',
        pageBuilder: (context, state) => _buildPageWithTransition(context: context, state: state, child: const ProfileScreen()),
      ),
      GoRoute(
        path: '/wallet',
        pageBuilder: (context, state) => _buildPageWithTransition(context: context, state: state, child: const WalletScreen()),
      ),
      StatefulShellRoute.indexedStack(
        builder: (context, state, navigationShell) {
          return ScaffoldWithNavBar(navigationShell: navigationShell);
        },
        branches: [
          StatefulShellBranch(
            routes: [
              GoRoute(
                path: '/home',
                builder: (context, state) => BlocProvider(
                  create: (context) => getIt<HomeCubit>()..loadHomeData(),
                  child: const HomeScreen(),
                ),
              ),
            ],
          ),
          StatefulShellBranch(
            routes: [
              GoRoute(
                path: '/shop',
                builder: (context, state) => BlocProvider(
                  create: (context) => getIt<ProductCubit>()..getProducts(),
                  child: const ShopScreen(),
                ),
              ),
            ],
          ),
          StatefulShellBranch(
            routes: [
              GoRoute(
                path: '/orders',
                builder: (context, state) => const OrdersScreen(),
              ),
              GoRoute(
                path: '/order-success/:id',
                builder: (context, state) {
                  final orderId = state.pathParameters['id'] ?? '';
                  final extra = state.extra as Map<String, dynamic>?;
                  return OrderSuccessScreen(
                    orderId: orderId,
                    extraData: extra,
                  );
                },
              ),
              GoRoute(
                path: '/order-detail',
                builder: (context, state) {
                  final order = state.extra as OrderModel;
                  return OrderDetailScreen(order: order);
                },
              ),
            ],
          ),
          StatefulShellBranch(
            routes: [
              GoRoute(
                path: '/settings',
                builder: (context, state) => const SettingsScreen(),
              ),
            ],
          ),
        ],
      ),
    ],
  );
}
