import 'package:equatable/equatable.dart';
import '../../../product/data/models/product_model.dart';
import '../../data/models/article_model.dart';

abstract class HomeState extends Equatable {
  @override
  List<Object?> get props => [];
}

class HomeInitial extends HomeState {}

class HomeLoading extends HomeState {}

class HomeLoaded extends HomeState {
  final List<String> banners;
  final List<String> ads;
  final List<ProductModel> popularProducts;
  final List<ProductModel> allProducts;
  final List<ProductModel> onSaleProducts;
  final List<ProductModel> randomProducts;
  final List<ProductCategory> categories;
  final List<ArticleModel> articles;

  HomeLoaded({
    required this.banners,
    required this.ads,
    required this.popularProducts,
    required this.allProducts,
    required this.onSaleProducts,
    required this.randomProducts,
    required this.categories,
    required this.articles,
  });

  @override
  List<Object> get props => [banners, ads, popularProducts, allProducts, onSaleProducts, randomProducts, categories, articles];
}

class HomeError extends HomeState {
  final String message;
  HomeError(this.message);

  @override
  List<Object> get props => [message];
}
