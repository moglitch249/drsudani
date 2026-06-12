import 'dart:convert';

class OrderModel {
  final int id;
  final String status;
  final String dateCreated;
  final String total;
  final List<dynamic> lineItems;

  OrderModel({
    required this.id,
    required this.status,
    required this.dateCreated,
    required this.total,
    required this.lineItems,
  });

  factory OrderModel.fromMap(Map<String, dynamic> map) {
    return OrderModel(
      id: map['id'] ?? 0,
      status: map['status'] ?? 'pending',
      dateCreated: map['date_created'] ?? '',
      total: map['total'] ?? '0.0',
      lineItems: map['line_items'] ?? [],
    );
  }
}
