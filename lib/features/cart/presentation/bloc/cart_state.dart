import 'package:equatable/equatable.dart';
import '../../data/models/cart_item_model.dart';

abstract class CartState extends Equatable {
  const CartState();

  @override
  List<Object> get props => [];
}

class CartInitial extends CartState {}

class CartLoading extends CartState {}

class CartLoaded extends CartState {
  final List<CartItemModel> items;
  final double subTotal;
  final double totalDiscount;
  final double totalAmount;

  const CartLoaded({
    required this.items, 
    required this.subTotal,
    required this.totalDiscount,
    required this.totalAmount,
  });

  @override
  List<Object> get props => [items, subTotal, totalDiscount, totalAmount];
}

class CartError extends CartState {
  final String message;

  const CartError(this.message);

  @override
  List<Object> get props => [message];
}
