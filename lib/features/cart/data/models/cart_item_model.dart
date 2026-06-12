import 'dart:convert';
import '../../../product/data/models/product_model.dart';

class CartItemModel {
  final ProductModel product;
  final int quantity;
  final String selectedVariation;
  final int? variationId;
  final double price;
  final double regularPrice;
  final String customField;
  final Map<String, dynamic>? customAddons;

  CartItemModel({
    required this.product,
    required this.quantity,
    required this.selectedVariation,
    this.variationId,
    required this.price,
    required this.regularPrice,
    this.customField = '',
    this.customAddons,
  });

  Map<String, dynamic> toMap() {
    return {
      'product': {
        'id': product.id,
        'name': product.name,
        'price': product.price,
        'regularPrice': product.regularPrice,
        'type': product.type,
        'imageUrl': product.imageUrl,
        'isBotAutoTopup': product.isBotAutoTopup,
        'categories': product.categories.map((c) => {'id': c.id, 'name': c.name}).toList(),
      },
      'quantity': quantity,
      'selectedVariation': selectedVariation,
      'variationId': variationId,
      'price': price,
      'regularPrice': regularPrice,
      'customField': customField,
      'customAddons': customAddons,
    };
  }

  factory CartItemModel.fromMap(Map<String, dynamic> map) {
    return CartItemModel(
      product: ProductModel(
        id: map['product']['id'] ?? 0,
        name: map['product']['name'] ?? '',
        description: '',
        type: map['product']['type'] ?? 'simple',
        price: map['product']['price']?.toString() ?? '0',
        regularPrice: map['product']['regularPrice']?.toString() ?? '0',
        salePrice: '0',
        imageUrl: map['product']['imageUrl'] ?? '',
        categories: map['product']['categories'] != null 
            ? (map['product']['categories'] as List).map((c) => ProductCategory(id: c['id'], name: c['name'])).toList() 
            : [],
        variations: [],
        addons: [],
        isBotAutoTopup: map['product']['isBotAutoTopup'] ?? false,
      ),
      quantity: map['quantity']?.toInt() ?? 1,
      selectedVariation: map['selectedVariation'] ?? '',
      variationId: map['variationId'],
      price: map['price']?.toDouble() ?? 0.0,
      regularPrice: map['regularPrice']?.toDouble() ?? map['price']?.toDouble() ?? 0.0,
      customField: map['customField'] ?? '',
      customAddons: map['customAddons'] != null ? Map<String, dynamic>.from(map['customAddons']) : null,
    );
  }

  String toJson() => json.encode(toMap());

  factory CartItemModel.fromJson(String source) => CartItemModel.fromMap(json.decode(source));
}
