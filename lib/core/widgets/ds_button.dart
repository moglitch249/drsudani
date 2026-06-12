import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import '../theme/app_theme.dart';
import '../theme/app_dimensions.dart';

enum DsButtonVariant { primary, secondary, ghost, danger }

class DsButton extends StatelessWidget {
  final String label;
  final VoidCallback? onPressed;
  final bool isLoading;
  final IconData? icon;
  final double? width;
  final DsButtonVariant variant;

  const DsButton({
    Key? key,
    required this.label,
    required this.onPressed,
    this.isLoading = false,
    this.icon,
    this.width,
    this.variant = DsButtonVariant.primary,
  }) : super(key: key);

  @override
  Widget build(BuildContext context) {
    return Container(
      width: width ?? double.infinity,
      height: 52,
      decoration: _getDecoration(),
      child: Material(
        color: Colors.transparent,
        child: InkWell(
          borderRadius: BorderRadius.circular(AppDimensions.radiusFull),
          onTap: () {
            if (!isLoading && onPressed != null) {
              HapticFeedback.lightImpact();
              onPressed!();
            }
          },
          child: Center(
            child: isLoading
                ? const SizedBox(
                    width: 20,
                    height: 20,
                    child: CircularProgressIndicator(
                      color: Colors.white,
                      strokeWidth: 2,
                    ),
                  )
                : Row(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      if (icon != null) ...[
                        Icon(icon, color: _getTextColor(), size: 20),
                        const SizedBox(width: AppDimensions.sm),
                      ],
                      Text(
                        label,
                        style: Theme.of(context).textTheme.labelLarge?.copyWith(
                              color: _getTextColor(),
                            ),
                      ),
                    ],
                  ),
          ),
        ),
      ),
    );
  }

  BoxDecoration _getDecoration() {
    switch (variant) {
      case DsButtonVariant.primary:
        return BoxDecoration(
          gradient: onPressed == null ? null : AppTheme.primaryGradient,
          color: onPressed == null ? AppTheme.surfaceVariant : null,
          borderRadius: BorderRadius.circular(AppDimensions.radiusFull),
          boxShadow: (onPressed != null && !isLoading) ? [AppDimensions.primaryGlow] : null,
        );
      case DsButtonVariant.secondary:
        return BoxDecoration(
          color: Colors.transparent,
          borderRadius: BorderRadius.circular(AppDimensions.radiusFull),
          border: Border.all(color: AppTheme.primary, width: 1.5),
        );
      case DsButtonVariant.danger:
        return BoxDecoration(
          color: AppTheme.error,
          borderRadius: BorderRadius.circular(AppDimensions.radiusFull),
        );
      case DsButtonVariant.ghost:
        return BoxDecoration(
          color: Colors.transparent,
          borderRadius: BorderRadius.circular(AppDimensions.radiusFull),
        );
    }
  }

  Color _getTextColor() {
    if (onPressed == null) return AppTheme.textMuted;
    switch (variant) {
      case DsButtonVariant.primary:
      case DsButtonVariant.danger:
        return Colors.white;
      case DsButtonVariant.secondary:
        return AppTheme.primary;
      case DsButtonVariant.ghost:
        return AppTheme.textSecondary;
    }
  }
}
