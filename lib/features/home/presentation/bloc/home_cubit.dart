import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:injectable/injectable.dart';
import '../../../../core/network/api_service.dart';
import '../../../product/data/models/product_model.dart';
import '../../data/models/article_model.dart';
import 'home_state.dart';

@injectable
class HomeCubit extends Cubit<HomeState> {
  final ApiService _apiService;

  HomeCubit(this._apiService) : super(HomeInitial());

  Future<void> loadHomeData() async {
    emit(HomeLoading());
    try {
      // Use Future.wait to fetch everything concurrently
      final responses = await Future.wait([
        _apiService.dio.get('/wp-json/wp/v2/media', queryParameters: {'search': 'banner', 'per_page': 5}), // Hero Banners
        _apiService.dio.get('/wp-json/wp/v2/media', queryParameters: {'search': 'adds', 'per_page': 5}), // Ads Carousels
        _apiService.getProducts(orderby: 'popularity', perPage: 10), // Popular
        _apiService.getProducts(onSale: true, perPage: 10), // On Sale (Exclusive Offers)
        _apiService.getProducts(orderby: 'date', perPage: 20), // Random/All Products
      ]);

      // Banners (Media containing 'banner')
      final bannerData = responses[0].data as List<dynamic>;
      List<String> banners = [];
      for (var b in bannerData) {
        if (b['source_url'] != null) {
          banners.add(b['source_url']);
        }
      }

      // Ads (Media containing 'adds')
      final adsData = responses[1].data as List<dynamic>;
      List<String> ads = [];
      for (var a in adsData) {
        if (a['source_url'] != null) {
          ads.add(a['source_url']);
        }
      }

      // Popular
      final popularData = responses[2].data as List<dynamic>;
      final popularProducts = popularData.map((e) => ProductModel.fromJson(e)).toList();

      // On Sale
      final onSaleData = responses[3].data as List<dynamic>;
      final onSaleProducts = onSaleData.map((e) => ProductModel.fromJson(e)).toList();

      // All / Random
      final allData = responses[4].data as List<dynamic>;
      final allProducts = allData.map((e) => ProductModel.fromJson(e)).toList();
      
      // Create a shuffled copy for random suggestions
      final randomProducts = List<ProductModel>.from(allProducts)..shuffle();

      // Fetch Categories
      final catResponse = await _apiService.getCategories();
      List<ProductCategory> categories = [];
      if (catResponse.statusCode == 200) {
        categories = (catResponse.data as List).map((e) => ProductCategory.fromJson(e)).toList();
      }

      // Fetch Articles
      List<ArticleModel> articles = [];
      try {
        final postResponse = await _apiService.getPosts(perPage: 5);
        if (postResponse.statusCode == 200) {
          articles = (postResponse.data as List).map((e) => ArticleModel.fromJson(e)).toList();
        }
      } catch (e) {
        // Fallback or ignore if articles fail so it doesn't break home
      }

      emit(HomeLoaded(
        banners: banners,
        ads: ads,
        popularProducts: popularProducts,
        allProducts: allProducts,
        onSaleProducts: onSaleProducts,
        randomProducts: randomProducts,
        categories: categories,
        articles: articles,
      ));
    } catch (e) {
      emit(HomeError('فشل في تحميل بيانات الصفحة الرئيسية: $e'));
    }
  }
}
