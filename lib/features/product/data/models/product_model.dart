import 'package:html/parser.dart' show parse;

class ProductModel {
  final int id;
  final String name;
  final String description;
  final String type; // 'simple' or 'variable'
  final String price;
  final String regularPrice;
  final String salePrice;
  final String imageUrl;
  final List<ProductCategory> categories;
  final List<int> variations; // Array of variation IDs for variable products

  final List<ProductAddon> addons;
  final bool isBotAutoTopup;

  ProductModel({
    required this.id,
    required this.name,
    required this.description,
    required this.type,
    required this.price,
    required this.regularPrice,
    required this.salePrice,
    required this.imageUrl,
    required this.categories,
    required this.variations,
    required this.addons,
    required this.isBotAutoTopup,
  });

  factory ProductModel.fromJson(Map<String, dynamic> json) {
    // Strip HTML from description
    String rawDesc = json['description'] ?? '';
    String parsedDesc = parse(rawDesc).documentElement?.text ?? '';

    // Get primary image
    String imgUrl = '';
    if (json['images'] != null && (json['images'] as List).isNotEmpty) {
      imgUrl = json['images'][0]['src'] ?? '';
    }

    // Parse categories
    List<ProductCategory> cats = [];
    if (json['categories'] != null) {
      cats = (json['categories'] as List)
          .map((c) => ProductCategory.fromJson(c))
          .toList();
    }

    // Parse variations
    List<int> varList = [];
    if (json['variations'] != null) {
      varList = List<int>.from(json['variations']);
    }

    // Parse WooCommerce Product Addons
    List<ProductAddon> addonsList = [];
    if (json['meta_data'] != null) {
      final meta = json['meta_data'] as List;
      final addonsMeta = meta.firstWhere(
        (m) => m['key'] == '_product_addons',
        orElse: () => null,
      );
      if (addonsMeta != null && addonsMeta['value'] != null && addonsMeta['value'] is List) {
        final List<dynamic> addonsData = addonsMeta['value'];
        addonsList = addonsData.map((e) => ProductAddon.fromJson(Map<String, dynamic>.from(e))).toList();
      }
    }

    bool isBot = false;
    if (json['meta_data'] != null) {
      final metaList = json['meta_data'] as List;
      final botMeta = metaList.firstWhere(
        (m) => m['key'] == '_is_bot_auto_topup',
        orElse: () => null,
      );
      if (botMeta != null && botMeta['value'] == 'yes') {
        isBot = true;
      }
    }

    return ProductModel(
      id: json['id'] ?? 0,
      name: json['name'] ?? '',
      description: parsedDesc.trim(),
      type: json['type'] ?? 'simple',
      price: json['price'] ?? '0',
      regularPrice: json['regular_price'] ?? '0',
      salePrice: json['sale_price'] ?? '0',
      imageUrl: imgUrl,
      categories: cats,
      variations: varList,
      addons: addonsList,
      isBotAutoTopup: isBot,
    );
  }
}

class ProductCategory {
  final int id;
  final String name;
  final String imageUrl;

  ProductCategory({required this.id, required this.name, this.imageUrl = ''});

  factory ProductCategory.fromJson(Map<String, dynamic> json) {
    String imgUrl = '';
    
    // First try the official WooCommerce category image
    if (json['image'] != null && json['image']['src'] != null) {
      imgUrl = json['image']['src'];
    }
    
    // If not found, try to extract from description (user added via WP media)
    if (imgUrl.isEmpty && json['description'] != null) {
      final String desc = json['description'].toString();
      final RegExp imgRegex = RegExp(r'<img[^>]+src="([^">]+)"');
      final match = imgRegex.firstMatch(desc);
      if (match != null && match.groupCount >= 1) {
        imgUrl = match.group(1) ?? '';
      }
    }
    return ProductCategory(
      id: json['id'] ?? 0,
      name: json['name'] ?? '',
      imageUrl: imgUrl,
    );
  }
}

class ProductAddon {
  final String name;
  final String type; // 'custom_text', 'custom_textarea', 'select', etc.
  final bool required;
  final List<ProductAddonOption> options;

  ProductAddon({
    required this.name,
    required this.type,
    required this.required,
    required this.options,
  });

  factory ProductAddon.fromJson(Map<String, dynamic> json) {
    List<ProductAddonOption> opts = [];
    if (json['options'] != null) {
      opts = (json['options'] as List)
          .map((o) => ProductAddonOption.fromJson(Map<String, dynamic>.from(o)))
          .toList();
    }
    return ProductAddon(
      name: json['name'] ?? json['title'] ?? '',
      type: json['type'] ?? 'custom_text',
      required: json['required'] == 1 || json['required'] == true,
      options: opts,
    );
  }
}

class ProductAddonOption {
  final String label;
  final double price;
  final String priceType;

  ProductAddonOption({
    required this.label,
    required this.price,
    required this.priceType,
  });

  factory ProductAddonOption.fromJson(Map<String, dynamic> json) {
    return ProductAddonOption(
      label: json['label'] ?? '',
      price: double.tryParse((json['price'] ?? '0').toString()) ?? 0.0,
      priceType: json['price_type'] ?? '',
    );
  }
}

class ProductVariationModel {
  final int id;
  final String price;
  final String regularPrice;
  final String salePrice;
  final bool inStock;
  final Map<String, String> attributes;

  ProductVariationModel({
    required this.id,
    required this.price,
    required this.regularPrice,
    required this.salePrice,
    required this.inStock,
    required this.attributes,
  });

  factory ProductVariationModel.fromJson(Map<String, dynamic> json) {
    final Map<String, String> attrs = {};
    if (json['attributes'] != null) {
      for (var attr in json['attributes'] as List) {
        final name = attr['name'] ?? '';
        final option = attr['option'] ?? '';
        if (name.isNotEmpty) {
          attrs[name] = option;
        }
      }
    }
    return ProductVariationModel(
      id: json['id'] ?? 0,
      price: json['price'] ?? '0',
      regularPrice: json['regular_price'] ?? '0',
      salePrice: json['sale_price'] ?? '0',
      inStock: json['in_stock'] ?? true,
      attributes: attrs,
    );
  }
}
