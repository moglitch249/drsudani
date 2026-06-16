import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import '../../../../core/theme/app_theme.dart';
import '../../../../core/theme/app_dimensions.dart';
import '../../../../core/widgets/ds_status_badge.dart';
import '../../../../core/widgets/ds_empty_state.dart';
import '../../../../core/l10n/app_localizations.dart';
import '../../../../core/network/api_service.dart';
import '../../../../core/di/injection.dart';
import '../../../auth/presentation/bloc/auth_bloc.dart';
import '../../../auth/presentation/bloc/auth_state_event.dart';
import '../../../auth/data/datasources/auth_local_data_source.dart';
import '../bloc/orders_cubit.dart';
import '../../data/models/order_model.dart';
import 'package:go_router/go_router.dart';

class OrdersScreen extends StatelessWidget {
  const OrdersScreen({Key? key}) : super(key: key);

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context)!;
    return BlocBuilder<AuthBloc, AuthState>(
      builder: (context, authState) {
        int customerId = 0;
        if (authState is AuthSuccess) {
          customerId = authState.user.id;
        }

        return BlocProvider(
          key: ValueKey(customerId),
          create: (context) {
            if (customerId != 0) {
              return OrdersCubit(
                apiService: getIt<ApiService>(),
                customerId: customerId,
              )..fetchOrders();
            } else {
              return OrdersCubit(apiService: getIt<ApiService>(), customerId: 0);
            }
          },
          child: DefaultTabController(
            length: 4,
            child: Scaffold(
              backgroundColor: Theme.of(context).scaffoldBackgroundColor,
              appBar: AppBar(
                title: Text(l10n.myOrders, style: const TextStyle(fontWeight: FontWeight.bold)),
                centerTitle: true,
                bottom: TabBar(
                  isScrollable: true,
                  indicatorColor: AppTheme.primary,
                  indicatorWeight: 3,
                  dividerColor: Colors.transparent,
                  labelColor: AppTheme.primary,
                  unselectedLabelColor: Theme.of(context).textTheme.bodySmall?.color,
                  tabs: [
                    Tab(text: l10n.all),
                    Tab(text: l10n.pending),
                    Tab(text: l10n.completed),
                    Tab(text: l10n.cancelled),
                  ],
                ),
              ),
              body: BlocBuilder<OrdersCubit, OrdersState>(
                builder: (context, state) {
                  if (customerId == 0) {
                    return Center(
                      child: DsEmptyState(
                        icon: Icons.lock_outline,
                        title: 'تسجيل الدخول مطلوب',
                        subtitle: 'الرجاء تسجيل الدخول لعرض وتتبع طلباتك',
                        buttonText: 'تسجيل الدخول',
                        onButtonPressed: () => context.push('/login'),
                      ),
                    );
                  }
                  if (state is OrdersLoading) {
                    return const Center(child: CircularProgressIndicator(color: AppTheme.primary));
                  } else if (state is OrdersError) {
                    return Center(
                      child: DsEmptyState(
                        icon: Icons.error_outline,
                        title: 'خطأ',
                        subtitle: state.message,
                        buttonText: l10n.retry,
                        onButtonPressed: () => context.read<OrdersCubit>().fetchOrders(),
                      ),
                    );
                  } else if (state is OrdersLoaded) {
                    final allOrders = state.orders;
                    return TabBarView(
                      children: [
                        _OrdersListView(orders: allOrders, l10n: l10n),
                        _OrdersListView(orders: allOrders.where((o) => o.status == 'pending' || o.status == 'on-hold' || o.status == 'processing').toList(), l10n: l10n),
                        _OrdersListView(orders: allOrders.where((o) => o.status == 'completed').toList(), l10n: l10n),
                        _OrdersListView(orders: allOrders.where((o) => o.status == 'cancelled' || o.status == 'refunded' || o.status == 'failed' || o.status == 'trash').toList(), l10n: l10n),
                      ],
                    );
                  }
                  return const SizedBox();
                },
              ),
            ),
          ),
        );
      },
    );
  }
}

class _OrdersListView extends StatelessWidget {
  final List<OrderModel> orders;
  final AppLocalizations l10n;

  const _OrdersListView({Key? key, required this.orders, required this.l10n}) : super(key: key);

  @override
  Widget build(BuildContext context) {
    if (orders.isEmpty) {
      return Center(
        child: DsEmptyState(
          icon: Icons.receipt_long_outlined,
          title: l10n.noOrdersYet,
          subtitle: l10n.noOrdersSubtitle,
        ),
      );
    }

    return RefreshIndicator(
      onRefresh: () => context.read<OrdersCubit>().fetchOrders(),
      color: AppTheme.primary,
      child: ListView.builder(
        padding: const EdgeInsets.all(AppDimensions.lg),
        itemCount: orders.length,
        itemBuilder: (context, index) {
          final order = orders[index];
          return _OrderCard(order: order, l10n: l10n);
        },
      ),
    );
  }
}

class _OrderCard extends StatelessWidget {
  final OrderModel order;
  final AppLocalizations l10n;

  const _OrderCard({Key? key, required this.order, required this.l10n}) : super(key: key);

  OrderStatus _mapStatus(String status) {
    switch (status.toLowerCase()) {
      case 'completed':
        return OrderStatus.completed;
      case 'cancelled':
        return OrderStatus.cancelled;
      case 'refunded':
        return OrderStatus.refunded;
      case 'failed':
        return OrderStatus.failed;
      case 'processing':
        return OrderStatus.processing;
      case 'on-hold':
        return OrderStatus.onHold;
      default:
        return OrderStatus.pending;
    }
  }

  String _statusLabel(String status, AppLocalizations l10n) {
    final isArabic = l10n.locale.languageCode == 'ar';
    switch (status.toLowerCase()) {
      case 'pending':
        return isArabic ? 'قيد الانتظار' : 'Pending';
      case 'processing':
        return isArabic ? 'قيد المعالجة' : 'Processing';
      case 'on-hold':
        return isArabic ? 'معلق' : 'On Hold';
      case 'completed':
        return isArabic ? 'مكتمل' : 'Completed';
      case 'cancelled':
        return isArabic ? 'ملغي' : 'Cancelled';
      case 'refunded':
        return isArabic ? 'مسترد' : 'Refunded';
      case 'failed':
        return isArabic ? 'فشل' : 'Failed';
      default:
        return status;
    }
  }

  String _formatDate(String dateStr) {
    try {
      final date = DateTime.parse(dateStr);
      return '${date.year}-${date.month.toString().padLeft(2, '0')}-${date.day.toString().padLeft(2, '0')}  ${date.hour.toString().padLeft(2, '0')}:${date.minute.toString().padLeft(2, '0')}';
    } catch (e) {
      return dateStr;
    }
  }

  @override
  Widget build(BuildContext context) {
    final isArabic = l10n.locale.languageCode == 'ar';
    final currency = isArabic ? 'ج.س' : 'SDG';

    return GestureDetector(
      onTap: () => context.push('/order-detail', extra: order),
      behavior: HitTestBehavior.opaque,
      child: Container(
        margin: const EdgeInsets.only(bottom: AppDimensions.md),
        decoration: BoxDecoration(
          color: Theme.of(context).cardColor,
          borderRadius: BorderRadius.circular(AppDimensions.radiusLg),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withOpacity(0.04),
            blurRadius: 12,
            offset: const Offset(0, 4),
          ),
        ],
      ),
      child: Padding(
        padding: const EdgeInsets.all(AppDimensions.lg),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // Header: Order # + Status Badge
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Text(
                  '${l10n.locale.languageCode == 'ar' ? 'طلب' : 'Order'} #${order.id}',
                  style: TextStyle(
                    fontSize: 16,
                    fontWeight: FontWeight.bold,
                    color: Theme.of(context).colorScheme.onSurface,
                  ),
                ),
                DsStatusBadge(
                  status: _mapStatus(order.status),
                  label: _statusLabel(order.status, l10n),
                ),
              ],
            ),
            const SizedBox(height: 12),

            // Date
            Row(
              children: [
                Icon(Icons.calendar_today_outlined, size: 14, color: Theme.of(context).textTheme.bodySmall?.color),
                const SizedBox(width: 6),
                Text(
                  _formatDate(order.dateCreated),
                  style: TextStyle(
                    fontSize: 13,
                    color: Theme.of(context).textTheme.bodySmall?.color,
                  ),
                ),
              ],
            ),
            const SizedBox(height: 8),

            // Items summary
            if (order.lineItems.isNotEmpty) ...[
              ...order.lineItems.take(3).map((item) {
                final name = item['name'] ?? '';
                final qty = item['quantity'] ?? 1;
                return Padding(
                  padding: const EdgeInsets.only(top: 4),
                  child: Row(
                    children: [
                      Icon(Icons.circle, size: 6, color: AppTheme.primary.withOpacity(0.6)),
                      const SizedBox(width: 8),
                      Expanded(
                        child: Text(
                          '$name × $qty',
                          style: TextStyle(
                            fontSize: 13,
                            color: Theme.of(context).colorScheme.onSurface.withOpacity(0.8),
                          ),
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                        ),
                      ),
                    ],
                  ),
                );
              }).toList(),
              if (order.lineItems.length > 3)
                Padding(
                  padding: const EdgeInsets.only(top: 4),
                  child: Text(
                    '+${order.lineItems.length - 3} منتجات أخرى',
                    style: TextStyle(fontSize: 12, color: AppTheme.textMuted),
                  ),
                ),
            ],

            const SizedBox(height: 12),
            // Divider
            Divider(color: Theme.of(context).dividerColor.withOpacity(0.3), height: 1),
            const SizedBox(height: 12),

            // Total
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Text(
                  l10n.total,
                  style: TextStyle(
                    fontSize: 14,
                    fontWeight: FontWeight.w600,
                    color: Theme.of(context).colorScheme.onSurface,
                  ),
                ),
                Text(
                  '${order.total} $currency',
                  style: const TextStyle(
                    fontSize: 18,
                    fontWeight: FontWeight.bold,
                    color: AppTheme.primary,
                  ),
                ),
              ],
            ),
          ],
        ),
      ),
      ),
    );
  }
}
