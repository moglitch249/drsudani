import 'package:flutter/material.dart';
import '../theme/app_theme.dart';
import '../theme/app_dimensions.dart';

enum OrderStatus { pending, processing, completed, cancelled, refunded, failed, onHold }

class DsStatusBadge extends StatelessWidget {
  final OrderStatus status;
  final String label;

  const DsStatusBadge({
    Key? key,
    required this.status,
    required this.label,
  }) : super(key: key);

  @override
  Widget build(BuildContext context) {
    Color bgColor;
    Color textColor;

    switch (status) {
      case OrderStatus.pending:
      case OrderStatus.onHold:
        bgColor = AppTheme.warning.withOpacity(0.2);
        textColor = AppTheme.warning;
        break;
      case OrderStatus.processing:
        bgColor = AppTheme.primary.withOpacity(0.2);
        textColor = AppTheme.primary;
        break;
      case OrderStatus.completed:
        bgColor = AppTheme.success.withOpacity(0.2);
        textColor = AppTheme.success;
        break;
      case OrderStatus.cancelled:
      case OrderStatus.failed:
        bgColor = AppTheme.error.withOpacity(0.2);
        textColor = AppTheme.error;
        break;
      case OrderStatus.refunded:
        bgColor = Colors.grey.withOpacity(0.2);
        textColor = Colors.grey.shade700;
        break;
    }

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
      decoration: BoxDecoration(
        color: bgColor,
        borderRadius: BorderRadius.circular(AppDimensions.radiusFull),
      ),
      child: Text(
        label,
        style: Theme.of(context).textTheme.labelSmall?.copyWith(
              color: textColor,
              fontWeight: FontWeight.bold,
            ),
      ),
    );
  }
}
