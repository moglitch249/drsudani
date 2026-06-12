import '../../data/models/product_model.dart';

abstract class ProductState {}

class ProductInitial extends ProductState {}

class ProductLoading extends ProductState {}

class ProductsLoaded extends ProductState {
  final List<ProductModel> products;
  final bool hasReachedMax;

  ProductsLoaded({required this.products, this.hasReachedMax = false});
}

class ProductDetailLoaded extends ProductState {
  final ProductModel product;
  final List<ProductVariationModel> variations;

  ProductDetailLoaded(this.product, {this.variations = const []});
}

class ProductError extends ProductState {
  final String message;

  ProductError(this.message);
}
