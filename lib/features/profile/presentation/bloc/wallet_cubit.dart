import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:equatable/equatable.dart';
import 'package:hive_flutter/hive_flutter.dart';
import 'package:dio/dio.dart';
import 'package:flutter/foundation.dart';
import '../../../../core/network/api_service.dart';
import '../../../../core/constants/api_constants.dart';

class WalletTransaction extends Equatable {
  final String id;
  final String type;
  final String amount;
  final String balance;
  final String details;
  final String date;

  const WalletTransaction({
    required this.id,
    required this.type,
    required this.amount,
    required this.balance,
    required this.details,
    required this.date,
  });

  factory WalletTransaction.fromJson(Map<String, dynamic> json) {
    return WalletTransaction(
      id: json['transaction_id']?.toString() ?? '',
      type: json['type']?.toString() ?? '',
      amount: json['amount']?.toString() ?? '',
      balance: json['balance']?.toString() ?? '',
      details: json['details']?.toString() ?? '',
      date: json['date']?.toString() ?? '',
    );
  }

  @override
  List<Object?> get props => [id, type, amount, balance, details, date];
}

abstract class WalletState extends Equatable {
  @override
  List<Object?> get props => [];
}

class WalletInitial extends WalletState {}
class WalletLoading extends WalletState {}

class WalletLoaded extends WalletState {
  final double balance;
  final List<WalletTransaction> transactions;
  final String? firstName;

  WalletLoaded(this.balance, this.transactions, {this.firstName});

  @override
  List<Object?> get props => [balance, transactions, firstName];
}

class WalletError extends WalletState {
  final String message;
  WalletError(this.message);
  @override
  List<Object?> get props => [message];
}

class WalletCubit extends Cubit<WalletState> {
  final ApiService _apiService;

  WalletCubit(this._apiService) : super(WalletInitial());

  /// مساعد: جلب بيانات الرصيد من WooCommerce customer data
  double _extractBalance(dynamic customerData) {
    final metaList = customerData['meta_data'] as List?;
    if (metaList == null) return 0.0;

    for (final key in [
      'woo_wallet_current_balance',
      '_wwallet_balance',
      'wallet_balance',
      'tera_wallet_balance',
    ]) {
      try {
        final meta = metaList.cast<Map>().firstWhere(
          (m) => m['key'] == key,
          orElse: () => <String, dynamic>{},
        );
        if (meta.isNotEmpty) {
          final parsed = double.tryParse(
            meta['value'].toString().replaceAll(',', '').trim(),
          );
          if (parsed != null) {
            debugPrint('[Wallet] ✅ رصيد من "$key" = $parsed');
            return parsed;
          }
        }
      } catch (_) {}
    }
    return 0.0;
  }

  Future<void> fetchWalletData({String? authToken}) async {
    emit(WalletLoading());

    final token = authToken ?? Hive.box('auth').get('jwtToken')?.toString();
    if (token == null || token.isEmpty) {
      emit(WalletError('يرجى تسجيل الدخول أولاً.'));
      return;
    }

    final authBox = Hive.box('auth');
    final userEmail = authBox.get('userEmail')?.toString();
    final userId = authBox.get('userId');

    // === محاولة 1: PHP Plugin المخصص (الأفضل أمناً) ===
    try {
      debugPrint('[Wallet] ← Fetching balance from /drsudani/v1/wallet');
      final res = await _apiService.getWalletData();
      if (res.statusCode == 200 && res.data?['success'] == true) {
        final balance = double.tryParse(res.data['balance']?.toString() ?? '0') ?? 0.0;
        debugPrint('[Wallet] ✅ Balance retrieved: $balance');
        emit(WalletLoaded(balance, [], firstName: res.data['first_name']?.toString()));
        return;
      }
    } catch (e) {
      debugPrint('[Wallet] ⚠️ Failed to fetch wallet data: $e');
    }

    emit(WalletError(
      'تعذّر جلب الرصيد.\n'
      'تأكد من اتصالك بالإنترنت والمحاولة مجدداً.',
    ));
  }
}

