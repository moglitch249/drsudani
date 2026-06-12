import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';
import '../../../../core/network/api_service.dart';
import '../../../../core/theme/app_theme.dart';
import '../../../../core/theme/app_dimensions.dart';
import '../../../../core/widgets/ds_product_card.dart';
import '../../../../core/widgets/ds_app_bar.dart';
import '../../../../core/widgets/ds_empty_state.dart';
import '../../../../core/di/injection.dart';
import '../../../product/data/models/product_model.dart';
import 'package:shimmer/shimmer.dart';

class CategoryScreen extends StatefulWidget {
  final int categoryId;
  final String categoryName;

  const CategoryScreen({
    Key? key,
    required this.categoryId,
    required this.categoryName,
  }) : super(key: key);

  @override
  State<CategoryScreen> createState() => _CategoryScreenState();
}

class _CategoryScreenState extends State<CategoryScreen> {
  bool _isLoading = true;
  String _error = '';
  List<ProductModel> _products = [];

  @override
  void initState() {
    super.initState();
    _fetchProducts();
  }

  Future<void> _fetchProducts() async {
    try {
      final apiService = getIt<ApiService>();
      final response = await apiService.getProducts(categoryId: widget.categoryId, perPage: 30);
      final data = response.data as List<dynamic>;
      setState(() {
        _products = data.map((e) => ProductModel.fromJson(e)).toList();
        _isLoading = false;
      });
    } catch (e) {
      setState(() {
        _error = 'حدث خطأ أثناء جلب المنتجات: $e';
        _isLoading = false;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: CustomScrollView(
        slivers: [
          DsAppBar(
            title: widget.categoryName,
            leading: IconButton(
              icon: const Icon(Icons.arrow_back),
              onPressed: () => context.pop(),
            ),
          ),
          SliverToBoxAdapter(
            child: _isLoading
                ? _buildLoading()
                : _error.isNotEmpty
                    ? Center(child: Padding(padding: const EdgeInsets.all(20), child: Text(_error, style: const TextStyle(color: AppTheme.error))))
                    : _products.isEmpty
                        ? const DsEmptyState(
                            icon: Icons.inventory_2_outlined,
                            title: 'لا توجد منتجات',
                            subtitle: 'هذا التصنيف لا يحتوي على منتجات حالياً.',
                          )
                        : const SizedBox.shrink(),
          ),
          if (!_isLoading && _error.isEmpty && _products.isNotEmpty)
            SliverPadding(
              padding: const EdgeInsets.all(AppDimensions.lg),
              sliver: SliverGrid(
                gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                  crossAxisCount: 2,
                  childAspectRatio: 1.0,
                  crossAxisSpacing: AppDimensions.md,
                  mainAxisSpacing: AppDimensions.md,
                ),
                delegate: SliverChildBuilderDelegate(
                  (context, index) {
                    final product = _products[index];
                    return DsProductCard(
                      id: product.id.toString(),
                      imageUrl: product.imageUrl.isNotEmpty
                          ? product.imageUrl
                          : 'assets/images/drsudani.png',
                      name: product.name,
                      startingPrice: double.tryParse(product.price) ?? 0.0,
                      regularPrice: double.tryParse(product.regularPrice) ?? 0.0,
                      salePrice: double.tryParse(product.salePrice) ?? 0.0,
                      isPopular: false,
                      onTap: () {
                        context.push('/product/${product.id}');
                      },
                    );
                  },
                  childCount: _products.length,
                ),
              ),
            ),
        ],
      ),
    );
  }

  Widget _buildLoading() {
    return GridView.builder(
      shrinkWrap: true,
      physics: const NeverScrollableScrollPhysics(),
      padding: const EdgeInsets.all(AppDimensions.lg),
      gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
        crossAxisCount: 2,
        childAspectRatio: 1.0,
        crossAxisSpacing: AppDimensions.md,
        mainAxisSpacing: AppDimensions.md,
      ),
      itemCount: 6,
      itemBuilder: (context, index) => Shimmer.fromColors(
        baseColor: Colors.grey[300]!,
        highlightColor: Colors.grey[100]!,
        child: Container(
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(24),
          ),
        ),
      ),
    );
  }
}
