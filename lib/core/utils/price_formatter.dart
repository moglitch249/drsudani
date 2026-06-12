class PriceFormatter {
  static String format(double price, {bool withDecimals = false}) {
    if (withDecimals) return price.toStringAsFixed(2);
    return price.toStringAsFixed(0);
  }

  static String formatWithCurrency(
    double price,
    bool isArabic, {
    bool withDecimals = false,
  }) {
    final currency = isArabic ? 'ج.س' : 'SDG';
    return '${format(price, withDecimals: withDecimals)} $currency';
  }
}
