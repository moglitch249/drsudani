import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:url_launcher/url_launcher.dart';
import '../../../../core/theme/app_dimensions.dart';
import '../../../../core/theme/app_theme.dart';
import '../../../../core/l10n/app_localizations.dart';
import '../../../../core/utils/price_formatter.dart';
import '../bloc/wallet_cubit.dart';
import '../../../auth/presentation/bloc/auth_bloc.dart';
import '../../../auth/presentation/bloc/auth_state_event.dart';


class WalletScreen extends StatefulWidget {
  const WalletScreen({Key? key}) : super(key: key);

  @override
  State<WalletScreen> createState() => _WalletScreenState();
}

class _WalletScreenState extends State<WalletScreen> {
  final ScrollController _scrollController = ScrollController();
  final GlobalKey _transactionsKey = GlobalKey();

  @override
  void initState() {
    super.initState();
    _secureScreen();
    // استخرج التوكن من AuthBloc مباشرة (لا نعتمد على Hive وحده)
    WidgetsBinding.instance.addPostFrameCallback((_) {
      final authState = context.read<AuthBloc>().state;
      String? token;
      if (authState is AuthSuccess) {
        token = authState.user.token;
      }
      context.read<WalletCubit>().fetchWalletData(authToken: token);
    });
  }

  Future<void> _secureScreen() async {

  }

  @override
  void dispose() {

    _scrollController.dispose();
    super.dispose();
  }

  void _scrollToTransactions() {
    if (_transactionsKey.currentContext != null) {
      Scrollable.ensureVisible(
        _transactionsKey.currentContext!,
        duration: const Duration(milliseconds: 500),
        curve: Curves.easeInOut,
      );
    }
  }

  void _showRechargeBottomSheet(BuildContext context, AppLocalizations l10n) {
    showModalBottomSheet(
      context: context,
      backgroundColor: Colors.transparent,
      builder: (context) {
        return Container(
          padding: const EdgeInsets.all(AppDimensions.xl),
          decoration: BoxDecoration(
            color: Theme.of(context).scaffoldBackgroundColor,
            borderRadius: const BorderRadius.vertical(top: Radius.circular(AppDimensions.radiusXl)),
          ),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Container(
                width: 40,
                height: 4,
                decoration: BoxDecoration(
                  color: Colors.grey.withOpacity(0.3),
                  borderRadius: BorderRadius.circular(2),
                ),
              ),
              const SizedBox(height: AppDimensions.xl),
              Icon(Icons.account_balance_wallet, size: 60, color: AppTheme.primary),
              const SizedBox(height: AppDimensions.md),
              Text(
                l10n.locale.languageCode == 'ar' ? 'تعبئة الرصيد' : 'Recharge Wallet',
                style: const TextStyle(fontSize: 20, fontWeight: FontWeight.bold),
              ),
              const SizedBox(height: AppDimensions.md),
              Text(
                l10n.locale.languageCode == 'ar' 
                  ? 'يرجى التواصل مع خدمة العملاء عبر واتساب لتعبئة رصيدك عن طريق بنكك أو فوري.'
                  : 'Please contact customer support via WhatsApp to recharge your wallet via Bankak or Fawry.',
                textAlign: TextAlign.center,
                style: TextStyle(color: Theme.of(context).colorScheme.onSurface.withOpacity(0.7)),
              ),
              const SizedBox(height: AppDimensions.xl),
              SizedBox(
                width: double.infinity,
                child: ElevatedButton.icon(
                  onPressed: () async {
                    final String msg = l10n.locale.languageCode == 'ar' ? 'مرحباً، أرغب في تعبئة رصيد محفظتي' : 'Hello, I want to recharge my wallet';
                    // Please replace +249123456789 with your actual customer service WhatsApp number
                    final String phone = '249123456789';
                    final Uri url = Uri.parse('https://wa.me/$phone?text=${Uri.encodeComponent(msg)}');
                    try {
                      await launchUrl(url, mode: LaunchMode.externalApplication);
                    } catch (e) {
                      debugPrint('Could not launch WhatsApp: $e');
                    }
                    if (context.mounted) Navigator.pop(context);
                  },
                  style: ElevatedButton.styleFrom(
                    backgroundColor: const Color(0xFF25D366),
                    foregroundColor: Colors.white,
                    padding: const EdgeInsets.symmetric(vertical: 16),
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(AppDimensions.radiusLg)),
                  ),
                  icon: const Icon(Icons.chat),
                  label: Text(l10n.locale.languageCode == 'ar' ? 'تواصل عبر واتساب' : 'Contact on WhatsApp'),
                ),
              ),
              const SizedBox(height: AppDimensions.md),
            ],
          ),
        );
      },
    );
  }

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context)!;
    final isDark = Theme.of(context).brightness == Brightness.dark;

    return Scaffold(
      backgroundColor: Theme.of(context).scaffoldBackgroundColor,
      appBar: AppBar(
        backgroundColor: Colors.transparent,
        elevation: 0,
        title: Text(l10n.myWallet, style: const TextStyle(fontWeight: FontWeight.bold)),
        leading: IconButton(
          icon: Icon(Icons.arrow_back, color: Theme.of(context).colorScheme.onSurface),
          onPressed: () => context.pop(),
        ),
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(AppDimensions.lg),
        child: Column(
          children: [
            // The Purple Wallet Card
            Container(
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
                  String errorMsg = '';
                  if (state is WalletLoaded) {
                    balance = state.balance;
                  } else if (state is WalletLoading) {
                    // Just show 0.0 when loading initially or keep old if we had it
                  } else if (state is WalletError) {
                    hasError = true;
                    errorMsg = state.message;
                  }
                  
                  // Get user name, prefer firstName from WalletCubit
                  String displayName = 'User';
                  final walletState = context.read<WalletCubit>().state;
                  if (walletState is WalletLoaded && walletState.firstName != null && walletState.firstName!.isNotEmpty) {
                    displayName = walletState.firstName!;
                  } else {
                    final authState = context.read<AuthBloc>().state;
                    if (authState is AuthSuccess) {
                      displayName = authState.user.firstName.isNotEmpty ? authState.user.firstName : authState.user.email.split('@')[0];
                    }
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
                              Text('${l10n.welcome}،\n$displayName', style: const TextStyle(color: Colors.white, fontSize: 12), textAlign: TextAlign.right),
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
                          if (!hasError)
                            Text(l10n.locale.languageCode == 'ar' ? 'ج.س' : 'SDG', style: const TextStyle(color: Colors.white, fontSize: 18, fontWeight: FontWeight.bold)),
                        ],
                      ),
                      if (hasError) ...[
                        const SizedBox(height: 8),
                        Text(errorMsg, style: const TextStyle(color: Colors.redAccent, fontSize: 12), maxLines: 3),
                      ] else ...[
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
                    ],
                  );
                },
              ),
            ),
            
            const SizedBox(height: AppDimensions.xl),
            
            // Action Buttons
            Row(
              children: [
                Expanded(
                  child: _buildActionButton(
                    context: context,
                    icon: Icons.add_circle_outline,
                    title: l10n.locale.languageCode == 'ar' ? 'تعبئة الرصيد' : 'Recharge',
                    color: const Color(0xFF6C5CE7),
                    isPrimary: true,
                    onTap: () => _showRechargeBottomSheet(context, l10n),
                  ),
                ),
                const SizedBox(width: AppDimensions.md),
                Expanded(
                  child: _buildActionButton(
                    context: context,
                    icon: Icons.history,
                    title: l10n.locale.languageCode == 'ar' ? 'سجل المعاملات' : 'Transactions',
                    color: Theme.of(context).cardColor,
                    isPrimary: false,
                    onTap: _scrollToTransactions,
                  ),
                ),
              ],
            ),
            
            const SizedBox(height: AppDimensions.xxl),
            
            // Transactions List
            Container(key: _transactionsKey),
            BlocBuilder<WalletCubit, WalletState>(
              builder: (context, state) {
                if (state is WalletLoaded) {
                  if (state.transactions.isEmpty) {
                    return Center(
                      child: Column(
                        children: [
                          Icon(Icons.history, size: 60, color: Theme.of(context).colorScheme.onSurface.withOpacity(0.2)),
                          const SizedBox(height: AppDimensions.md),
                          Text(
                            l10n.locale.languageCode == 'ar' ? 'لا توجد معاملات حالياً' : 'No transactions currently',
                            style: TextStyle(color: Theme.of(context).colorScheme.onSurface.withOpacity(0.5), fontSize: 14),
                          ),
                        ],
                      ),
                    );
                  }
                  
                  return ListView.builder(
                    shrinkWrap: true,
                    physics: const NeverScrollableScrollPhysics(),
                    itemCount: state.transactions.length,
                    itemBuilder: (context, index) {
                      final txn = state.transactions[index];
                      final isCredit = txn.type == 'credit';
                      return _buildTransactionTile(
                        context: context,
                        title: txn.details.isNotEmpty ? txn.details : (isCredit ? (l10n.locale.languageCode == 'ar' ? 'إضافة رصيد' : 'Deposit') : (l10n.locale.languageCode == 'ar' ? 'خصم رصيد' : 'Deduction')),
                        date: txn.date,
                        amount: txn.amount,
                        isPositive: isCredit,
                        currency: l10n.locale.languageCode == 'ar' ? 'ج.س' : 'SDG',
                      );
                    },
                  );
                } else if (state is WalletLoading) {
                  return const Center(child: CircularProgressIndicator());
                } else if (state is WalletError) {
                  return Center(
                    child: Column(
                      children: [
                        Icon(Icons.error_outline, size: 50, color: AppTheme.error.withOpacity(0.5)),
                        const SizedBox(height: AppDimensions.md),
                        Text(
                          state.message,
                          style: TextStyle(color: Theme.of(context).colorScheme.onSurface.withOpacity(0.5), fontSize: 13),
                          textAlign: TextAlign.center,
                        ),
                        const SizedBox(height: AppDimensions.md),
                        TextButton.icon(
                          icon: const Icon(Icons.refresh),
                          label: Text(l10n.retry),
                          onPressed: () {
                            final authState = context.read<AuthBloc>().state;
                            String? token;
                            if (authState is AuthSuccess) {
                              token = authState.user.token;
                            }
                            context.read<WalletCubit>().fetchWalletData(authToken: token);
                          },
                        ),
                      ],
                    ),
                  );
                }
                return const SizedBox.shrink();
              },
            ),
            
            const SizedBox(height: 40),
          ],
        ),
      ),
    );
  }

  Widget _buildActionButton({required BuildContext context, required IconData icon, required String title, required Color color, required bool isPrimary, required VoidCallback onTap}) {
    return GestureDetector(
      onTap: onTap,
      child: Container(
        padding: const EdgeInsets.symmetric(vertical: 16),
        decoration: BoxDecoration(
          color: isPrimary ? color : Colors.transparent,
          borderRadius: BorderRadius.circular(AppDimensions.radiusLg),
          border: isPrimary ? null : Border.all(color: const Color(0xFF6C5CE7).withOpacity(0.5)),
        ),
        child: Row(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(icon, color: isPrimary ? Colors.white : const Color(0xFF6C5CE7), size: 20),
            const SizedBox(width: 8),
            Text(
              title,
              style: TextStyle(color: isPrimary ? Colors.white : Theme.of(context).colorScheme.onSurface, fontWeight: FontWeight.bold),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildTransactionTile({required BuildContext context, required String title, required String date, required String amount, required bool isPositive, required String currency}) {
    return Container(
      margin: const EdgeInsets.only(bottom: AppDimensions.sm),
      padding: const EdgeInsets.all(AppDimensions.md),
      decoration: BoxDecoration(
        color: Theme.of(context).cardColor,
        borderRadius: BorderRadius.circular(AppDimensions.radiusLg),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withOpacity(0.02),
            blurRadius: 5,
            offset: const Offset(0, 2),
          ),
        ],
      ),
      child: Row(
        children: [
          Container(
            padding: const EdgeInsets.all(10),
            decoration: BoxDecoration(
              color: (isPositive ? AppTheme.success : AppTheme.error).withOpacity(0.1),
              shape: BoxShape.circle,
            ),
            child: Icon(
              isPositive ? Icons.arrow_downward : Icons.arrow_upward,
              color: isPositive ? AppTheme.success : AppTheme.error,
              size: 20,
            ),
          ),
          const SizedBox(width: AppDimensions.md),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(title, style: TextStyle(color: Theme.of(context).colorScheme.onSurface, fontWeight: FontWeight.bold)),
                const SizedBox(height: 4),
                Text(date, style: TextStyle(color: AppTheme.textMuted, fontSize: 12)),
              ],
            ),
          ),
          Text(
            '${isPositive ? '+' : '-'}$amount $currency',
            style: TextStyle(
              color: isPositive ? AppTheme.success : AppTheme.error,
              fontWeight: FontWeight.bold,
              fontSize: 16,
            ),
          ),
        ],
      ),
    );
  }
}
