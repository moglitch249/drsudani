import 'package:flutter/material.dart';
import 'package:flutter_animate/flutter_animate.dart';
import 'package:go_router/go_router.dart';
import '../../../../core/theme/app_theme.dart';
import '../../../../core/theme/app_dimensions.dart';
import '../../data/models/order_model.dart';
import '../../../../core/l10n/app_localizations.dart';
import '../../../../core/widgets/ds_status_badge.dart';

class OrderDetailScreen extends StatelessWidget {
  final OrderModel order;

  const OrderDetailScreen({Key? key, required this.order}) : super(key: key);

  String _formatDate(String dateStr) {
    try {
      final date = DateTime.parse(dateStr);
      return '${date.year}-${date.month.toString().padLeft(2, '0')}-${date.day.toString().padLeft(2, '0')}  ${date.hour.toString().padLeft(2, '0')}:${date.minute.toString().padLeft(2, '0')}';
    } catch (e) {
      return dateStr;
    }
  }

  OrderStatus _mapStatus(String status) {
    switch (status) {
      case 'completed':
        return OrderStatus.completed;
      case 'cancelled':
      case 'refunded':
      case 'failed':
      case 'trash':
        return OrderStatus.cancelled;
      case 'processing':
        return OrderStatus.processing;
      default:
        return OrderStatus.pending;
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
      case 'trash': return 'محذوف';
      default: return status;
    }
  }

  Widget _getStatusMessageCard(BuildContext context, String status) {
    IconData icon;
    Color color;
    String title;
    String description;

    switch (status) {
      case 'completed':
        icon = Icons.check_circle_outline;
        color = Colors.green;
        title = 'اكتمل الطلب بنجاح!';
        description = 'تم تنفيذ طلبك بنجاح. شكراً لثقتك بنا وبدكتور سوداني! نتمنى لك وقتاً ممتعاً.';
        break;
      case 'processing':
        icon = Icons.hourglass_top_outlined;
        color = Colors.orange;
        title = 'جاري التنفيذ...';
        description = 'الطلب قيد المعالجة حالياً. يرجى التحلي بالصبر، سيتم تنفيذه في أسرع وقت ممكن.';
        break;
      case 'pending':
      case 'on-hold':
        icon = Icons.pause_circle_outline;
        color = Colors.orangeAccent;
        title = 'في قائمة الانتظار';
        description = 'طلبك في قائمة الانتظار وسيتم البدء في معالجته قريباً.';
        break;
      case 'cancelled':
      case 'refunded':
      case 'failed':
      case 'trash':
        icon = Icons.error_outline;
        color = Colors.red;
        title = 'تم إلغاء الطلب أو فشل';
        description = 'حدثت مشكلة أدت لعدم تنفيذ الطلب. تم استرداد الرصيد إلى محفظتك إذا تم الخصم. يرجى التواصل مع الدعم الفني إذا كنت بحاجة للمساعدة.';
        break;
      default:
        icon = Icons.info_outline;
        color = AppTheme.primary;
        title = 'معلومات الطلب';
        description = 'يتم متابعة حالة طلبك بشكل مستمر.';
    }

    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(AppDimensions.lg),
      decoration: BoxDecoration(
        color: color.withOpacity(0.1),
        borderRadius: BorderRadius.circular(AppDimensions.radiusLg),
        border: Border.all(color: color.withOpacity(0.3)),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(icon, color: color, size: 32),
          const SizedBox(width: AppDimensions.md),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  style: TextStyle(
                    fontSize: 16,
                    fontWeight: FontWeight.bold,
                    color: color,
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  description,
                  style: TextStyle(
                    fontSize: 13,
                    color: Theme.of(context).colorScheme.onSurface.withOpacity(0.8),
                    height: 1.5,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context)!;
    final isArabic = l10n.locale.languageCode == 'ar';
    final currency = isArabic ? 'ج.س' : 'SDG';

    return Scaffold(
      backgroundColor: Theme.of(context).scaffoldBackgroundColor,
      appBar: AppBar(
        title: Text('${isArabic ? 'طلب' : 'Order'} #${order.id}'),
        centerTitle: true,
        leading: IconButton(
          icon: const Icon(Icons.arrow_back),
          onPressed: () => context.pop(),
        ),
      ),
      body: SingleChildScrollView(
        physics: const BouncingScrollPhysics(),
        padding: const EdgeInsets.all(AppDimensions.lg),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // Status Card
            _getStatusMessageCard(context, order.status).animate().fadeIn(duration: 400.ms).slideY(begin: 0.1, end: 0),
            
            const SizedBox(height: AppDimensions.xl),
            
            // Order details summary
            Container(
              padding: const EdgeInsets.all(AppDimensions.lg),
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
              child: Column(
                children: [
                  _buildSummaryRow(context, 'رقم الطلب', '#${order.id}'),
                  const Divider(height: 24),
                  _buildSummaryRow(context, 'تاريخ الطلب', _formatDate(order.dateCreated)),
                  const Divider(height: 24),
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      Text('الحالة', style: TextStyle(color: Theme.of(context).colorScheme.onSurface.withOpacity(0.6))),
                      DsStatusBadge(status: _mapStatus(order.status), label: _statusLabel(order.status)),
                    ],
                  ),
                ],
              ),
            ).animate().fadeIn(delay: 100.ms).slideY(begin: 0.1, end: 0),

            const SizedBox(height: AppDimensions.xl),
            
            // Order Timeline
            _buildOrderTimeline(context, order.status).animate().fadeIn(delay: 150.ms).slideY(begin: 0.1, end: 0),

            const SizedBox(height: AppDimensions.xl),
            
            Text(
              'تفاصيل المنتجات',
              style: TextStyle(
                fontSize: 18,
                fontWeight: FontWeight.bold,
                color: Theme.of(context).colorScheme.onSurface,
              ),
            ).animate().fadeIn(delay: 200.ms),
            
            const SizedBox(height: AppDimensions.md),
            
            // Items List
            ...order.lineItems.map((item) {
              final name = item['name'] ?? '';
              final qty = item['quantity'] ?? 1;
              final subtotal = item['subtotal'] ?? '0';
              final List metaData = item['meta_data'] ?? [];

              return Container(
                margin: const EdgeInsets.only(bottom: AppDimensions.md),
                padding: const EdgeInsets.all(AppDimensions.md),
                decoration: BoxDecoration(
                  color: Theme.of(context).cardColor,
                  borderRadius: BorderRadius.circular(AppDimensions.radiusMd),
                  border: Border.all(color: Theme.of(context).dividerColor.withOpacity(0.1)),
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Expanded(
                          child: Text(
                            name,
                            style: TextStyle(
                              fontSize: 15,
                              fontWeight: FontWeight.bold,
                              color: Theme.of(context).colorScheme.onSurface,
                            ),
                          ),
                        ),
                        Text(
                          '$subtotal $currency',
                          style: const TextStyle(
                            fontSize: 15,
                            fontWeight: FontWeight.bold,
                            color: AppTheme.primary,
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 4),
                    Text(
                      'الكمية: $qty',
                      style: TextStyle(
                        fontSize: 13,
                        color: Theme.of(context).colorScheme.onSurface.withOpacity(0.6),
                      ),
                    ),
                    if (metaData.isNotEmpty) ...[
                      const SizedBox(height: 8),
                      const Divider(height: 1),
                      const SizedBox(height: 8),
                      ...metaData.map((meta) {
                        // Avoid showing internal woo-commerce meta (starts with _)
                        final key = meta['key']?.toString() ?? '';
                        if (key.startsWith('_')) return const SizedBox.shrink();
                        
                        // Map english keys to arabic for display if needed
                        String displayKey = key;
                        if (key.toLowerCase() == 'playerid' || key.toLowerCase() == 'player id') displayKey = 'معرف اللاعب';
                        if (key.toLowerCase() == 'email') displayKey = 'البريد الإلكتروني';

                        return Padding(
                          padding: const EdgeInsets.only(bottom: 4),
                          child: Row(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                '$displayKey: ',
                                style: TextStyle(
                                  fontSize: 12,
                                  fontWeight: FontWeight.bold,
                                  color: Theme.of(context).colorScheme.onSurface.withOpacity(0.7),
                                ),
                              ),
                              Expanded(
                                child: Text(
                                  meta['value']?.toString() ?? '',
                                  style: TextStyle(
                                    fontSize: 12,
                                    color: Theme.of(context).colorScheme.onSurface,
                                  ),
                                ),
                              ),
                            ],
                          ),
                        );
                      }).toList(),
                    ]
                  ],
                ),
              ).animate().fadeIn(delay: 300.ms).slideX(begin: 0.1, end: 0);
            }).toList(),
            
            const SizedBox(height: AppDimensions.md),
            
            // Total Footer
            Container(
              padding: const EdgeInsets.all(AppDimensions.lg),
              decoration: BoxDecoration(
                gradient: AppTheme.primaryGradient,
                borderRadius: BorderRadius.circular(AppDimensions.radiusLg),
                boxShadow: [AppDimensions.primaryGlow],
              ),
              child: Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  const Text(
                    'الإجمالي المدفوع',
                    style: TextStyle(
                      fontSize: 16,
                      fontWeight: FontWeight.bold,
                      color: Colors.white,
                    ),
                  ),
                  Text(
                    '${order.total} $currency',
                    style: const TextStyle(
                      fontSize: 20,
                      fontWeight: FontWeight.w900,
                      color: Colors.white,
                    ),
                  ),
                ],
              ),
            ).animate().fadeIn(delay: 400.ms).slideY(begin: 0.2, end: 0),
            
            const SizedBox(height: 40), // Bottom padding
          ],
        ),
      ),
    );
  }

  Widget _buildSummaryRow(BuildContext context, String label, String value) {
    return Row(
      mainAxisAlignment: MainAxisAlignment.spaceBetween,
      children: [
        Text(
          label,
          style: TextStyle(
            color: Theme.of(context).colorScheme.onSurface.withOpacity(0.6),
            fontSize: 14,
          ),
        ),
        Text(
          value,
          style: TextStyle(
            fontWeight: FontWeight.bold,
            color: Theme.of(context).colorScheme.onSurface,
            fontSize: 14,
          ),
        ),
      ],
    );
  }

  Widget _buildOrderTimeline(BuildContext context, String status) {
    final orderStatus = _mapStatus(status);
    
    bool isStep1Done = true;
    bool isStep2Done = orderStatus == OrderStatus.processing || orderStatus == OrderStatus.completed;
    bool isStep3Done = orderStatus == OrderStatus.completed;
    bool isCancelled = orderStatus == OrderStatus.cancelled;

    return Container(
      padding: const EdgeInsets.all(AppDimensions.lg),
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
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            'مراحل التنفيذ',
            style: TextStyle(
              fontSize: 16,
              fontWeight: FontWeight.bold,
              color: Theme.of(context).colorScheme.onSurface,
            ),
          ),
          const SizedBox(height: AppDimensions.lg),
          if (isCancelled) ...[
            _buildTimelineStep(context, 'تم استلام الطلب', true, isLast: false, isError: false),
            _buildTimelineStep(context, 'إلغاء الطلب / فشل', true, isLast: true, isError: true),
          ] else ...[
            _buildTimelineStep(context, 'تم استلام الطلب', isStep1Done, isLast: false),
            _buildTimelineStep(context, 'جاري التنفيذ والتسليم', isStep2Done, isLast: false, isActive: orderStatus == OrderStatus.processing),
            _buildTimelineStep(context, 'اكتمل بنجاح', isStep3Done, isLast: true, isActive: orderStatus == OrderStatus.completed),
          ]
        ],
      ),
    );
  }

  Widget _buildTimelineStep(BuildContext context, String title, bool isCompleted, {bool isLast = false, bool isActive = false, bool isError = false}) {
    Color nodeColor = isError ? Colors.red : (isCompleted ? AppTheme.success : Colors.grey.withOpacity(0.3));
    if (isActive && !isCompleted && !isError) nodeColor = Colors.orange; 

    Widget circle = Container(
      width: 20,
      height: 20,
      decoration: BoxDecoration(
        color: (isActive && !isCompleted) ? nodeColor.withOpacity(0.2) : nodeColor,
        shape: BoxShape.circle,
        border: (isActive && !isCompleted) ? Border.all(color: nodeColor, width: 2) : null,
      ),
      child: (isCompleted && !isError) 
        ? const Icon(Icons.check, size: 12, color: Colors.white) 
        : (isError ? const Icon(Icons.close, size: 12, color: Colors.white) : null),
    );

    if (isActive && !isCompleted && !isError) {
      circle = circle.animate(onPlay: (controller) => controller.repeat(reverse: true))
          .scale(begin: const Offset(1, 1), end: const Offset(1.3, 1.3), duration: 800.ms)
          .fade(begin: 0.6, end: 1.0, duration: 800.ms);
    }

    Widget textWidget = Text(
      title,
      style: TextStyle(
        fontWeight: isActive || isCompleted ? FontWeight.bold : FontWeight.normal,
        color: isActive || isCompleted ? Theme.of(context).colorScheme.onSurface : Colors.grey,
      ),
    );

    if (isActive && !isCompleted && !isError) {
      textWidget = textWidget.animate(onPlay: (controller) => controller.repeat(reverse: true))
          .fade(begin: 0.5, end: 1.0, duration: 800.ms);
    }

    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Column(
          children: [
            circle,
            if (!isLast)
              Container(
                width: 2,
                height: 30,
                color: isCompleted && !isError ? AppTheme.success : Colors.grey.withOpacity(0.2),
              ),
          ],
        ),
        const SizedBox(width: AppDimensions.md),
        Padding(
          padding: const EdgeInsets.only(top: 0),
          child: textWidget,
        ),
      ],
    );
  }
}
