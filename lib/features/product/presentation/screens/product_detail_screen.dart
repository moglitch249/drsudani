import 'dart:ui' as dart_ui;
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:cached_network_image/cached_network_image.dart';
import 'package:go_router/go_router.dart';
import '../../../../core/theme/app_theme.dart';
import '../../../../core/theme/app_dimensions.dart';
import '../../../../core/widgets/ds_button.dart';
import '../../../../core/utils/price_formatter.dart';
import '../../../../core/l10n/app_localizations.dart';
import '../../../../core/widgets/ds_text_field.dart';
import '../bloc/product_cubit.dart';
import '../bloc/product_state.dart';
import '../../../../core/di/injection.dart';
import '../../../checkout/data/models/checkout_item_model.dart';
import '../../data/models/product_model.dart';

class ProductDetailScreen extends StatefulWidget {
  final String productId;

  const ProductDetailScreen({Key? key, required this.productId}) : super(key: key);

  @override
  State<ProductDetailScreen> createState() => _ProductDetailScreenState();
}

class _ProductDetailScreenState extends State<ProductDetailScreen> {
  ProductVariationModel? _selectedVariation;
  final Map<String, String> _customAddonValues = {};
  final Map<String, TextEditingController> _addonControllers = {};
  bool _isFastBuyInProgress = false;

  @override
  void dispose() {
    for (var controller in _addonControllers.values) {
      controller.dispose();
    }
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context)!;
    return BlocProvider(
      create: (context) => getIt<ProductCubit>()..getProductById(int.tryParse(widget.productId) ?? 0),
      child: Scaffold(
        backgroundColor: Theme.of(context).scaffoldBackgroundColor, // Dynamic background
        body: BlocBuilder<ProductCubit, ProductState>(
          builder: (context, state) {
            if (state is ProductLoading) {
              return const Center(child: CircularProgressIndicator(color: AppTheme.primary));
            } else if (state is ProductError) {
              return Center(child: Text(state.message, style: const TextStyle(color: AppTheme.error)));
            } else if (state is ProductDetailLoaded) {
              final product = state.product;
              final variations = state.variations;
              
              if (_selectedVariation == null && variations.isNotEmpty) {
                _selectedVariation = variations.first;
              }

              double regularPrice = double.tryParse(product.regularPrice) ?? 0.0;
              double salePrice = double.tryParse(product.salePrice) ?? 0.0;
              double finalPrice = double.tryParse(product.price) ?? 0.0;

              if (_selectedVariation != null) {
                regularPrice = double.tryParse(_selectedVariation!.regularPrice) ?? 0.0;
                salePrice = double.tryParse(_selectedVariation!.salePrice) ?? 0.0;
                finalPrice = double.tryParse(_selectedVariation!.price) ?? 0.0;
              }

              return Stack(
                children: [
                  // Blurred Background Image (Fixed Scroll Issue by filling screen)
                  Positioned.fill(
                    child: CachedNetworkImage(
                      imageUrl: product.imageUrl.isNotEmpty ? product.imageUrl : 'error',
                      fit: BoxFit.cover,
                      errorWidget: (context, url, error) => Image.asset('assets/images/drsudani.png', fit: BoxFit.cover),
                    ),
                  ),
                  Positioned.fill(
                    child: BackdropFilter(
                      filter: dart_ui.ImageFilter.blur(sigmaX: 15, sigmaY: 15),
                      child: Container(
                        decoration: BoxDecoration(
                          gradient: LinearGradient(
                            begin: Alignment.topCenter,
                            end: Alignment.bottomCenter,
                            colors: [
                              Theme.of(context).scaffoldBackgroundColor.withOpacity(0.4),
                              Theme.of(context).scaffoldBackgroundColor.withOpacity(0.8),
                              Theme.of(context).scaffoldBackgroundColor,
                            ],
                          ),
                        ),
                      ),
                    ),
                  ),

                  // Main Scrollable Content
                  CustomScrollView(
                    slivers: [
                      SliverAppBar(
                        backgroundColor: Colors.transparent,
                        elevation: 0,
                        pinned: true,
                        leading: Padding(
                          padding: const EdgeInsets.all(8.0),
                          child: CircleAvatar(
                            backgroundColor: Colors.black45,
                            child: IconButton(
                              icon: const Icon(Icons.arrow_back, color: Colors.white),
                              onPressed: () => context.pop(),
                            ),
                          ),
                        ),
                      ),
                      SliverToBoxAdapter(
                        child: Padding(
                          padding: const EdgeInsets.symmetric(horizontal: AppDimensions.md),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              // Hero Card (Product Info)
                              Container(
                                margin: const EdgeInsets.only(top: AppDimensions.md, bottom: AppDimensions.xl),
                                padding: const EdgeInsets.all(AppDimensions.md),
                                decoration: BoxDecoration(
                                  color: Theme.of(context).cardColor, // Dynamic card color
                                  borderRadius: BorderRadius.circular(24),
                                  border: Border.all(color: Colors.white.withOpacity(0.05)),
                                  boxShadow: [
                                    BoxShadow(
                                      color: Colors.black.withOpacity(0.3),
                                      blurRadius: 15,
                                      offset: const Offset(0, 10),
                                    ),
                                  ],
                                ),
                                child: Column(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    // Row 1: Content on Left, Image on Right
                                    Row(
                                      crossAxisAlignment: CrossAxisAlignment.start,
                                      children: [
                                        Expanded(
                                          child: Column(
                                            crossAxisAlignment: CrossAxisAlignment.start,
                                            children: [
                                              Row(
                                                crossAxisAlignment: CrossAxisAlignment.center,
                                                children: [
                                                  Expanded(
                                                    child: Text(
                                                      product.name,
                                                      style: TextStyle(
                                                        color: Theme.of(context).colorScheme.onSurface,
                                                        fontSize: 22,
                                                        fontWeight: FontWeight.bold,
                                                      ),
                                                    ),
                                                  ),
                                                  const SizedBox(width: 8),
                                                  // Stock status badge
                                                  Container(
                                                    padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 3),
                                                    decoration: BoxDecoration(
                                                      color: product.stockStatus == 'instock'
                                                          ? AppTheme.success.withOpacity(0.1)
                                                          : AppTheme.error.withOpacity(0.1),
                                                      borderRadius: BorderRadius.circular(20),
                                                    ),
                                                    child: Row(
                                                      mainAxisSize: MainAxisSize.min,
                                                      children: [
                                                        Container(
                                                          width: 6, height: 6,
                                                          decoration: BoxDecoration(
                                                            color: product.stockStatus == 'instock' ? AppTheme.success : AppTheme.error,
                                                            shape: BoxShape.circle,
                                                          ),
                                                        ),
                                                        const SizedBox(width: 6),
                                                        Text(
                                                          product.stockStatus == 'instock' ? 'متوفر' : 'غير متوفر',
                                                          style: TextStyle(
                                                            fontSize: 11,
                                                            fontWeight: FontWeight.bold,
                                                            color: product.stockStatus == 'instock' ? AppTheme.success : AppTheme.error,
                                                          ),
                                                        ),
                                                      ],
                                                    ),
                                                  ),
                                                ],
                                              ),
                                              const SizedBox(height: 8),
                                              if (product.description.isNotEmpty)
                                                Text(
                                                  product.description,
                                                  maxLines: 4,
                                                  overflow: TextOverflow.ellipsis,
                                                  style: TextStyle(
                                                    color: Theme.of(context).colorScheme.onSurface.withOpacity(0.7),
                                                    fontSize: 12,
                                                    height: 1.5,
                                                  ),
                                                ),
                                            ],
                                          ),
                                        ),
                                        const SizedBox(width: 16),
                                        // Right side: Image and Price under it
                                        Column(
                                          children: [
                                            ClipRRect(
                                              borderRadius: BorderRadius.circular(16),
                                              child: SizedBox(
                                                width: 100,
                                                height: 100,
                                                child: CachedNetworkImage(
                                                  imageUrl: product.imageUrl.isNotEmpty ? product.imageUrl : 'error',
                                                  fit: BoxFit.cover,
                                                  errorWidget: (context, url, error) => Image.asset('assets/images/drsudani.png', fit: BoxFit.cover),
                                                ),
                                              ),
                                            ),
                                            const SizedBox(height: 12),
                                            Directionality(
                                              textDirection: l10n.locale.languageCode == 'ar' ? TextDirection.rtl : TextDirection.ltr,
                                              child: Row(
                                                mainAxisSize: MainAxisSize.min,
                                                crossAxisAlignment: CrossAxisAlignment.baseline,
                                                textBaseline: TextBaseline.alphabetic,
                                                children: [
                                                  Text(
                                                    PriceFormatter.format(finalPrice),
                                                    style: const TextStyle(
                                                      color: AppTheme.success,
                                                      fontSize: 22,
                                                      fontWeight: FontWeight.bold,
                                                    ),
                                                  ),
                                                  const SizedBox(width: 4),
                                                  Text(
                                                    l10n.locale.languageCode == 'ar' ? 'ج.س' : 'SDG',
                                                    style: TextStyle(
                                                      color: Theme.of(context).colorScheme.onSurface.withOpacity(0.6),
                                                      fontSize: 12,
                                                      fontWeight: FontWeight.bold,
                                                    ),
                                                  ),
                                                ],
                                              ),
                                            ),
                                            if (regularPrice > finalPrice) ...[
                                              const SizedBox(height: 4),
                                              Directionality(
                                                textDirection: l10n.locale.languageCode == 'ar' ? TextDirection.rtl : TextDirection.ltr,
                                                child: Row(
                                                  mainAxisSize: MainAxisSize.min,
                                                  children: [
                                                    Text(
                                                      PriceFormatter.format(regularPrice),
                                                      style: TextStyle(
                                                        color: Theme.of(context).colorScheme.onSurface.withOpacity(0.5),
                                                        fontSize: 12,
                                                        decoration: TextDecoration.lineThrough,
                                                      ),
                                                    ),
                                                    const SizedBox(width: 4),
                                                    Text(
                                                      l10n.locale.languageCode == 'ar' ? 'ج.س' : 'SDG',
                                                      style: TextStyle(
                                                        color: Theme.of(context).colorScheme.onSurface.withOpacity(0.5),
                                                        fontSize: 10,
                                                        decoration: TextDecoration.lineThrough,
                                                      ),
                                                    ),
                                                  ],
                                                ),
                                              ),
                                            ],
                                          ],
                                        ),
                                      ],
                                    ),
                                  ],
                                ),
                              ),

                              // Variations / The Card
                              if (variations.isNotEmpty) ...[
                                Center(
                                  child: Text(
                                    l10n.locale.languageCode == 'ar' ? 'البطاقة' : 'The Card',
                                    style: TextStyle(color: Theme.of(context).colorScheme.onSurface, fontSize: 18, fontWeight: FontWeight.bold),
                                  ),
                                ),
                                const SizedBox(height: AppDimensions.lg),
                                GridView.builder(
                                  padding: EdgeInsets.zero,
                                  physics: const NeverScrollableScrollPhysics(),
                                  shrinkWrap: true,
                                  gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                                    crossAxisCount: 2,
                                    crossAxisSpacing: 12,
                                    mainAxisSpacing: 12,
                                    childAspectRatio: 2.5,
                                  ),
                                  itemCount: variations.length,
                                  itemBuilder: (context, index) {
                                    final varItem = variations[index];
                                    String label = varItem.attributes.values.join(' - ');
                                    if (label.isEmpty) label = 'باقة ${varItem.id}';
                                    final isSelected = _selectedVariation?.id == varItem.id;
                                    
                                    return GestureDetector(
                                      onTap: () => setState(() => _selectedVariation = varItem),
                                      child: AnimatedContainer(
                                        duration: const Duration(milliseconds: 200),
                                        decoration: BoxDecoration(
                                          color: isSelected ? Theme.of(context).colorScheme.primary.withOpacity(0.2) : Theme.of(context).cardColor,
                                          borderRadius: BorderRadius.circular(16),
                                          border: Border.all(
                                            color: isSelected ? Theme.of(context).colorScheme.primary : Theme.of(context).dividerColor,
                                            width: isSelected ? 2 : 1,
                                          ),
                                        ),
                                        child: Center(
                                          child: Text(
                                            label,
                                            style: TextStyle(
                                              color: isSelected ? Theme.of(context).colorScheme.primary : Theme.of(context).colorScheme.onSurface.withOpacity(0.8),
                                              fontWeight: isSelected ? FontWeight.bold : FontWeight.normal,
                                              fontSize: 16,
                                            ),
                                            textAlign: TextAlign.center,
                                          ),
                                        ),
                                      ),
                                    );
                                  },
                                ),
                                const SizedBox(height: AppDimensions.xl),
                              ],

                              // Addons and Player ID Fields
                              if (product.addons.isNotEmpty || product.isBotAutoTopup) ...[
                                ...product.addons.map((addon) {
                                  if (!_addonControllers.containsKey(addon.name)) {
                                    _addonControllers[addon.name] = TextEditingController();
                                  }
                                  return _buildDarkInputField(
                                    label: addon.name,
                                    isRequired: addon.required,
                                    controller: _addonControllers[addon.name]!,
                                    onChanged: (val) => _customAddonValues[addon.name] = val,
                                  );
                                }).toList(),
                                
                                if (product.isBotAutoTopup) ...[
                                  if (!_addonControllers.containsKey('Player ID'))
                                    ...[
                                      () {
                                        _addonControllers['Player ID'] = TextEditingController();
                                        return const SizedBox.shrink();
                                      }()
                                    ],
                                  _buildDarkInputField(
                                    label: l10n.locale.languageCode == 'ar' ? 'أيدي اللاعب (Player ID)' : 'Player ID',
                                    isRequired: true,
                                    controller: _addonControllers['Player ID']!,
                                    onChanged: (val) => _customAddonValues['Player ID'] = val,
                                  ),
                                ],
                              ],

                              SizedBox(height: MediaQuery.of(context).padding.bottom + 120),
                            ],
                          ),
                        ),
                      ),
                    ],
                  ),
                ],
              );
            }
            return const SizedBox();
          },
        ),
        bottomSheet: BlocBuilder<ProductCubit, ProductState>(
          builder: (context, state) {
            if (state is ProductDetailLoaded) {
              return Container(
                padding: EdgeInsets.fromLTRB(
                  AppDimensions.lg,
                  AppDimensions.md,
                  AppDimensions.lg,
                  AppDimensions.lg + MediaQuery.of(context).padding.bottom,
                ),
                decoration: BoxDecoration(
                  color: Theme.of(context).scaffoldBackgroundColor, // Dynamic bottom sheet background
                  border: Border(top: BorderSide(color: Theme.of(context).dividerColor, width: 1)),
                  boxShadow: [
                    BoxShadow(color: Colors.black.withOpacity(0.5), blurRadius: 20, offset: const Offset(0, -10)),
                  ],
                ),
                child: SafeArea(
                  child: Row(
                    children: [

                      // Buy Now Button
                      Expanded(
                        child: Container(
                          height: 56,
                          decoration: BoxDecoration(
                            gradient: const LinearGradient(
                              colors: [Color(0xFF8C52FF), Color(0xFF6B3FA0)],
                              begin: Alignment.centerLeft,
                              end: Alignment.centerRight,
                            ),
                            borderRadius: BorderRadius.circular(16),
                            boxShadow: [
                              BoxShadow(
                                color: const Color(0xFF8C52FF).withOpacity(0.4),
                                blurRadius: 12,
                                offset: const Offset(0, 4),
                              ),
                            ],
                          ),
                          child: Material(
                            color: Colors.transparent,
                            child: InkWell(
                              borderRadius: BorderRadius.circular(16),
                              onTap: () {
                                HapticFeedback.lightImpact();
                                if (_isFastBuyInProgress) return;
                                setState(() => _isFastBuyInProgress = true);
                                try {
                                  final checkoutItem = _validateAndGetCheckoutItem(context, state, l10n);
                                  if (checkoutItem != null) {
                                    context.push('/checkout', extra: checkoutItem);
                                  }
                                } finally {
                                  if (mounted) setState(() => _isFastBuyInProgress = false);
                                }
                              },
                              child: Row(
                                mainAxisAlignment: MainAxisAlignment.center,
                                children: [
                                  if (_isFastBuyInProgress)
                                    const SizedBox(width: 24, height: 24, child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2))
                                  else ...[
                                    Text(
                                      l10n.locale.languageCode == 'ar' ? 'اشتر الآن' : 'Buy Now',
                                      style: const TextStyle(color: Colors.white, fontSize: 18, fontWeight: FontWeight.bold),
                                    ),
                                    const SizedBox(width: 10),
                                    const Icon(Icons.bolt_rounded, color: Colors.white, size: 22),
                                  ],
                                ],
                              ),
                            ),
                          ),
                        ),
                      ),
                    ],
                  ),
                ),
              );
            }
            return const SizedBox.shrink();
          },
        ),
      ),
    );
  }

  Widget _buildDarkInputField({
    required String label,
    required bool isRequired,
    required TextEditingController controller,
    required Function(String) onChanged,
  }) {
    // نص تلميحي ذكي بناءً على اسم الحقل
    String hintText;
    if (label.toLowerCase().contains('player') || label.toLowerCase().contains('id') || label.contains('آيدي')) {
      hintText = 'ex: 123456789';
    } else if (label.toLowerCase().contains('email') || label.contains('بريد')) {
      hintText = 'ex: name@email.com';
    } else if (label.toLowerCase().contains('username') || label.contains('اسم المستخدم')) {
      hintText = 'ex: DrSudani123';
    } else {
      hintText = 'أدخل القيمة...';
    }

    return Padding(
      padding: const EdgeInsets.only(bottom: AppDimensions.lg),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Text(
                label,
                style: TextStyle(color: Theme.of(context).colorScheme.onSurface, fontSize: 14, fontWeight: FontWeight.bold),
              ),
              if (isRequired)
                const Text(' *', style: TextStyle(color: Colors.red, fontSize: 16, fontWeight: FontWeight.bold)),
            ],
          ),
          const SizedBox(height: 8),
          Container(
            decoration: BoxDecoration(
              color: Theme.of(context).cardColor,
              borderRadius: BorderRadius.circular(12),
              border: Border.all(color: Theme.of(context).dividerColor),
            ),
            child: TextField(
              controller: controller,
              onChanged: onChanged,
              style: TextStyle(color: Theme.of(context).colorScheme.onSurface),
              decoration: InputDecoration(
                border: InputBorder.none,
                contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
                hintText: hintText,
                hintStyle: TextStyle(color: Theme.of(context).colorScheme.onSurface.withOpacity(0.3)),
              ),
            ),
          ),
        ],
      ),
    );
  }

  CheckoutItem? _validateAndGetCheckoutItem(BuildContext context, ProductDetailLoaded state, AppLocalizations l10n) {
    final product = state.product;
    final variations = state.variations;

    if (variations.isNotEmpty && _selectedVariation == null) {
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(l10n.pleaseSelectPackage), backgroundColor: AppTheme.error));
      return null;
    }

    for (var addon in product.addons) {
      if (addon.required) {
        final val = _customAddonValues[addon.name]?.trim() ?? '';
        if (val.isEmpty) {
          ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('${l10n.pleaseEnter} ${addon.name}'), backgroundColor: AppTheme.error));
          return null;
        }
      }
    }
    
    if (product.isBotAutoTopup) {
      final val = _customAddonValues['Player ID']?.trim() ?? '';
      if (val.isEmpty) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(l10n.enterPlayerId), backgroundColor: AppTheme.error));
        return null;
      }
    }

    final double price = _selectedVariation != null 
        ? double.tryParse(_selectedVariation!.price) ?? 0.0 
        : double.tryParse(product.price) ?? 0.0;
        
    final double regularPrice = _selectedVariation != null
        ? double.tryParse(_selectedVariation!.regularPrice) ?? price
        : double.tryParse(product.regularPrice) ?? price;

    final Map<String, dynamic> addonsToSave = Map.from(_customAddonValues);
    final String playerId = addonsToSave.remove('Player ID') ?? '';

    return CheckoutItem(
      productId: product.id,
      variationId: _selectedVariation?.id,
      productName: product.name,
      price: price,
      regularPrice: regularPrice,
      playerId: playerId,
      addons: addonsToSave.isNotEmpty ? addonsToSave : null,
    );
  }
}
