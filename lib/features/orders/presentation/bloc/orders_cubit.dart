import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:equatable/equatable.dart';
import '../../../../core/network/api_service.dart';
import '../../data/models/order_model.dart';

// --- States ---
abstract class OrdersState extends Equatable {
  const OrdersState();
  @override
  List<Object> get props => [];
}

class OrdersInitial extends OrdersState {}

class OrdersLoading extends OrdersState {}

class OrdersLoaded extends OrdersState {
  final List<OrderModel> orders;
  const OrdersLoaded(this.orders);
  @override
  List<Object> get props => [orders];
}

class OrdersError extends OrdersState {
  final String message;
  const OrdersError(this.message);
  @override
  List<Object> get props => [message];
}

// --- Cubit ---
class OrdersCubit extends Cubit<OrdersState> {
  final ApiService apiService;
  final int customerId;

  OrdersCubit({required this.apiService, required this.customerId})
      : super(OrdersInitial());

  Future<void> fetchOrders() async {
    emit(OrdersLoading());
    try {
      final response = await apiService.getCustomerOrders(customerId: customerId);
      if (response.statusCode == 200) {
        final List<dynamic> data = response.data;
        final orders = data.map((e) => OrderModel.fromMap(Map<String, dynamic>.from(e))).toList();
        emit(OrdersLoaded(orders));
      } else {
        emit(const OrdersError('فشل تحميل الطلبات'));
      }
    } catch (e) {
      emit(OrdersError('خطأ: $e'));
    }
  }

  List<OrderModel> filterByStatus(List<OrderModel> orders, String? status) {
    if (status == null) return orders;
    return orders.where((o) => o.status == status).toList();
  }
}
