import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:injectable/injectable.dart';
import '../../../../core/network/api_service.dart';
import '../../data/models/product_model.dart';
import 'product_state.dart';

@injectable
class ProductCubit extends Cubit<ProductState> {
  final ApiService _apiService;

  ProductCubit(this._apiService) : super(ProductInitial());

  Future<void> loadProducts({int? categoryId, String? search}) async {
    emit(ProductLoading());
    try {
      final response = await _apiService.getProducts(categoryId: categoryId, search: search);
      
      if (response.statusCode == 200) {
        final List<dynamic> data = response.data;
        final products = data.map((json) => ProductModel.fromJson(json)).toList();
        emit(ProductsLoaded(products: products));
      } else {
        emit(ProductError('Failed to load products: ${response.statusCode}'));
      }
    } catch (e) {
      emit(ProductError('Network error: $e'));
    }
  }

  Future<void> getProducts({int? categoryId}) => loadProducts(categoryId: categoryId);

  Future<void> getProductById(int id) async {
    emit(ProductLoading());
    try {
      final response = await _apiService.getProductDetail(id);
      if (response.statusCode == 200) {
        final product = ProductModel.fromJson(response.data);
        
        List<ProductVariationModel> variations = [];
        if (product.type == 'variable') {
          try {
            final varResponse = await _apiService.getProductVariations(id);
            if (varResponse.statusCode == 200) {
              final List<dynamic> varData = varResponse.data;
              variations = varData.map((json) => ProductVariationModel.fromJson(json)).toList();
            }
          } catch (_) {
            // Ignore variation fetch error
          }
        }
        
        emit(ProductDetailLoaded(product, variations: variations));
      } else {
        emit(ProductError('Failed to load product details'));
      }
    } catch (e) {
      emit(ProductError('Network error: $e'));
    }
  }
}
