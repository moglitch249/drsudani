import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:equatable/equatable.dart';
import 'package:hive_flutter/hive_flutter.dart';
import '../../../../core/network/api_service.dart';

// --- Notification Model ---
class AppNotification {
  final String id;
  final String title;
  final String body;
  final String type; // 'order_update', 'new_product', 'discount'
  final String date;
  final bool isRead;

  AppNotification({
    required this.id,
    required this.title,
    required this.body,
    required this.type,
    required this.date,
    this.isRead = false,
  });

  Map<String, dynamic> toMap() => {
    'id': id,
    'title': title,
    'body': body,
    'type': type,
    'date': date,
    'isRead': isRead,
  };

  factory AppNotification.fromMap(Map<String, dynamic> map) => AppNotification(
    id: map['id'] ?? '',
    title: map['title'] ?? '',
    body: map['body'] ?? '',
    type: map['type'] ?? '',
    date: map['date'] ?? '',
    isRead: map['isRead'] ?? false,
  );
}

// --- States ---
abstract class NotificationsState extends Equatable {
  const NotificationsState();
  @override
  List<Object> get props => [];
}

class NotificationsInitial extends NotificationsState {}

class NotificationsLoaded extends NotificationsState {
  final List<AppNotification> notifications;
  final int unreadCount;

  const NotificationsLoaded({required this.notifications, required this.unreadCount});

  @override
  List<Object> get props => [notifications, unreadCount];
}

// --- Cubit ---
class NotificationsCubit extends Cubit<NotificationsState> {
  final ApiService apiService;
  final int customerId;

  NotificationsCubit({required this.apiService, required this.customerId})
      : super(NotificationsInitial());

  Future<void> loadNotifications() async {
    try {
      final box = await Hive.openBox('notificationsBox');
      final List<dynamic>? rawNotifs = box.get('notifications');
      
      List<AppNotification> notifications = [];
      if (rawNotifs != null) {
        notifications = rawNotifs
            .map((n) => AppNotification.fromMap(Map<String, dynamic>.from(n)))
            .toList();
      }
      
      final unreadCount = notifications.where((n) => !n.isRead).length;
      emit(NotificationsLoaded(notifications: notifications, unreadCount: unreadCount));
    } catch (e) {
      emit(const NotificationsLoaded(notifications: [], unreadCount: 0));
    }
  }

  Future<void> checkForUpdates() async {
    try {
      if (customerId == 0) return;
      
      final response = await apiService.getCustomerOrders(customerId: customerId);
      if (response.statusCode == 200 && response.data is List) {
        final orders = response.data as List;
        final box = await Hive.openBox('notificationsBox');
        final List<dynamic>? rawNotifs = box.get('notifications');
        
        List<AppNotification> existing = [];
        if (rawNotifs != null) {
          existing = rawNotifs
              .map((n) => AppNotification.fromMap(Map<String, dynamic>.from(n)))
              .toList();
        }
        
        // Check cached order statuses
        final Map<String, dynamic>? cachedStatuses = box.get('orderStatuses') != null
            ? Map<String, dynamic>.from(box.get('orderStatuses'))
            : {};
        
        bool hasNew = false;
        
        for (var order in orders) {
          final orderId = order['id'].toString();
          final status = order['status'] ?? '';
          final previousStatus = cachedStatuses?[orderId];
          
          if (previousStatus != null && previousStatus != status) {
            // Status changed! Generate notification
            existing.insert(0, AppNotification(
              id: 'order_${orderId}_$status',
              title: _statusTitle(status),
              body: 'طلب #$orderId تم تحديثه إلى "${_statusLabel(status)}"',
              type: 'order_update',
              date: DateTime.now().toIso8601String(),
            ));
            hasNew = true;
          }
          
          cachedStatuses?[orderId] = status;
        }
        
        if (hasNew) {
          await box.put('notifications', existing.map((n) => n.toMap()).toList());
        }
        await box.put('orderStatuses', cachedStatuses);
        
        final unreadCount = existing.where((n) => !n.isRead).length;
        emit(NotificationsLoaded(notifications: existing, unreadCount: unreadCount));
      }
    } catch (e) {
      // silently fail on network errors
    }
  }

  Future<void> markAllAsRead() async {
    if (state is NotificationsLoaded) {
      final loaded = state as NotificationsLoaded;
      final updated = loaded.notifications.map((n) => AppNotification(
        id: n.id, title: n.title, body: n.body, type: n.type, date: n.date, isRead: true,
      )).toList();
      
      final box = await Hive.openBox('notificationsBox');
      await box.put('notifications', updated.map((n) => n.toMap()).toList());
      
      emit(NotificationsLoaded(notifications: updated, unreadCount: 0));
    }
  }

  Future<void> clearAll() async {
    final box = await Hive.openBox('notificationsBox');
    await box.put('notifications', []);
    emit(const NotificationsLoaded(notifications: [], unreadCount: 0));
  }

  String _statusTitle(String status) {
    switch (status) {
      case 'completed': return 'طلب مكتمل ✅';
      case 'processing': return 'طلب قيد المعالجة ⏳';
      case 'cancelled': return 'طلب ملغي ❌';
      case 'refunded': return 'طلب مسترد 💰';
      case 'on-hold': return 'طلب معلق ⏸️';
      default: return 'تحديث طلب 📦';
    }
  }

  String _statusLabel(String status) {
    switch (status) {
      case 'pending': return 'قيد الانتظار';
      case 'processing': return 'قيد المعالجة';
      case 'on-hold': return 'معلق';
      case 'completed': return 'مكتمل';
      case 'cancelled': return 'ملغي';
      case 'refunded': return 'مسترد';
      case 'failed': return 'فشل';
      default: return status;
    }
  }
}
