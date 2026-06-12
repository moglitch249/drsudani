import 'package:dio/dio.dart';
import 'package:injectable/injectable.dart';
import '../constants/api_constants.dart';

@lazySingleton
class ApiService {
  final Dio dio;

  ApiService(this.dio);

  // --- Auth (JWT) ---
  Future<Response<dynamic>> login(Map<String, dynamic> body) {
    return dio.post(ApiConstants.jwtAuthPath, data: body);
  }

  Future<Response<dynamic>> validateToken() {
    return dio.post('${ApiConstants.jwtAuthPath}/validate');
  }

  // --- WooCommerce Endpoints ---
  Future<Response<dynamic>> getProducts({
    int? categoryId,
    int perPage = 20,
    int page = 1,
    bool? featured,
    bool? onSale,
    String? orderby,
    String? search,
  }) {
    final queryParameters = <String, dynamic>{
      'per_page': perPage,
      'page': page,
    };
    if (categoryId != null) {
      queryParameters['category'] = categoryId;
    }
    if (featured != null) {
      queryParameters['featured'] = featured;
    }
    if (onSale != null) {
      queryParameters['on_sale'] = onSale;
    }
    if (orderby != null) {
      queryParameters['orderby'] = orderby;
    }
    if (search != null && search.isNotEmpty) {
      queryParameters['search'] = search;
    }
    return dio.get('${ApiConstants.wcApiPath}/products', queryParameters: queryParameters);
  }

  Future<Response<dynamic>> getProductDetail(int id) {
    return dio.get('${ApiConstants.wcApiPath}/products/$id');
  }

  Future<Response<dynamic>> getProductVariations(int productId) {
    return dio.get('${ApiConstants.wcApiPath}/products/$productId/variations');
  }

  Future<Response<dynamic>> getCategories() {
    return dio.get('${ApiConstants.wcApiPath}/products/categories');
  }

  Future<Response<dynamic>> createSecureCheckout(Map<String, dynamic> body, {Map<String, String>? extraHeaders}) {
    return dio.post(
      '/wp-json/drsudani/v1/checkout', 
      data: body,
      options: extraHeaders != null ? Options(headers: extraHeaders) : null,
    );
  }

  Future<Response<dynamic>> getCustomerOrders({required int customerId}) {
    // We no longer need to pass customerId because the server extracts it securely from the JWT token!
    return dio.get('/wp-json/drsudani/v1/orders');
  }

  Future<Response<dynamic>> getCustomerProfile(int id) {
    return dio.get('${ApiConstants.wcApiPath}/customers/$id');
  }

  // --- WordPress General ---
  Future<Response<dynamic>> getPosts({int perPage = 5}) {
    return dio.get('/wp-json/wp/v2/posts', queryParameters: {
      'per_page': perPage,
      '_embed': true,
    });
  }

  // --- Tera Wallet (Custom Endpoint) ---
  Future<Response<dynamic>> getWalletData() {
    return dio.get('/wp-json/drsudani/v1/wallet');
  }
}
