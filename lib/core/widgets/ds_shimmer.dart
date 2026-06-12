import 'package:flutter/material.dart';
import 'package:shimmer/shimmer.dart';
import '../theme/app_theme.dart';
import '../theme/app_dimensions.dart';

class DsShimmer extends StatelessWidget {
  final double width;
  final double height;
  final double borderRadius;

  const DsShimmer({
    Key? key,
    required this.width,
    required this.height,
    this.borderRadius = AppDimensions.radiusMd,
  }) : super(key: key);

  @override
  Widget build(BuildContext context) {
    return Shimmer.fromColors(
      baseColor: AppTheme.surfaceVariant,
      highlightColor: AppTheme.surfaceVariant.withOpacity(0.5),
      child: Container(
        width: width,
        height: height,
        decoration: BoxDecoration(
          color: AppTheme.surfaceVariant,
          borderRadius: BorderRadius.circular(borderRadius),
        ),
      ),
    );
  }
}

class DsProductCardShimmer extends StatelessWidget {
  const DsProductCardShimmer({Key? key}) : super(key: key);

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: BoxDecoration(
        color: AppTheme.surfaceVariant,
        borderRadius: BorderRadius.circular(AppDimensions.radiusLg),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const AspectRatio(
            aspectRatio: 1,
            child: DsShimmer(
              width: double.infinity,
              height: double.infinity,
              borderRadius: AppDimensions.radiusLg,
            ),
          ),
          Padding(
            padding: const EdgeInsets.all(AppDimensions.sm),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const DsShimmer(width: double.infinity, height: 16),
                const SizedBox(height: AppDimensions.sm),
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: const [
                    DsShimmer(width: 60, height: 16),
                    DsShimmer(width: 24, height: 24, borderRadius: AppDimensions.radiusFull),
                  ],
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
