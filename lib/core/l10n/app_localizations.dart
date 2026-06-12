import 'package:flutter/material.dart';

class AppLocalizations {
  final Locale locale;

  AppLocalizations(this.locale);

  static AppLocalizations? of(BuildContext context) {
    return Localizations.of<AppLocalizations>(context, AppLocalizations);
  }

  static const LocalizationsDelegate<AppLocalizations> delegate = _AppLocalizationsDelegate();

  static final Map<String, Map<String, String>> _localizedValues = {
    'en': {
      'appName': 'Dr. Sudani',
      'home': 'Home',
      'shop': 'Shop',
      'orders': 'Orders',
      'settings': 'Settings',
      'profile': 'Profile',
      'all': 'All',
      'pending': 'Pending',
      'completed': 'Completed',
      'cancelled': 'Cancelled',
      'noOrdersYet': 'No orders yet',
      'noOrdersSubtitle': 'You haven\'t placed any orders yet. Start shopping now!',
      'myWallet': 'My Wallet',
      'walletBalance': 'Current Balance',
      'myOrders': 'My Orders',
      'trackOrders': 'Track and manage your orders',
      'editProfile': 'Edit Profile',
      'editProfileSubtitle': 'Your details and password',
      'logout': 'Logout',
      'logoutSubtitle': 'Sign out of your account',
      'welcome': 'Welcome',
      'goodEvening': 'Good Evening',
      'walletNumber': 'Wallet Number',
      'manageAccount': 'Manage Account',
      'activeAccount': 'Active Account',
      'balance': 'Balance',
      'shopByCategory': 'Shop by Category',
      'mostPopular': 'Most Popular',
      'allProducts': 'All Products',
      'latestArticles': 'Latest Articles',
      'viewAll': 'View All',
      'connectionError': 'Connection Error',
      'retry': 'Retry',
      'noOffers': 'No offers currently',
      'searchGames': 'Search for games...',
      'buy': 'Buy',
      'categoryAll': 'All',
      'categoryDirectRecharge': 'Direct Recharge',
      'categoryGameCards': 'Game Cards',
      'categoryElectronicPayment': 'Electronic Payment',
      'contactWhatsApp': 'Contact via WhatsApp',
      'faq': 'FAQ',
      'aboutApp': 'About App',
      'aboutDrSudani': 'About Dr. Sudani',
      'privacyPolicy': 'Privacy Policy',
      'termsOfUse': 'Terms of Use',
      'version': 'Version',
      'appPreferences': 'App Preferences',
      'darkMode': 'Dark Mode',
      'support': 'Support',
      'productDescription': 'Product Description',
      'selectPackage': 'Select Package / Category',
      'playerId': 'Player ID',
      'enterPlayerId': 'Enter Player ID',
      'fastBuy': 'Buy Now',
      'addToCart': 'Add to Cart',
      'addedToCart': 'Added to cart successfully!',
      'pleaseSelectPackage': 'Please select a package',
      'pleaseEnter': 'Please enter',
      'cart': 'Cart',
      'cartEmpty': 'Cart is empty',
      'cartEmptyDesc': 'You haven\'t added any products to the cart yet.',
      'total': 'Total',
      'checkout': 'Checkout',
      'contactInfo': 'Contact Information',
      'phone': 'Phone Number',
      'email': 'Email',
      'required': 'Required',
      'orderSummary': 'Order Summary',
      'confirmPayment': 'Confirm Payment',
      'orderSuccess': 'Order submitted successfully!',
      'insufficientBalance': 'Sorry, wallet balance is insufficient! Please recharge.',
      'paymentWallet': 'Wallet',
    },
    'ar': {
      'appName': 'دكتور سوداني',
      'home': 'الرئيسية',
      'shop': 'المتجر',
      'orders': 'طلباتي',
      'settings': 'الإعدادات',
      'profile': 'حسابي',
      'all': 'الكل',
      'pending': 'قيد الانتظار',
      'completed': 'مكتملة',
      'cancelled': 'ملغاة',
      'noOrdersYet': 'لا توجد طلبات حالياً',
      'noOrdersSubtitle': 'لم تقم بإجراء أي طلبات حتى الآن. ابدأ التسوق الآن!',
      'myWallet': 'محفظتي',
      'walletBalance': 'الرصيد الحالي',
      'myOrders': 'طلباتي',
      'trackOrders': 'تتبع وإدارة طلباتك',
      'editProfile': 'تعديل الحساب',
      'editProfileSubtitle': 'بياناتك وكلمة المرور',
      'logout': 'تسجيل الخروج',
      'logoutSubtitle': 'الخروج من حسابك',
      'welcome': 'مرحباً',
      'goodEvening': 'مساء النور',
      'walletNumber': 'رقم المحفظة',
      'manageAccount': 'إدارة حسابي',
      'activeAccount': 'حساب نشط',
      'balance': 'الرصيد',
      'shopByCategory': 'تسوق حسب القسم',
      'mostPopular': 'الأكثر شهرة',
      'allProducts': 'كل المنتجات',
      'latestArticles': 'أحدث المقالات',
      'viewAll': 'عرض الكل',
      'connectionError': 'خطأ في الاتصال',
      'retry': 'إعادة المحاولة',
      'noOffers': 'لا توجد عروض حالياً',
      'searchGames': 'ابحث عن ألعاب...',
      'buy': 'شراء',
      'categoryAll': 'الكل',
      'categoryDirectRecharge': 'شحن مباشر',
      'categoryGameCards': 'بطاقات ألعاب',
      'categoryElectronicPayment': 'دفع إلكتروني',
      'contactWhatsApp': 'تواصل عبر واتساب',
      'faq': 'الأسئلة الشائعة',
      'aboutApp': 'عن التطبيق',
      'aboutDrSudani': 'عن دكتور سوداني',
      'privacyPolicy': 'سياسة الخصوصية',
      'termsOfUse': 'شروط الاستخدام',
      'version': 'إصدار',
      'appPreferences': 'تفضيلات التطبيق',
      'darkMode': 'الوضع الداكن',
      'support': 'الدعم الفني',
      'productDescription': 'وصف المنتج',
      'selectPackage': 'اختر الباقة / الفئة',
      'playerId': 'أيدي اللاعب (Player ID)',
      'enterPlayerId': 'أدخل الأيدي...',
      'fastBuy': 'اشتري الآن',
      'addToCart': 'إضافة للسلة',
      'addedToCart': 'تمت الإضافة إلى السلة بنجاح!',
      'pleaseSelectPackage': 'الرجاء اختيار باقة',
      'pleaseEnter': 'الرجاء إدخال',
      'cart': 'السلة',
      'cartEmpty': 'السلة فارغة',
      'cartEmptyDesc': 'لم تقم بإضافة أي منتجات إلى السلة بعد.',
      'total': 'الإجمالي',
      'checkout': 'إتمام الطلب',
      'contactInfo': 'معلومات التواصل',
      'phone': 'رقم الهاتف',
      'email': 'البريد الإلكتروني',
      'required': 'مطلوب',
      'orderSummary': 'ملخص الطلب',
      'confirmPayment': 'تأكيد الدفع',
      'orderSuccess': 'تم إرسال الطلب بنجاح!',
      'insufficientBalance': 'عذراً، رصيد المحفظة غير كافٍ لإتمام الطلب! الرجاء شحن المحفظة.',
      'paymentWallet': 'المحفظة',
    },
  };

  String get appName => _localizedValues[locale.languageCode]!['appName']!;
  String get home => _localizedValues[locale.languageCode]!['home']!;
  String get shop => _localizedValues[locale.languageCode]!['shop']!;
  String get orders => _localizedValues[locale.languageCode]!['orders']!;
  String get settings => _localizedValues[locale.languageCode]!['settings']!;
  String get profile => _localizedValues[locale.languageCode]!['profile']!;
  String get all => _localizedValues[locale.languageCode]!['all']!;
  String get pending => _localizedValues[locale.languageCode]!['pending']!;
  String get completed => _localizedValues[locale.languageCode]!['completed']!;
  String get cancelled => _localizedValues[locale.languageCode]!['cancelled']!;
  String get noOrdersYet => _localizedValues[locale.languageCode]!['noOrdersYet']!;
  String get noOrdersSubtitle => _localizedValues[locale.languageCode]!['noOrdersSubtitle']!;
  String get myWallet => _localizedValues[locale.languageCode]!['myWallet']!;
  String get walletBalance => _localizedValues[locale.languageCode]!['walletBalance']!;
  String get myOrders => _localizedValues[locale.languageCode]!['myOrders']!;
  String get trackOrders => _localizedValues[locale.languageCode]!['trackOrders']!;
  String get editProfile => _localizedValues[locale.languageCode]!['editProfile']!;
  String get editProfileSubtitle => _localizedValues[locale.languageCode]!['editProfileSubtitle']!;
  String get logout => _localizedValues[locale.languageCode]!['logout']!;
  String get logoutSubtitle => _localizedValues[locale.languageCode]!['logoutSubtitle']!;
  String get welcome => _localizedValues[locale.languageCode]!['welcome']!;
  String get goodEvening => _localizedValues[locale.languageCode]!['goodEvening']!;
  String get walletNumber => _localizedValues[locale.languageCode]!['walletNumber']!;
  String get manageAccount => _localizedValues[locale.languageCode]!['manageAccount']!;
  String get activeAccount => _localizedValues[locale.languageCode]!['activeAccount']!;
  String get balance => _localizedValues[locale.languageCode]!['balance']!;
  String get shopByCategory => _localizedValues[locale.languageCode]!['shopByCategory']!;
  String get mostPopular => _localizedValues[locale.languageCode]!['mostPopular']!;
  String get allProducts => _localizedValues[locale.languageCode]!['allProducts']!;
  String get latestArticles => _localizedValues[locale.languageCode]!['latestArticles']!;
  String get viewAll => _localizedValues[locale.languageCode]!['viewAll']!;
  String get connectionError => _localizedValues[locale.languageCode]!['connectionError']!;
  String get retry => _localizedValues[locale.languageCode]!['retry']!;
  String get noOffers => _localizedValues[locale.languageCode]!['noOffers']!;
  String get searchGames => _localizedValues[locale.languageCode]!['searchGames']!;
  String get buy => _localizedValues[locale.languageCode]!['buy']!;
  String get categoryAll => _localizedValues[locale.languageCode]!['categoryAll']!;
  String get categoryDirectRecharge => _localizedValues[locale.languageCode]!['categoryDirectRecharge']!;
  String get categoryGameCards => _localizedValues[locale.languageCode]!['categoryGameCards']!;
  String get categoryElectronicPayment => _localizedValues[locale.languageCode]!['categoryElectronicPayment']!;
  String get contactWhatsApp => _localizedValues[locale.languageCode]!['contactWhatsApp']!;
  String get faq => _localizedValues[locale.languageCode]!['faq']!;
  String get aboutApp => _localizedValues[locale.languageCode]!['aboutApp']!;
  String get aboutDrSudani => _localizedValues[locale.languageCode]!['aboutDrSudani']!;
  String get privacyPolicy => _localizedValues[locale.languageCode]!['privacyPolicy']!;
  String get termsOfUse => _localizedValues[locale.languageCode]!['termsOfUse']!;
  String get version => _localizedValues[locale.languageCode]!['version']!;
  String get appPreferences => _localizedValues[locale.languageCode]!['appPreferences']!;
  String get darkMode => _localizedValues[locale.languageCode]!['darkMode']!;
  String get support => _localizedValues[locale.languageCode]!['support']!;
  String get productDescription => _localizedValues[locale.languageCode]!['productDescription']!;
  String get selectPackage => _localizedValues[locale.languageCode]!['selectPackage']!;
  String get playerId => _localizedValues[locale.languageCode]!['playerId']!;
  String get enterPlayerId => _localizedValues[locale.languageCode]!['enterPlayerId']!;
  String get fastBuy => _localizedValues[locale.languageCode]!['fastBuy']!;
  String get addToCart => _localizedValues[locale.languageCode]!['addToCart']!;
  String get addedToCart => _localizedValues[locale.languageCode]!['addedToCart']!;
  String get pleaseSelectPackage => _localizedValues[locale.languageCode]!['pleaseSelectPackage']!;
  String get pleaseEnter => _localizedValues[locale.languageCode]!['pleaseEnter']!;
  String get cart => _localizedValues[locale.languageCode]!['cart']!;
  String get cartEmpty => _localizedValues[locale.languageCode]!['cartEmpty']!;
  String get cartEmptyDesc => _localizedValues[locale.languageCode]!['cartEmptyDesc']!;
  String get total => _localizedValues[locale.languageCode]!['total']!;
  String get checkout => _localizedValues[locale.languageCode]!['checkout']!;
  String get contactInfo => _localizedValues[locale.languageCode]!['contactInfo']!;
  String get phone => _localizedValues[locale.languageCode]!['phone']!;
  String get email => _localizedValues[locale.languageCode]!['email']!;
  String get required => _localizedValues[locale.languageCode]!['required']!;
  String get orderSummary => _localizedValues[locale.languageCode]!['orderSummary']!;
  String get confirmPayment => _localizedValues[locale.languageCode]!['confirmPayment']!;
  String get orderSuccess => _localizedValues[locale.languageCode]!['orderSuccess']!;
  String get insufficientBalance => _localizedValues[locale.languageCode]!['insufficientBalance']!;
  String get paymentWallet => _localizedValues[locale.languageCode]!['paymentWallet']!;
}

class _AppLocalizationsDelegate extends LocalizationsDelegate<AppLocalizations> {
  const _AppLocalizationsDelegate();

  @override
  bool isSupported(Locale locale) => ['en', 'ar'].contains(locale.languageCode);

  @override
  Future<AppLocalizations> load(Locale locale) async {
    return AppLocalizations(locale);
  }

  @override
  bool shouldReload(_AppLocalizationsDelegate old) => false;
}
