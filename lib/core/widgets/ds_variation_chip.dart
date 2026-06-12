import 'package:flutter/material.dart';
import '../theme/app_theme.dart';
import '../theme/app_dimensions.dart';
import '../theme/app_typography.dart';
import 'package:flutter_localizations/flutter_localizations.dart';

class DsVariationChip extends StatelessWidget {
  final String label;
  final double price;
  final bool isSelected;
  final bool isBestValue;
  final VoidCallback onTap;

  const DsVariationChip({
    Key? key,
    required this.label,
    required this.price,
    required this.isSelected,
    required this.onTap,
    this.isBestValue = false,
  }) : super(key: key);

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: onTap,
      child: AnimatedScale(
        scale: isSelected ? 1.05 : 1.0,
        duration: const Duration(milliseconds: 200),
        curve: Curves.easeOut,
        child: AnimatedContainer(
          duration: const Duration(milliseconds: 200),
          constraints: const BoxConstraints(minWidth: 80),
          padding: const EdgeInsets.symmetric(
            horizontal: AppDimensions.md,
            vertical: 10,
          ),
          decoration: BoxDecoration(
            color: isSelected ? null : Theme.of(context).cardColor,
            gradient: isSelected ? AppTheme.primaryGradient : null,
            borderRadius: BorderRadius.circular(AppDimensions.radiusMd),
            border: isSelected
                ? null
                : Border.all(color: Theme.of(context).dividerColor, width: 1),
            boxShadow: isSelected ? [AppDimensions.primaryGlow] : null,
          ),
          child: Stack(
            clipBehavior: Clip.none,
            children: [
              Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.center,
                children: [
                  Text(
                    label,
                    style: Theme.of(context).textTheme.titleSmall?.copyWith(
                          color: isSelected ? Colors.white : Theme.of(context).colorScheme.onSurface,
                          fontWeight: FontWeight.bold,
                        ),
                    textAlign: TextAlign.center,
                  ),
                  const SizedBox(height: AppDimensions.xs),
                  Text(
                    '${price.toStringAsFixed(2)} SDG',
                    style: AppTypography.priceSmallStyle.copyWith(
                      color: isSelected ? Colors.white : AppTheme.gold,
                      fontSize: 12,
                    ),
                    textAlign: TextAlign.center,
                  ),
                ],
              ),
              if (isBestValue)
                Positioned(
                  top: -16,
                  right: -10,
                  child: Container(
                    padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                    decoration: BoxDecoration(
                      color: AppTheme.gold,
                      borderRadius: BorderRadius.circular(AppDimensions.radiusSm),
                    ),
                    child: const Text(
                      'Best Value', // Can be localized later
                      style: TextStyle(
                        color: Colors.black,
                        fontSize: 10,
                        fontWeight: FontWeight.bold,
                      ),
                    ),
                  ),
                ),
            ],
          ),
        ),
      ),
    );
  }
}
