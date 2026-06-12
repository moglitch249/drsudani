import 'dart:async';
import 'package:flutter/material.dart';
import 'package:flutter/cupertino.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';
import 'package:flutter_animate/flutter_animate.dart';
import '../../../../core/l10n/app_localizations.dart';
import '../../../../core/theme/app_theme.dart';
import '../../../../core/theme/app_dimensions.dart';
import '../../../../core/widgets/ds_app_bar.dart';
import '../../../../core/utils/price_formatter.dart';
import '../../../auth/presentation/bloc/auth_bloc.dart';
import '../../../auth/presentation/bloc/auth_state_event.dart';
import '../bloc/wallet_cubit.dart';

class ProfileScreen extends StatefulWidget {
  const ProfileScreen({Key? key}) : super(key: key);

  @override
  State<ProfileScreen> createState() => _ProfileScreenState();
}

class _ProfileScreenState extends State<ProfileScreen> {
  late Timer _timer;
  String _greetingKey = 'goodEvening';

  @override
  void initState() {
    super.initState();
    _updateGreeting();
    _timer = Timer.periodic(const Duration(minutes: 1), (_) => _updateGreeting());
  }

  @override
  void dispose() {
    _timer.cancel();
    super.dispose();
  }

  void _updateGreeting() {
    final hour = DateTime.now().hour;
    final newGreeting = (hour >= 5 && hour < 12) ? 'goodMorning' : 'goodEvening';
    if (newGreeting != _greetingKey) {
      if (mounted) setState(() => _greetingKey = newGreeting);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context)!;
    final greetingText = _greetingKey == 'goodMorning' ? (l10n.locale.languageCode == 'ar' ? 'صباح الخير' : 'Good Morning') : l10n.goodEvening;
    return Scaffold(
      backgroundColor: Theme.of(context).scaffoldBackgroundColor,
      body: SafeArea(
        child: CustomScrollView(
          slivers: [
            DsAppBar(title: l10n.profile),
            SliverList(
              delegate: SliverChildListDelegate([
                Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    Container(
                      padding: const EdgeInsets.only(top: AppDimensions.xl, bottom: AppDimensions.xl),
                      child: Padding(
                        padding: const EdgeInsets.symmetric(horizontal: AppDimensions.lg),
                        child: Row(
                          mainAxisAlignment: MainAxisAlignment.spaceBetween,
                          children: [
                            Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Container(
                                  padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 4),
                                  decoration: BoxDecoration(
                                    color: Theme.of(context).primaryColor.withOpacity(0.1),
                                    borderRadius: BorderRadius.circular(20),
                                  ),
                                  child: Row(
                                    children: [
                                      Container(
                                        width: 8,
                                        height: 8,
                                        decoration: const BoxDecoration(
                                          color: AppTheme.success,
                                          shape: BoxShape.circle,
                                        ),
                                      ).animate(onPlay: (controller) => controller.repeat(reverse: true))
                                       .fade(begin: 0.3, end: 1.0, duration: 1.seconds),
                                      const SizedBox(width: 6),
                                      Text(
                                        l10n.activeAccount,
                                        style: const TextStyle(
                                          fontSize: 12,
                                          color: AppTheme.success,
                                          fontWeight: FontWeight.w600,
                                        ),
                                      ),
                                    ],
                                  ),
                                ),
                                const SizedBox(height: 8),
                                Text(
                                  greetingText,
                                  style: TextStyle(
                                    fontSize: 16,
                                    color: Theme.of(context).colorScheme.onSurface.withOpacity(0.6),
                                  ),
                                ),
                                const SizedBox(height: 4),
                                BlocBuilder<AuthBloc, AuthState>(
                                  builder: (context, state) {
                                    String name = 'مستخدم';
                                    if (state is AuthSuccess) {
                                      name = state.user.firstName.isNotEmpty ? state.user.firstName : state.user.email.split('@')[0];
                                    }
                                    return Text(
                                      name,
                                      style: TextStyle(
                                        fontSize: 28,
                                        fontWeight: FontWeight.bold,
                                        color: Theme.of(context).colorScheme.onSurface,
                                        letterSpacing: -0.5,
                                      ),
                                    );
                                  },
                                ),
                              ],
                            ),
                            Hero(
                              tag: 'profile_avatar',
                              child: Container(
                                decoration: BoxDecoration(
                                  shape: BoxShape.circle,
                                  boxShadow: [
                                    BoxShadow(
                                      color: AppTheme.primary.withOpacity(0.2),
                                      blurRadius: 20,
                                      offset: const Offset(0, 10),
                                    ),
                                  ],
                                ),
                                child: CircleAvatar(
                                  radius: 35,
                                  backgroundColor: AppTheme.primary.withOpacity(0.1),
                                  child: Icon(CupertinoIcons.person_solid, size: 35, color: AppTheme.primary),
                                ),
                              ),
                            ),
                          ],
                        ),
                      ),
                    ),
                    Padding(
                      padding: const EdgeInsets.symmetric(horizontal: AppDimensions.lg),
                      child: _buildWalletCard(context, l10n),
                    ),
                    const SizedBox(height: AppDimensions.xl),
                    Padding(
                      padding: const EdgeInsets.symmetric(horizontal: AppDimensions.lg),
                      child: Text(
                        l10n.locale.languageCode == 'ar' ? 'إجراءات سريعة' : 'Quick Actions',
                        style: TextStyle(
                          fontSize: 18,
                          fontWeight: FontWeight.bold,
                          color: Theme.of(context).colorScheme.onSurface,
                        ),
                      ),
                    ),
                    const SizedBox(height: AppDimensions.md),
                    Padding(
                      padding: const EdgeInsets.symmetric(horizontal: AppDimensions.lg),
                      child: GridView.count(
                        shrinkWrap: true,
                        physics: const NeverScrollableScrollPhysics(),
                        crossAxisCount: 2,
                        mainAxisSpacing: AppDimensions.md,
                        crossAxisSpacing: AppDimensions.md,
                        childAspectRatio: 1.1,
                        children: [
                          _buildGridCard(
                            context,
                            icon: Icons.account_balance_wallet_outlined,
                            title: l10n.locale.languageCode == 'ar' ? 'المحفظة' : 'Wallet',
                            subtitle: l10n.locale.languageCode == 'ar' ? 'إدارة المحفظة' : 'Manage Wallet',
                            iconColor: const Color(0xFF0984E3),
                            onTap: () => context.push('/wallet'),
                          ),
                          _buildGridCard(
                            context,
                            icon: Icons.shopping_bag_outlined,
                            title: l10n.myOrders,
                            subtitle: l10n.trackOrders,
                            iconColor: const Color(0xFF6C5CE7),
                            onTap: () => context.go('/orders'),
                          ),
                          _buildGridCard(
                            context,
                            icon: Icons.person_outline,
                            title: l10n.editProfile,
                            subtitle: l10n.editProfileSubtitle,
                            iconColor: const Color(0xFF00B894),
                            onTap: () {},
                          ),
                          _buildGridCard(
                            context,
                            icon: Icons.logout,
                            title: l10n.logout,
                            subtitle: l10n.logoutSubtitle,
                            iconColor: const Color(0xFFD63031),
                            onTap: () {
                              context.read<AuthBloc>().add(LogoutEvent());
                              context.go('/login');
                            },
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(height: AppDimensions.xxl),
                  ],
                ),
              ]),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildWalletCard(BuildContext context, AppLocalizations l10n) {
    return Container(
      width: double.infinity,
      height: 200,
      padding: const EdgeInsets.all(AppDimensions.xl),
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(AppDimensions.radiusXl),
        gradient: const LinearGradient(
          colors: [Color(0xFF8C7AE6), Color(0xFF6C5CE7)],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
        boxShadow: [
          BoxShadow(
            color: const Color(0xFF6C5CE7).withOpacity(0.4),
            blurRadius: 20,
            offset: const Offset(0, 10),
          )
        ],
      ),
      child: BlocBuilder<WalletCubit, WalletState>(
        builder: (context, state) {
          double balance = 0.0;
          bool hasError = false;
          if (state is WalletLoaded) {
            balance = state.balance;
          } else if (state is WalletError) {
            hasError = true;
          }
          
          String userName = 'User';
          final authState = context.read<AuthBloc>().state;
          if (authState is AuthSuccess) {
            userName = authState.user.firstName;
          }

          return Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 6),
                    decoration: BoxDecoration(
                      color: Colors.white.withOpacity(0.2),
                      borderRadius: BorderRadius.circular(20),
                    ),
                    child: Text(l10n.myWallet, style: const TextStyle(color: Colors.white)),
                  ),
                  Row(
                    children: [
                      Text('${l10n.welcome}،\n$userName', style: const TextStyle(color: Colors.white, fontSize: 12), textAlign: TextAlign.right),
                      const SizedBox(width: 8),
                      Container(
                        width: 40, height: 40,
                        decoration: BoxDecoration(
                          shape: BoxShape.circle,
                          border: Border.all(color: Colors.white, width: 2),
                        ),
                        child: const Center(child: Icon(Icons.person, color: Colors.white)),
                      ),
                    ],
                  )
                ],
              ),
              const Spacer(),
              Text(l10n.walletBalance, style: const TextStyle(color: Colors.white70, fontSize: 14)),
              const SizedBox(height: 4),
              Row(
                crossAxisAlignment: CrossAxisAlignment.baseline,
                textBaseline: TextBaseline.alphabetic,
                children: [
                  Text(state is WalletLoading ? '...' : (hasError ? '--' : PriceFormatter.format(balance, withDecimals: true)), style: const TextStyle(color: Colors.white, fontSize: 36, fontWeight: FontWeight.bold)),
                  const SizedBox(width: 8),
                  Text(l10n.locale.languageCode == 'ar' ? 'ج.س' : 'SDG', style: const TextStyle(color: Colors.white, fontSize: 18, fontWeight: FontWeight.bold)),
                ],
              ),
              const SizedBox(height: 8),
              Row(
                children: List.generate(4, (index) => Container(
                  margin: const EdgeInsets.only(right: 4),
                  width: 6, height: 6,
                  decoration: BoxDecoration(
                    color: Colors.white.withOpacity(index == 0 ? 1 : 0.4),
                    shape: BoxShape.circle,
                  ),
                )),
              ),
            ],
          );
        },
      ),
    );
  }

  Widget _buildGridCard(
    BuildContext context, {
    required IconData icon,
    required String title,
    required String subtitle,
    required Color iconColor,
    required VoidCallback onTap,
  }) {
    return GestureDetector(
      onTap: onTap,
      child: Container(
        decoration: BoxDecoration(
          color: Theme.of(context).cardColor,
          borderRadius: BorderRadius.circular(AppDimensions.radiusLg),
          boxShadow: [
            BoxShadow(
              color: Colors.black.withOpacity(0.05),
              blurRadius: 10,
              offset: const Offset(0, 4),
            )
          ],
        ),
        padding: const EdgeInsets.all(AppDimensions.md),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Container(
              padding: const EdgeInsets.all(10),
              decoration: BoxDecoration(
                color: iconColor.withOpacity(0.15),
                borderRadius: BorderRadius.circular(12),
              ),
              child: Icon(icon, color: iconColor, size: 28),
            ),
            const Spacer(),
            Text(
              title,
              style: TextStyle(
                color: Theme.of(context).colorScheme.onSurface,
                fontSize: 16,
                fontWeight: FontWeight.bold,
              ),
            ),
            const SizedBox(height: 4),
            Text(
              subtitle,
              style: TextStyle(
                color: Theme.of(context).textTheme.bodySmall?.color ?? Colors.grey,
                fontSize: 12,
              ),
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
            ),
          ],
        ),
      ),
    );
  }
}
