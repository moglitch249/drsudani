import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import '../../../../core/theme/app_theme.dart';
import '../../../../core/theme/app_dimensions.dart';
import '../../../../core/widgets/ds_empty_state.dart';
import '../../../../core/l10n/app_localizations.dart';
import '../bloc/notifications_cubit.dart';

class NotificationsScreen extends StatelessWidget {
  const NotificationsScreen({Key? key}) : super(key: key);

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context)!;
    final isArabic = l10n.locale.languageCode == 'ar';

    return Scaffold(
      backgroundColor: Theme.of(context).scaffoldBackgroundColor,
      appBar: AppBar(
        title: Text(
          isArabic ? 'الإشعارات' : 'Notifications',
          style: const TextStyle(fontWeight: FontWeight.bold),
        ),
        centerTitle: true,
        actions: [
          BlocBuilder<NotificationsCubit, NotificationsState>(
            builder: (context, state) {
              if (state is NotificationsLoaded && state.notifications.isNotEmpty) {
                return PopupMenuButton<String>(
                  icon: const Icon(Icons.more_vert),
                  onSelected: (value) {
                    if (value == 'read_all') {
                      context.read<NotificationsCubit>().markAllAsRead();
                    } else if (value == 'clear_all') {
                      context.read<NotificationsCubit>().clearAll();
                    }
                  },
                  itemBuilder: (context) => [
                    PopupMenuItem(
                      value: 'read_all',
                      child: Row(
                        children: [
                          const Icon(Icons.done_all, size: 18, color: AppTheme.primary),
                          const SizedBox(width: 8),
                          Text(isArabic ? 'تعيين الكل كمقروء' : 'Mark all as read'),
                        ],
                      ),
                    ),
                    PopupMenuItem(
                      value: 'clear_all',
                      child: Row(
                        children: [
                          const Icon(Icons.delete_sweep, size: 18, color: AppTheme.error),
                          const SizedBox(width: 8),
                          Text(isArabic ? 'مسح الكل' : 'Clear all'),
                        ],
                      ),
                    ),
                  ],
                );
              }
              return const SizedBox.shrink();
            },
          ),
        ],
      ),
      body: BlocBuilder<NotificationsCubit, NotificationsState>(
        builder: (context, state) {
          if (state is NotificationsLoaded) {
            if (state.notifications.isEmpty) {
              return Center(
                child: DsEmptyState(
                  icon: Icons.notifications_none,
                  title: isArabic ? 'لا توجد إشعارات' : 'No notifications',
                  subtitle: isArabic
                      ? 'ستظهر هنا إشعارات عندما يتغير حالة طلباتك'
                      : 'Notifications will appear here when your order status changes',
                ),
              );
            }

            return RefreshIndicator(
              onRefresh: () => context.read<NotificationsCubit>().checkForUpdates(),
              color: AppTheme.primary,
              child: ListView.builder(
                padding: const EdgeInsets.all(AppDimensions.lg),
                itemCount: state.notifications.length,
                itemBuilder: (context, index) {
                  final notif = state.notifications[index];
                  return _NotificationCard(notification: notif, isArabic: isArabic);
                },
              ),
            );
          }
          return const Center(child: CircularProgressIndicator(color: AppTheme.primary));
        },
      ),
    );
  }
}

class _NotificationCard extends StatelessWidget {
  final AppNotification notification;
  final bool isArabic;

  const _NotificationCard({Key? key, required this.notification, required this.isArabic}) : super(key: key);

  IconData _getIcon() {
    switch (notification.type) {
      case 'order_update':
        return Icons.local_shipping_outlined;
      case 'new_product':
        return Icons.new_releases_outlined;
      case 'discount':
        return Icons.discount_outlined;
      default:
        return Icons.notifications_outlined;
    }
  }

  Color _getColor() {
    switch (notification.type) {
      case 'order_update':
        return AppTheme.primary;
      case 'new_product':
        return const Color(0xFF00B894);
      case 'discount':
        return const Color(0xFFF39C12);
      default:
        return AppTheme.primary;
    }
  }

  String _formatDate(String dateStr) {
    try {
      final date = DateTime.parse(dateStr);
      final now = DateTime.now();
      final diff = now.difference(date);

      if (diff.inMinutes < 60) {
        return isArabic ? 'منذ ${diff.inMinutes} دقيقة' : '${diff.inMinutes}m ago';
      } else if (diff.inHours < 24) {
        return isArabic ? 'منذ ${diff.inHours} ساعة' : '${diff.inHours}h ago';
      } else if (diff.inDays < 7) {
        return isArabic ? 'منذ ${diff.inDays} يوم' : '${diff.inDays}d ago';
      } else {
        return '${date.year}-${date.month.toString().padLeft(2, '0')}-${date.day.toString().padLeft(2, '0')}';
      }
    } catch (e) {
      return dateStr;
    }
  }

  @override
  Widget build(BuildContext context) {
    final color = _getColor();

    return Container(
      margin: const EdgeInsets.only(bottom: AppDimensions.md),
      decoration: BoxDecoration(
        color: notification.isRead
            ? Theme.of(context).cardColor
            : color.withOpacity(0.04),
        borderRadius: BorderRadius.circular(AppDimensions.radiusLg),
        border: notification.isRead
            ? null
            : Border.all(color: color.withOpacity(0.15)),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withOpacity(0.03),
            blurRadius: 8,
            offset: const Offset(0, 2),
          ),
        ],
      ),
      child: Padding(
        padding: const EdgeInsets.all(AppDimensions.md),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Container(
              padding: const EdgeInsets.all(10),
              decoration: BoxDecoration(
                color: color.withOpacity(0.1),
                borderRadius: BorderRadius.circular(12),
              ),
              child: Icon(_getIcon(), color: color, size: 22),
            ),
            const SizedBox(width: AppDimensions.md),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      Expanded(
                        child: Text(
                          notification.title,
                          style: TextStyle(
                            fontWeight: notification.isRead ? FontWeight.w500 : FontWeight.bold,
                            fontSize: 14,
                            color: Theme.of(context).colorScheme.onSurface,
                          ),
                        ),
                      ),
                      if (!notification.isRead)
                        Container(
                          width: 8,
                          height: 8,
                          decoration: BoxDecoration(
                            color: color,
                            shape: BoxShape.circle,
                          ),
                        ),
                    ],
                  ),
                  const SizedBox(height: 4),
                  Text(
                    notification.body,
                    style: TextStyle(
                      fontSize: 13,
                      color: Theme.of(context).colorScheme.onSurface.withOpacity(0.7),
                    ),
                  ),
                  const SizedBox(height: 6),
                  Text(
                    _formatDate(notification.date),
                    style: TextStyle(
                      fontSize: 11,
                      color: Theme.of(context).textTheme.bodySmall?.color,
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}
