class CheckoutItem {
  final int productId;
  final int? variationId;
  final String productName;
  final double price;
  final double regularPrice;
  final String playerId;
  final Map<String, dynamic>? addons;

  CheckoutItem({
    required this.productId,
    this.variationId,
    required this.productName,
    required this.price,
    required this.regularPrice,
    required this.playerId,
    this.addons,
  });
}
