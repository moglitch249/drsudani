import 'dart:async';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';
import '../../../../core/theme/app_theme.dart';
import '../../../../core/theme/app_dimensions.dart';
import '../../../../core/widgets/ds_product_card.dart';
import '../../../../core/widgets/ds_text_field.dart';
import '../../../../core/widgets/ds_empty_state.dart';
import '../../../product/presentation/bloc/product_cubit.dart';
import '../../../product/presentation/bloc/product_state.dart';
import '../../../../core/l10n/app_localizations.dart';

class ShopScreen extends StatefulWidget {
  const ShopScreen({Key? key}) : super(key: key);

  @override
  State<ShopScreen> createState() => _ShopScreenState();
}

class _ShopScreenState extends State<ShopScreen> {
  int _selectedCategoryIndex = 0;
  final TextEditingController _searchController = TextEditingController();
  Timer? _debounce;

  // Temporary category mapping until dynamic categories are implemented
  int? _getCategoryId(int index) {
    switch (index) {
      case 0: return null; // All
      case 1: return 18;   // Direct Recharge (example ID)
      case 2: return 19;   // Game Cards
      case 3: return 20;   // Electronic Payment
      default: return null;
    }
  }

  @override
  void dispose() {
    _searchController.dispose();
    _debounce?.cancel();
    super.dispose();
  }

  void _onSearchChanged(String query) {
    if (_debounce?.isActive ?? false) _debounce!.cancel();
    _debounce = Timer(const Duration(milliseconds: 400), () {
      context.read<ProductCubit>().loadProducts(
        categoryId: _getCategoryId(_selectedCategoryIndex),
        search: query,
      );
    });
  }

  void _onCategoryChanged(int index) {
    setState(() => _selectedCategoryIndex = index);
    context.read<ProductCubit>().loadProducts(
      categoryId: _getCategoryId(index),
      search: _searchController.text,
    );
  }

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context)!;
    final List<String> categories = [
      l10n.categoryAll, 
      l10n.categoryDirectRecharge, 
      l10n.categoryGameCards, 
      l10n.categoryElectronicPayment
    ];

    return Scaffold(
      backgroundColor: Theme.of(context).scaffoldBackgroundColor,
      appBar: AppBar(
        title: Text(l10n.shop, style: const TextStyle(fontWeight: FontWeight.bold)),
        centerTitle: true,
        bottom: PreferredSize(
          preferredSize: const Size.fromHeight(130),
          child: Column(
            children: [
              Padding(
                padding: const EdgeInsets.symmetric(horizontal: AppDimensions.md),
                child: DsTextField(
                  controller: _searchController,
                  hint: l10n.searchGames,
                  prefixIcon: const Icon(Icons.search),
                  onChanged: _onSearchChanged,
                ),
              ),
              const SizedBox(height: AppDimensions.md),
              SizedBox(
                height: 40,
                child: ListView.builder(
                  scrollDirection: Axis.horizontal,
                  padding: const EdgeInsets.symmetric(horizontal: AppDimensions.md),
                  itemCount: categories.length,
                  itemBuilder: (context, index) {
                    final isSelected = _selectedCategoryIndex == index;
                    return GestureDetector(
                      onTap: () => _onCategoryChanged(index),
                      child: AnimatedContainer(
                        duration: const Duration(milliseconds: 200),
                        margin: const EdgeInsets.only(left: AppDimensions.sm),
                        padding: const EdgeInsets.symmetric(horizontal: AppDimensions.lg),
                        alignment: Alignment.center,
                        decoration: BoxDecoration(
                          color: isSelected ? AppTheme.primary : Theme.of(context).cardColor,
                          borderRadius: BorderRadius.circular(AppDimensions.radiusFull),
                          border: isSelected ? null : Border.all(color: AppTheme.primary.withOpacity(0.2)),
                        ),
                        child: Text(
                          categories[index],
                          style: TextStyle(
                            color: isSelected ? Colors.white : Theme.of(context).colorScheme.onSurface,
                            fontWeight: isSelected ? FontWeight.bold : FontWeight.normal,
                          ),
                        ),
                      ),
                    );
                  },
                ),
              ),
              const SizedBox(height: AppDimensions.md),
            ],
          ),
        ),
      ),
      body: BlocBuilder<ProductCubit, ProductState>(
        builder: (context, state) {
          if (state is ProductLoading) {
            return const Center(child: CircularProgressIndicator(color: AppTheme.primary));
          } else if (state is ProductError) {
            return Center(child: Text(state.message, style: const TextStyle(color: AppTheme.error)));
          } else if (state is ProductsLoaded) {
            if (state.products.isEmpty) {
              return DsEmptyState(
                icon: Icons.inventory_2_outlined,
                title: l10n.noOffers,
                subtitle: '',
              );
            }
            return GridView.builder(
              padding: const EdgeInsets.all(AppDimensions.md),
              gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                crossAxisCount: 2,
                crossAxisSpacing: AppDimensions.md,
                mainAxisSpacing: AppDimensions.md,
                childAspectRatio: 1.0,
              ),
              itemCount: state.products.length,
              itemBuilder: (context, index) {
                final product = state.products[index];
                return DsProductCard(
                  id: product.id.toString(),
                  imageUrl: product.imageUrl.isNotEmpty 
                      ? product.imageUrl 
                      : 'assets/images/drsudani.png',
                  name: product.name,
                  startingPrice: double.tryParse(product.price) ?? 0.0,
                  onTap: () {
                    context.push('/product/${product.id}');
                  },
                );
              },
            );
          }
          return const SizedBox.shrink();
        },
      ),
    );
  }
}
