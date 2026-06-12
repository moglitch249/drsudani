// ignore: unused_import
import 'package:intl/intl.dart' as intl;
import 'app_localizations.dart';

// ignore_for_file: type=lint

/// The translations for English (`en`).
class AppLocalizationsEn extends AppLocalizations {
  AppLocalizationsEn([String locale = 'en']) : super(locale);

  @override
  String get appName => 'Dr. Sudani';

  @override
  String get home => 'Home';

  @override
  String get shop => 'Shop';

  @override
  String get orders => 'Orders';

  @override
  String get settings => 'Settings';

  @override
  String get profile => 'Profile';

  @override
  String get all => 'All';

  @override
  String get pending => 'Pending';

  @override
  String get completed => 'Completed';

  @override
  String get cancelled => 'Cancelled';

  @override
  String get noOrdersYet => 'No orders yet';

  @override
  String get noOrdersSubtitle =>
      'You haven\'t placed any orders yet. Start shopping now!';

  @override
  String get myWallet => 'My Wallet';

  @override
  String get walletBalance => 'Current Balance';

  @override
  String get myOrders => 'My Orders';

  @override
  String get trackOrders => 'Track and manage your orders';

  @override
  String get editProfile => 'Edit Profile';

  @override
  String get editProfileSubtitle => 'Your details and password';

  @override
  String get logout => 'Logout';

  @override
  String get logoutSubtitle => 'Sign out of your account';

  @override
  String get welcome => 'Welcome';

  @override
  String get goodEvening => 'Good Evening';

  @override
  String get walletNumber => 'Wallet Number';

  @override
  String get manageAccount => 'Manage Account';
}
