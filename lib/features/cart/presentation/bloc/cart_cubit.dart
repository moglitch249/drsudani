import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:hive_flutter/hive_flutter.dart';
import 'package:injectable/injectable.dart';
import '../../data/models/cart_item_model.dart';
import 'cart_state.dart';

@lazySingleton
class CartCubit extends Cubit<CartState> {
  CartCubit() : super(CartInitial()) {
    loadCart();
  }

  Future<void> loadCart() async {
    try {
      final box = await Hive.openBox('cartBox');
      final List<dynamic>? rawItems = box.get('items');
      
      if (rawItems != null) {
        final List<CartItemModel> items = rawItems
            .map((item) => CartItemModel.fromMap(Map<String, dynamic>.from(item)))
            .toList();
        _emitLoaded(items);
      } else {
        _emitLoaded([]);
      }
    } catch (e) {
      emit(CartError('Failed to load cart: $e'));
    }
  }

  Future<void> addToCart(CartItemModel newItem) async {
    try {
      final currentState = state;
      List<CartItemModel> items = [];
      if (currentState is CartLoaded) {
        items = List.from(currentState.items);
      }

      // Check if item already exists (same product and variation)
      final existingIndex = items.indexWhere((item) => 
        item.product.id == newItem.product.id && 
        item.selectedVariation == newItem.selectedVariation &&
        item.customField == newItem.customField
      );

      if (existingIndex >= 0) {
        final existingItem = items[existingIndex];
        items[existingIndex] = CartItemModel(
          product: existingItem.product,
          quantity: existingItem.quantity + newItem.quantity,
          selectedVariation: existingItem.selectedVariation,
          price: existingItem.price,
          regularPrice: existingItem.regularPrice,
          customField: newItem.customField.isNotEmpty ? newItem.customField : existingItem.customField,
          customAddons: newItem.customAddons ?? existingItem.customAddons,
        );
      } else {
        items.add(newItem);
      }

      await _saveCart(items);
    } catch (e) {
      emit(CartError('Failed to add to cart: $e'));
    }
  }

  Future<void> removeFromCart(int index) async {
    try {
      if (state is CartLoaded) {
        final items = List<CartItemModel>.from((state as CartLoaded).items);
        items.removeAt(index);
        await _saveCart(items);
      }
    } catch (e) {
      emit(CartError('Failed to remove from cart: $e'));
    }
  }

  Future<void> updateQuantity(int index, int newQuantity) async {
    try {
      if (state is CartLoaded) {
        final items = List<CartItemModel>.from((state as CartLoaded).items);
        if (newQuantity <= 0) {
          items.removeAt(index);
        } else {
          final existingItem = items[index];
          items[index] = CartItemModel(
            product: existingItem.product,
            quantity: newQuantity,
            selectedVariation: existingItem.selectedVariation,
            price: existingItem.price,
            regularPrice: existingItem.regularPrice,
            customField: existingItem.customField,
            customAddons: existingItem.customAddons,
          );
        }
        await _saveCart(items);
      }
    } catch (e) {
      emit(CartError('Failed to update quantity: $e'));
    }
  }

  Future<void> clearCart() async {
    try {
      await _saveCart([]);
    } catch (e) {
      emit(CartError('Failed to clear cart: $e'));
    }
  }

  Future<void> _saveCart(List<CartItemModel> items) async {
    final box = await Hive.openBox('cartBox');
    final List<Map<String, dynamic>> mapList = items.map((e) => e.toMap()).toList();
    await box.put('items', mapList);
    _emitLoaded(items);
  }

  void _emitLoaded(List<CartItemModel> items) {
    double subTotal = items.fold(0, (sum, item) => sum + (item.regularPrice * item.quantity));
    double totalAmount = items.fold(0, (sum, item) => sum + (item.price * item.quantity));
    double totalDiscount = subTotal > totalAmount ? subTotal - totalAmount : 0.0;
    
    emit(CartLoaded(
      items: items, 
      subTotal: subTotal,
      totalDiscount: totalDiscount,
      totalAmount: totalAmount,
    ));
  }
}
