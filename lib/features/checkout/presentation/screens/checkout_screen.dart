import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';
import 'package:local_auth/local_auth.dart';
import '../../../../core/theme/app_theme.dart';
import '../../../../core/theme/app_dimensions.dart';
import '../../../../core/widgets/ds_button.dart';
import '../../../../core/widgets/ds_text_field.dart';
import '../../../cart/presentation/bloc/cart_cubit.dart';
import '../../../cart/presentation/bloc/cart_state.dart';
import '../../../auth/presentation/bloc/auth_bloc.dart';
import '../../../auth/presentation/bloc/auth_state_event.dart';
import '../../../../core/network/api_service.dart';
import '../../../../core/di/injection.dart';
import '../../../profile/presentation/bloc/wallet_cubit.dart';
import '../../../../core/l10n/app_localizations.dart';

class CheckoutScreen extends StatefulWidget {
  const CheckoutScreen({Key? key}) : super(key: key);

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
    // Auto-fill user data if logged in
    final authState = context.read<AuthBloc>().state;
    if (authState is AuthSuccess) {
      _emailController.text = authState.user.email;
      _userName = authState.user.firstName;
    }
    context.read<WalletCubit>().fetchWalletData();
  }

  @override
  void dispose() {
    _phoneController.dispose();
    _emailController.dispose();
    super.dispose();
  }

  Future<void> _createOrder(CartLoaded cartState, AppLocalizations l10n) async {
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
        // تستمر العملية إذا فشل القارئ لتجنب تعطيل الدفع تماماً
      }
    }

    setState(() => _isLoading = true);

    try {
      final List<Map<String, dynamic>> lineItems = cartState.items.map((item) {
        final List<Map<String, dynamic>> metaData = [];
        
        if (item.customField.isNotEmpty) {
          metaData.add({
            'key': 'Player ID',
            'value': item.customField,
          });
        }
        
        if (item.customAddons != null && item.customAddons!.isNotEmpty) {
          item.customAddons!.forEach((key, value) {
            metaData.add({
              'key': key,
              'value': value,
            });
          });
        }

        final Map<String, dynamic> mappedItem = {
          'product_id': item.product.id,
          'quantity': item.quantity,
        };

        if (item.variationId != null) {
          mappedItem['variation_id'] = item.variationId;
        }

        if (metaData.isNotEmpty) {
          mappedItem['meta_data'] = metaData;
        }

        return mappedItem;
      }).toList();

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
        // Clear cart
        await context.read<CartCubit>().clearCart();
        context.read<WalletCubit>().fetchWalletData();
        
        if (mounted) {
          context.go('/order-success/\${response.data['order_id']}');
        }
      } else {
        final msg = response.data['message'] ?? 'فشل إنشاء الطلب';
        throw Exception(msg);
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('خطأ: $e'), backgroundColor: AppTheme.error),
        );
      }
    } finally {
      if (mounted) setState(() => _isLoading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context)!;
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
        child: Stack(
          children: [
            BlocBuilder<CartCubit, CartState>(
              builder: (context, state) {
                if (state is CartLoaded && state.items.isNotEmpty) {
                  return SingleChildScrollView(
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
                          ...state.items.map((item) {
                            final hasAddons = item.customAddons != null && item.customAddons!.isNotEmpty;
                            return Padding(
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
                                          '${item.quantity}x ${item.product.name}',
                                          style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 15),
                                        ),
                                      ),
                                      const SizedBox(width: AppDimensions.sm),
                                      Text(
                                        '${(item.price * item.quantity).toStringAsFixed(2)} ${l10n.locale.languageCode == 'ar' ? 'ج.س' : 'SDG'}',
                                        style: const TextStyle(fontWeight: FontWeight.bold),
                                      ),
                                    ],
                                  ),
                                  if (item.customField.isNotEmpty) ...[
                                    const SizedBox(height: 4),
                                    Padding(
                                      padding: const EdgeInsets.only(top: 2, right: 12, left: 12),
                                      child: Row(
                                        children: [
                                          Icon(Icons.gamepad, size: 14, color: AppTheme.primary),
                                          const SizedBox(width: 4),
                                          Expanded(
                                            child: Text(
                                              'الآيدي: ${item.customField}',
                                              style: TextStyle(fontSize: 13, color: AppTheme.primary, fontWeight: FontWeight.bold),
                                            ),
                                          ),
                                        ],
                                      ),
                                    ),
                                  ],
                                  if (hasAddons) ...[
                                    const SizedBox(height: 4),
                                    ...item.customAddons!.entries.map((entry) => Padding(
                                      padding: const EdgeInsets.only(top: 2, right: 12, left: 12),
                                      child: Row(
                                        children: [
                                          Icon(Icons.subdirectory_arrow_right, size: 14, color: AppTheme.textSecondary),
                                          const SizedBox(width: 4),
                                          Expanded(
                                            child: Text(
                                              '${entry.key}: ${entry.value}',
                                              style: TextStyle(fontSize: 13, color: AppTheme.textSecondary),
                                            ),
                                          ),
                                        ],
                                      ),
                                    )).toList(),
                                  ],
                                ],
                              ),
                            );
                          }).toList(),
                          const SizedBox(height: 12),
                          Row(
                            mainAxisAlignment: MainAxisAlignment.spaceBetween,
                            children: [
                              Text('المجموع الفرعي', style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 16)),
                              Text('${state.subTotal.toStringAsFixed(2)} ${l10n.locale.languageCode == 'ar' ? 'ج.س' : 'SDG'}', style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 16)),
                            ],
                          ),
                          if (state.totalDiscount > 0) ...[
                            const SizedBox(height: 8),
                            Row(
                              mainAxisAlignment: MainAxisAlignment.spaceBetween,
                              children: [
                                Text('التخفيض', style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 16, color: AppTheme.success)),
                                Text('-${state.totalDiscount.toStringAsFixed(2)} ${l10n.locale.languageCode == 'ar' ? 'ج.س' : 'SDG'}', style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 16, color: AppTheme.success)),
                              ],
                            ),
                          ],
                          const SizedBox(height: 8),
                          Row(
                            mainAxisAlignment: MainAxisAlignment.spaceBetween,
                            children: [
                              Text(l10n.total, style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 18)),
                              Text('${state.totalAmount.toStringAsFixed(2)} ${l10n.locale.languageCode == 'ar' ? 'ج.س' : 'SDG'}', style: const TextStyle(color: AppTheme.primary, fontWeight: FontWeight.bold, fontSize: 20)),
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
                            onPressed: () => _createOrder(state, l10n),
                          ),
                  ],
                ),
              ),
            );
          }
          return Center(child: Text(l10n.cartEmpty));
            },
          ),
        ],
      ),
    ),
  );
  }
}
