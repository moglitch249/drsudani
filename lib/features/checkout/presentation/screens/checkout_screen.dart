import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';
import 'package:local_auth/local_auth.dart';
import '../../../../core/theme/app_theme.dart';
import '../../../../core/theme/app_dimensions.dart';
import '../../../../core/widgets/ds_button.dart';
import '../../../../core/widgets/ds_text_field.dart';
import '../../../auth/presentation/bloc/auth_bloc.dart';
import '../../../auth/presentation/bloc/auth_state_event.dart';
import '../../../../core/network/api_service.dart';
import '../../../../core/di/injection.dart';
import '../../../profile/presentation/bloc/wallet_cubit.dart';
import '../../../../core/l10n/app_localizations.dart';

import '../../data/models/checkout_item_model.dart';

class CheckoutScreen extends StatefulWidget {
  final CheckoutItem item;
  const CheckoutScreen({Key? key, required this.item}) : super(key: key);

  @override
  State<CheckoutScreen> createState() => _CheckoutScreenState();
}

class _CheckoutScreenState extends State<CheckoutScreen> {
  final _formKey = GlobalKey<FormState>();
  final _phoneController = TextEditingController();
  final _emailController = TextEditingController();
  String _userName = 'Customer';
  bool _isLoading = false;

  @override
  void initState() {
    super.initState();
    _secureScreen();
    // Auto-fill user data if logged in
    final authState = context.read<AuthBloc>().state;
    if (authState is AuthSuccess) {
      _emailController.text = authState.user.email;
      _userName = authState.user.firstName;
    }
    context.read<WalletCubit>().fetchWalletData();
  }

  Future<void> _secureScreen() async {

  }

  @override
  void dispose() {

    _phoneController.dispose();
    _emailController.dispose();
    super.dispose();
  }

  Future<void> _createOrder(AppLocalizations l10n) async {
    if (_isLoading) return; 
    if (!_formKey.currentState!.validate()) return;

    // === الحماية الجسدية (Biometrics) للمحفظة ===
    final LocalAuthentication auth = LocalAuthentication();
    final bool canAuthenticate = await auth.canCheckBiometrics || await auth.isDeviceSupported();
    
    if (canAuthenticate) {
      try {
        final bool didAuthenticate = await auth.authenticate(
          localizedReason: 'يرجى التحقق من هويتك لتأكيد الدفع من المحفظة',
          options: const AuthenticationOptions(stickyAuth: true, biometricOnly: false),
        );
        if (!didAuthenticate) {
          if (mounted) {
            ScaffoldMessenger.of(context).showSnackBar(
              const SnackBar(content: Text('تم إلغاء الدفع'), backgroundColor: Colors.red),
            );
          }
          return;
        }
      } catch (e) {
        debugPrint('Biometrics error: $e');
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(content: Text('تعذر التحقق من الهوية. يرجى المحاولة مجدداً.'), backgroundColor: Colors.orange),
          );
        }
        return;
      }
    }

    setState(() => _isLoading = true);

    try {
      // === Input Sanitization (A03 Injection Prevention) ===
      String _sanitize(String input) {
        final stripped = input.replaceAll(RegExp(r'<[^>]*>'), '').trim();
        return stripped.length > 200 ? stripped.substring(0, 200) : stripped;
      }

      final List<Map<String, dynamic>> metaData = [];
      
      if (widget.item.playerId.isNotEmpty) {
        metaData.add({
          'key': 'Player ID',
          'value': _sanitize(widget.item.playerId),
        });
      }
      
      if (widget.item.addons != null && widget.item.addons!.isNotEmpty) {
        widget.item.addons!.forEach((key, value) {
          final safeKey = _sanitize(key.toString());
          final safeValue = _sanitize(value.toString());
          if (safeKey.isNotEmpty && safeValue.isNotEmpty) {
            metaData.add({'key': safeKey, 'value': safeValue});
          }
        });
      }

      final Map<String, dynamic> mappedItem = {
        'product_id': widget.item.productId,
        'quantity': 1,
      };

      if (widget.item.variationId != null) {
        mappedItem['variation_id'] = widget.item.variationId;
      }

      if (metaData.isNotEmpty) {
        mappedItem['meta_data'] = metaData;
      }

      final lineItems = [mappedItem];

      final orderData = {
        'line_items': lineItems,
      };

      final authState = context.read<AuthBloc>().state;
      String userId = 'guest';
      if (authState is AuthSuccess) {
        userId = authState.user.id.toString();
      }
      final idempotencyKey = '${DateTime.now().millisecondsSinceEpoch}_$userId';

      final response = await getIt<ApiService>().createSecureCheckout(
        orderData, 
        extraHeaders: {'Idempotency-Key': idempotencyKey}
      );

      if (response.statusCode == 200 && response.data['success'] == true) {
        final walletState = context.read<WalletCubit>().state;
        final double oldBalance = (walletState is WalletLoaded) ? walletState.balance : 0.0;
        final double cost = widget.item.price;
        final double newBalance = oldBalance - cost;
        final String phone = _phoneController.text;

        context.read<WalletCubit>().fetchWalletData();
        
        if (mounted) {
          context.go(
            '/order-success/${response.data["order_id"]}',
            extra: {
              'oldBalance': oldBalance,
              'newBalance': newBalance,
              'phone': phone,
            },
          );
        }
      } else {
        final msg = response.data['message'] ?? 'فشل إنشاء الطلب';
        throw Exception(msg);
      }
    } catch (e) {
      if (mounted) {
        String errorMsg = e.toString();
        // Custom check for 402
        if (errorMsg.contains('402')) {
          errorMsg = 'عفواً، رصيد المحفظة غير كافٍ. يرجى الشحن والمحاولة مرة أخرى.';
        } else {
          errorMsg = errorMsg.replaceAll('Exception: ', '');
        }
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(errorMsg), backgroundColor: AppTheme.error, duration: const Duration(seconds: 4)),
        );
      }
    } finally {
      if (mounted) setState(() => _isLoading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context)!;
    final hasAddons = widget.item.addons != null && widget.item.addons!.isNotEmpty;
    final isArabic = l10n.locale.languageCode == 'ar';
    final currency = isArabic ? 'ج.س' : 'SDG';

    return Scaffold(
      backgroundColor: Theme.of(context).scaffoldBackgroundColor,
      appBar: AppBar(
        title: Text(l10n.checkout, style: const TextStyle(fontWeight: FontWeight.bold)),
        centerTitle: true,
      ),
      body: MultiBlocListener(
        listeners: [
          BlocListener<WalletCubit, WalletState>(
            listener: (context, state) {
              if (state is WalletError) {
                ScaffoldMessenger.of(context).showSnackBar(
                  SnackBar(
                    content: Text('تعذر التحقق من رصيدك، يرجى المحاولة مرة أخرى'),
                    backgroundColor: AppTheme.error,
                    action: SnackBarAction(
                      label: 'إعادة المحاولة',
                      textColor: Colors.white,
                      onPressed: () => context.read<WalletCubit>().fetchWalletData(),
                    ),
                  ),
                );
              }
            },
          ),
        ],
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(AppDimensions.lg),
          child: Form(
            key: _formKey,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(l10n.contactInfo, style: Theme.of(context).textTheme.titleLarge),
                const SizedBox(height: AppDimensions.md),
                DsTextField(
                  controller: _phoneController,
                  label: l10n.phone,
                  hint: '09XXXXXXX',
                  keyboardType: TextInputType.phone,
                  validator: (v) => v!.isEmpty ? l10n.required : null,
                ),
                const SizedBox(height: AppDimensions.md),
                DsTextField(
                  controller: _emailController,
                  label: l10n.email,
                  hint: 'example@mail.com',
                  keyboardType: TextInputType.emailAddress,
                  validator: (v) => v!.isEmpty ? l10n.required : null,
                ),
                const SizedBox(height: AppDimensions.xl),
                
                Text(l10n.orderSummary, style: Theme.of(context).textTheme.titleLarge),
                const SizedBox(height: AppDimensions.md),
                Container(
                  padding: const EdgeInsets.all(AppDimensions.lg),
                  decoration: BoxDecoration(
                    color: Theme.of(context).cardColor,
                    borderRadius: BorderRadius.circular(AppDimensions.radiusLg),
                    boxShadow: [
                      BoxShadow(
                        color: Colors.black.withOpacity(0.03),
                        blurRadius: 10,
                        offset: const Offset(0, 4),
                      ),
                    ],
                    border: Border.all(color: Theme.of(context).dividerColor.withOpacity(0.5)),
                  ),
                  child: Column(
                    children: [
                      Padding(
                        padding: const EdgeInsets.symmetric(vertical: 8),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Row(
                              mainAxisAlignment: MainAxisAlignment.spaceBetween,
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Expanded(
                                  child: Text(
                                    '1x ${widget.item.productName}',
                                    style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 15),
                                  ),
                                ),
                                const SizedBox(width: AppDimensions.sm),
                                Text(
                                  '${widget.item.price.toStringAsFixed(2)} $currency',
                                  style: const TextStyle(fontWeight: FontWeight.bold),
                                ),
                              ],
                            ),
                            if (widget.item.playerId.isNotEmpty) ...[
                              const SizedBox(height: 4),
                              Padding(
                                padding: const EdgeInsets.only(top: 2, right: 12, left: 12),
                                child: Row(
                                  children: [
                                    const Icon(Icons.gamepad, size: 14, color: AppTheme.primary),
                                    const SizedBox(width: 4),
                                    Expanded(
                                      child: Text(
                                        'الآيدي: ${widget.item.playerId}',
                                        style: const TextStyle(fontSize: 13, color: AppTheme.primary, fontWeight: FontWeight.bold),
                                      ),
                                    ),
                                  ],
                                ),
                              ),
                            ],
                            if (hasAddons) ...[
                              const SizedBox(height: 4),
                              ...widget.item.addons!.entries.map((entry) => Padding(
                                padding: const EdgeInsets.only(top: 2, right: 12, left: 12),
                                child: Row(
                                  children: [
                                    const Icon(Icons.subdirectory_arrow_right, size: 14, color: AppTheme.textSecondary),
                                    const SizedBox(width: 4),
                                    Expanded(
                                      child: Text(
                                        '${entry.key}: ${entry.value}',
                                        style: const TextStyle(fontSize: 13, color: AppTheme.textSecondary),
                                      ),
                                    ),
                                  ],
                                ),
                              )).toList(),
                            ],
                          ],
                        ),
                      ),
                      const SizedBox(height: 12),
                      Row(
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        children: [
                          const Text('المجموع الفرعي', style: TextStyle(fontWeight: FontWeight.w600, fontSize: 16)),
                          Text('${widget.item.regularPrice.toStringAsFixed(2)} $currency', style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 16)),
                        ],
                      ),
                      if (widget.item.regularPrice > widget.item.price) ...[
                        const SizedBox(height: 8),
                        Row(
                          mainAxisAlignment: MainAxisAlignment.spaceBetween,
                          children: [
                            const Text('التخفيض', style: TextStyle(fontWeight: FontWeight.w600, fontSize: 16, color: AppTheme.success)),
                            Text('-${(widget.item.regularPrice - widget.item.price).toStringAsFixed(2)} $currency', style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 16, color: AppTheme.success)),
                          ],
                        ),
                      ],
                      const SizedBox(height: 8),
                      Row(
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        children: [
                          Text(l10n.total, style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 18)),
                          Text('${widget.item.price.toStringAsFixed(2)} $currency', style: const TextStyle(color: AppTheme.primary, fontWeight: FontWeight.bold, fontSize: 20)),
                        ],
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: AppDimensions.xxl),
                
                _isLoading
                    ? const Center(child: CircularProgressIndicator())
                    : DsButton(
                        label: l10n.confirmPayment,
                        width: double.infinity,
                        onPressed: () => _createOrder(l10n),
                      ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
