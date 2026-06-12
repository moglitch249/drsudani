import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:cached_network_image/cached_network_image.dart';
import 'package:go_router/go_router.dart';
import '../../../../core/theme/app_theme.dart';
import '../../../../core/theme/app_dimensions.dart';
import '../../../../core/widgets/ds_button.dart';
import '../../../../core/widgets/ds_empty_state.dart';
import '../bloc/cart_cubit.dart';
import '../bloc/cart_state.dart';
import '../../../../core/l10n/app_localizations.dart';

class CartScreen extends StatelessWidget {
  const CartScreen({Key? key}) : super(key: key);

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context)!;
    final isArabic = l10n.locale.languageCode == 'ar';
    final currency = isArabic ? 'ج.س' : 'SDG';

    return Scaffold(
      backgroundColor: Theme.of(context).scaffoldBackgroundColor,
      appBar: AppBar(
        title: Text(l10n.cart, style: const TextStyle(fontWeight: FontWeight.bold)),
        centerTitle: true,
        actions: [
          BlocBuilder<CartCubit, CartState>(
            builder: (context, state) {
              if (state is CartLoaded && state.items.isNotEmpty) {
                return TextButton.icon(
                  icon: const Icon(Icons.delete_sweep_outlined, size: 20),
                  label: Text(isArabic ? 'مسح الكل' : 'Clear'),
                  style: TextButton.styleFrom(foregroundColor: AppTheme.error),
                  onPressed: () {
                    showDialog(
                      context: context,
                      builder: (ctx) => AlertDialog(
                        title: Text(isArabic ? 'مسح السلة' : 'Clear Cart'),
                        content: Text(isArabic ? 'هل أنت متأكد أنك تريد مسح جميع المنتجات من السلة؟' : 'Are you sure you want to remove all items?'),
                        actions: [
                          TextButton(
                            onPressed: () => Navigator.pop(ctx),
                            child: Text(isArabic ? 'إلغاء' : 'Cancel'),
                          ),
                          TextButton(
                            style: TextButton.styleFrom(foregroundColor: AppTheme.error),
                            onPressed: () {
                              context.read<CartCubit>().clearCart();
                              Navigator.pop(ctx);
                            },
                            child: Text(isArabic ? 'مسح' : 'Clear'),
                          ),
                        ],
                      ),
                    );
                  },
                );
              }
              return const SizedBox.shrink();
            },
          ),
        ],
      ),
      body: BlocBuilder<CartCubit, CartState>(
        builder: (context, state) {
          if (state is CartLoading) {
            return const Center(child: CircularProgressIndicator(color: AppTheme.primary));
          } else if (state is CartLoaded) {
            if (state.items.isEmpty) {
              return Center(
                child: DsEmptyState(
                  icon: Icons.shopping_cart_outlined,
                  title: l10n.cartEmpty,
                  subtitle: l10n.cartEmptyDesc,
                ),
              );
            }

            return Column(
              children: [
                Expanded(
                  child: ListView.builder(
                    padding: const EdgeInsets.all(AppDimensions.lg),
                    itemCount: state.items.length,
                    itemBuilder: (context, index) {
                      final item = state.items[index];
                      final hasDiscount = item.regularPrice > item.price;

                      return Dismissible(
                        key: ValueKey('${item.product.id}_${item.selectedVariation}_$index'),
                        direction: DismissDirection.endToStart,
                        background: Container(
                          margin: const EdgeInsets.only(bottom: AppDimensions.md),
                          decoration: BoxDecoration(
                            color: AppTheme.error.withOpacity(0.1),
                            borderRadius: BorderRadius.circular(AppDimensions.radiusLg),
                          ),
                          alignment: Alignment.centerRight,
                          padding: const EdgeInsets.only(right: 24),
                          child: const Icon(Icons.delete_outline, color: AppTheme.error, size: 28),
                        ),
                        onDismissed: (_) {
                          final removedItem = item;
                          final removedIndex = index;
                          context.read<CartCubit>().removeFromCart(removedIndex);

                          ScaffoldMessenger.of(context).clearSnackBars();
                          ScaffoldMessenger.of(context).showSnackBar(
                            SnackBar(
                              content: Text('تم حذف ${removedItem.product.name}'),
                              backgroundColor: AppTheme.error,
                              behavior: SnackBarBehavior.floating,
                              shape: RoundedRectangleBorder(
                                borderRadius: BorderRadius.circular(12)),
                              action: SnackBarAction(
                                label: 'تراجع',
                                textColor: Colors.white,
                                onPressed: () {
                                  context.read<CartCubit>().addToCart(removedItem);
                                },
                              ),
                            ),
                          );
                        },
                        child: Container(
                          margin: const EdgeInsets.only(bottom: AppDimensions.md),
                          decoration: BoxDecoration(
                            color: Theme.of(context).cardColor,
                            borderRadius: BorderRadius.circular(AppDimensions.radiusLg),
                            boxShadow: [
                              BoxShadow(
                                color: Colors.black.withOpacity(0.04),
                                blurRadius: 12,
                                offset: const Offset(0, 4),
                              ),
                            ],
                          ),
                          child: Padding(
                            padding: const EdgeInsets.all(AppDimensions.md),
                            child: Row(
                              children: [
                                // Product Image
                                Container(
                                  decoration: BoxDecoration(
                                    borderRadius: BorderRadius.circular(AppDimensions.radiusMd),
                                    border: Border.all(
                                      color: Theme.of(context).dividerColor.withOpacity(0.2),
                                    ),
                                  ),
                                  child: ClipRRect(
                                    borderRadius: BorderRadius.circular(AppDimensions.radiusMd),
                                    child: CachedNetworkImage(
                                      imageUrl: item.product.imageUrl.isNotEmpty
                                          ? item.product.imageUrl
                                          : 'assets/images/drsudani.png',
                                      width: 70,
                                      height: 70,
                                      fit: BoxFit.cover,
                                      errorWidget: (context, url, error) =>
                                          Image.asset('assets/images/drsudani.png', width: 70, height: 70),
                                    ),
                                  ),
                                ),
                                const SizedBox(width: AppDimensions.md),

                                // Product Info
                                Expanded(
                                  child: Column(
                                    crossAxisAlignment: CrossAxisAlignment.start,
                                    children: [
                                      Text(
                                        item.product.name,
                                        style: TextStyle(
                                          fontWeight: FontWeight.bold,
                                          fontSize: 15,
                                          color: Theme.of(context).colorScheme.onSurface,
                                        ),
                                        maxLines: 2,
                                        overflow: TextOverflow.ellipsis,
                                      ),
                                      if (item.selectedVariation.isNotEmpty) ...[
                                        const SizedBox(height: 3),
                                        Container(
                                          padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
                                          decoration: BoxDecoration(
                                            color: AppTheme.primary.withOpacity(0.08),
                                            borderRadius: BorderRadius.circular(8),
                                          ),
                                          child: Text(
                                            item.selectedVariation,
                                            style: const TextStyle(
                                              color: AppTheme.primary,
                                              fontSize: 11,
                                              fontWeight: FontWeight.w600,
                                            ),
                                          ),
                                        ),
                                      ],
                                      if (item.customField.isNotEmpty) ...[
                                        const SizedBox(height: 3),
                                        Row(
                                          children: [
                                            const Icon(Icons.gamepad, size: 12, color: AppTheme.primary),
                                            const SizedBox(width: 4),
                                            Text(
                                              'الآيدي: ${item.customField}',
                                              style: const TextStyle(
                                                color: AppTheme.primary,
                                                fontSize: 11,
                                                fontWeight: FontWeight.bold,
                                              ),
                                            ),
                                          ],
                                        ),
                                      ],
                                      if (item.customAddons != null && item.customAddons!.isNotEmpty) ...[
                                        const SizedBox(height: 3),
                                        ...item.customAddons!.entries.map((entry) => Padding(
                                          padding: const EdgeInsets.only(top: 2),
                                          child: Text(
                                            '${entry.key}: ${entry.value}',
                                            style: TextStyle(
                                              fontSize: 11,
                                              color: Theme.of(context).textTheme.bodySmall?.color,
                                            ),
                                            maxLines: 1,
                                            overflow: TextOverflow.ellipsis,
                                          ),
                                        )),
                                      ],
                                      const SizedBox(height: 6),
                                      Row(
                                        children: [
                                          Text(
                                            '${item.price.toStringAsFixed(0)} $currency',
                                            style: const TextStyle(
                                              color: AppTheme.primary,
                                              fontWeight: FontWeight.bold,
                                              fontSize: 15,
                                            ),
                                          ),
                                          if (hasDiscount) ...[
                                            const SizedBox(width: 8),
                                            Text(
                                              '${item.regularPrice.toStringAsFixed(0)}',
                                              style: TextStyle(
                                                color: Theme.of(context).textTheme.bodySmall?.color,
                                                fontSize: 12,
                                                decoration: TextDecoration.lineThrough,
                                              ),
                                            ),
                                          ],
                                        ],
                                      ),
                                    ],
                                  ),
                                ),

                                // Quantity Controls
                                Container(
                                  decoration: BoxDecoration(
                                    color: Theme.of(context).scaffoldBackgroundColor,
                                    borderRadius: BorderRadius.circular(AppDimensions.radiusMd),
                                  ),
                                  child: Column(
                                    children: [
                                      InkWell(
                                        onTap: () => context.read<CartCubit>().updateQuantity(index, item.quantity + 1),
                                        borderRadius: BorderRadius.circular(8),
                                        child: Container(
                                          padding: const EdgeInsets.all(6),
                                          child: const Icon(Icons.add, size: 18, color: AppTheme.primary),
                                        ),
                                      ),
                                      Padding(
                                        padding: const EdgeInsets.symmetric(vertical: 2),
                                        child: Text(
                                          '${item.quantity}',
                                          style: TextStyle(
                                            fontWeight: FontWeight.bold,
                                            fontSize: 16,
                                            color: Theme.of(context).colorScheme.onSurface,
                                          ),
                                        ),
                                      ),
                                      InkWell(
                                        onTap: () => context.read<CartCubit>().updateQuantity(index, item.quantity - 1),
                                        borderRadius: BorderRadius.circular(8),
                                        child: Container(
                                          padding: const EdgeInsets.all(6),
                                          child: Icon(
                                            item.quantity > 1 ? Icons.remove : Icons.delete_outline,
                                            size: 18,
                                            color: item.quantity > 1 ? Colors.grey : AppTheme.error,
                                          ),
                                        ),
                                      ),
                                    ],
                                  ),
                                ),
                              ],
                            ),
                          ),
                        ),
                      );
                    },
                  ),
                ),

                // Bottom Summary & Checkout
                Container(
                  padding: const EdgeInsets.all(AppDimensions.lg),
                  decoration: BoxDecoration(
                    color: Theme.of(context).cardColor,
                    boxShadow: [
                      BoxShadow(
                        color: Colors.black.withOpacity(0.06),
                        blurRadius: 20,
                        offset: const Offset(0, -5),
                      ),
                    ],
                    borderRadius: const BorderRadius.vertical(top: Radius.circular(AppDimensions.radiusXl)),
                  ),
                  child: SafeArea(
                    child: Column(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        // Subtotal
                        if (state.totalDiscount > 0) ...[
                          Row(
                            mainAxisAlignment: MainAxisAlignment.spaceBetween,
                            children: [
                              Text(
                                isArabic ? 'المجموع الفرعي' : 'Subtotal',
                                style: TextStyle(fontSize: 14, color: Theme.of(context).textTheme.bodySmall?.color),
                              ),
                              Text(
                                '${state.subTotal.toStringAsFixed(0)} $currency',
                                style: TextStyle(
                                  fontSize: 14,
                                  color: Theme.of(context).textTheme.bodySmall?.color,
                                  decoration: TextDecoration.lineThrough,
                                ),
                              ),
                            ],
                          ),
                          const SizedBox(height: 6),
                          Row(
                            mainAxisAlignment: MainAxisAlignment.spaceBetween,
                            children: [
                              Row(
                                children: [
                                  const Icon(Icons.discount_outlined, size: 16, color: AppTheme.success),
                                  const SizedBox(width: 4),
                                  Text(
                                    isArabic ? 'التخفيض' : 'Discount',
                                    style: const TextStyle(fontSize: 14, color: AppTheme.success, fontWeight: FontWeight.w600),
                                  ),
                                ],
                              ),
                              Text(
                                '-${state.totalDiscount.toStringAsFixed(0)} $currency',
                                style: const TextStyle(fontSize: 14, color: AppTheme.success, fontWeight: FontWeight.bold),
                              ),
                            ],
                          ),
                          const SizedBox(height: 8),
                          Divider(color: Theme.of(context).dividerColor.withOpacity(0.3)),
                          const SizedBox(height: 4),
                        ],
                        Row(
                          mainAxisAlignment: MainAxisAlignment.spaceBetween,
                          children: [
                            Text('${l10n.total}:', style: const TextStyle(fontSize: 18, fontWeight: FontWeight.bold)),
                            Text(
                              '${state.totalAmount.toStringAsFixed(0)} $currency',
                              style: const TextStyle(fontSize: 22, fontWeight: FontWeight.bold, color: AppTheme.primary),
                            ),
                          ],
                        ),
                        const SizedBox(height: AppDimensions.md),
                        DsButton(
                          label: l10n.checkout,
                          width: double.infinity,
                          onPressed: () {
                            context.push('/checkout');
                          },
                        ),
                      ],
                    ),
                  ),
                ),
              ],
            );
          } else if (state is CartError) {
            return Center(
              child: DsEmptyState(
                icon: Icons.error_outline,
                title: 'خطأ في السلة',
                subtitle: state.message,
                buttonText: 'إعادة المحاولة',
                onButtonPressed: () => context.read<CartCubit>().loadCart(),
              ),
            );
          }
          return const SizedBox.shrink();
        },
      ),
    );
  }
}
