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
import '../../features/profile/presentation/screens/settings_screen.dart';
import '../../features/profile/presentation/screens/wallet_screen.dart';
import '../../features/product/presentation/screens/product_detail_screen.dart';
import '../../features/checkout/presentation/screens/order_success_screen.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import '../di/injection.dart';
import '../../features/auth/presentation/bloc/auth_bloc.dart';
import '../../features/auth/presentation/bloc/auth_state_event.dart';
import '../../features/product/presentation/bloc/product_cubit.dart';
import '../../features/home/presentation/bloc/home_cubit.dart';
import 'package:hive_flutter/hive_flutter.dart';
import '../theme/app_theme.dart';
import '../../features/cart/presentation/screens/cart_screen.dart';
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
    context.read<AuthBloc>().add(CheckAuthStatusEvent());
    
    Future.delayed(const Duration(milliseconds: 3000), () {
      if (mounted) {
        final isFirstTime = Hive.box('settings').get('isFirstTime', defaultValue: true);
        if (isFirstTime) {
          context.go('/onboarding');
          return;
        }

        final authState = context.read<AuthBloc>().state;
        if (authState is AuthSuccess) {
          context.go('/home');
        } else {
          context.go('/login');
        }
      }
    });
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Theme.of(context).scaffoldBackgroundColor, // Adaptive background
      body: Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Image.asset(
              'assets/images/drsudani.png',
              width: 220, 
              errorBuilder: (context, error, stackTrace) => 
                const Icon(Icons.gamepad, size: 100, color: AppTheme.primary),
            )
            .animate()
            .scale(duration: 1000.ms, curve: Curves.easeOutBack)
            .fadeIn(duration: 1000.ms),
            
            const SizedBox(height: 24),
            
            Text(
              'دكتور الألعاب في السودان',
              style: GoogleFonts.cairo(
                fontSize: 22,
                fontWeight: FontWeight.w900,
                color: AppTheme.primary,
                letterSpacing: 1.0,
              ),
            )
            .animate()
            .slideY(begin: 1.5, end: 0, duration: 900.ms, delay: 600.ms, curve: Curves.easeOutCubic)
            .fadeIn(duration: 900.ms, delay: 600.ms),
          ],
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
    return Scaffold(
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
                  _NavBarItem(
                    icon: CupertinoIcons.bag,
                    label: AppLocalizations.of(context)!.shop,
                    isSelected: navigationShell.currentIndex == 1,
                    onTap: () => _goBranch(1),
                  ),
                  _NavBarItem(
                    icon: CupertinoIcons.doc_text,
                    label: AppLocalizations.of(context)!.orders,
                    isSelected: navigationShell.currentIndex == 2,
                    onTap: () => _goBranch(2),
                  ),
                  _NavBarItem(
                    icon: CupertinoIcons.settings,
                    label: AppLocalizations.of(context)!.settings,
                    isSelected: navigationShell.currentIndex == 3,
                    onTap: () => _goBranch(3),
                  ),
                ],
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
      onTap: onTap,
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
  static CustomTransitionPage _buildPageWithTransition({
    required BuildContext context, 
    required GoRouterState state, 
    required Widget child,
  }) {
    return CustomTransitionPage(
      key: state.pageKey,
      child: child,
      transitionsBuilder: (context, animation, secondaryAnimation, child) {
        return SlideTransition(
          position: Tween<Offset>(
            begin: const Offset(1, 0),
            end: Offset.zero,
          ).animate(CurvedAnimation(
            parent: animation,
            curve: Curves.easeOutCubic,
          )),
          child: child,
        );
      },
    );
  }

  static final router = GoRouter(
    initialLocation: '/splash',
    routes: [
      GoRoute(
        path: '/splash',
        builder: (context, state) => const SplashScreen(),
      ),
      GoRoute(
        path: '/cart',
        pageBuilder: (context, state) => _buildPageWithTransition(context: context, state: state, child: const CartScreen()),
      ),
      GoRoute(
        path: '/checkout',
        pageBuilder: (context, state) => _buildPageWithTransition(context: context, state: state, child: const CheckoutScreen()),
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
        path: '/category/:id/:name',
        pageBuilder: (context, state) {
          final id = int.tryParse(state.pathParameters['id'] ?? '0') ?? 0;
          final name = state.pathParameters['name'] ?? '';
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
                path: 'order-success/:id',
                builder: (context, state) {
                  final orderId = state.pathParameters['id'] ?? '';
                  return OrderSuccessScreen(orderId: orderId);
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
