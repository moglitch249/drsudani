import 'dart:ui';
import 'package:flutter/material.dart';
import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter_animate/flutter_animate.dart';
import '../theme/app_theme.dart';
import '../l10n/app_localizations.dart';

class DsProductCard extends StatefulWidget {
  final String id;
  final String imageUrl;
  final String name;
  final double startingPrice; // Keep for backward compatibility
  final double regularPrice;
  final double salePrice;
  final bool isPopular;
  final VoidCallback onTap;

  const DsProductCard({
    Key? key,
    required this.id,
    required this.imageUrl,
    required this.name,
    this.startingPrice = 0.0,
    this.regularPrice = 0.0,
    this.salePrice = 0.0,
    this.isPopular = false,
    required this.onTap,
  }) : super(key: key);

  @override
  State<DsProductCard> createState() => _DsProductCardState();
}

class _DsProductCardState extends State<DsProductCard> {
  bool _isPressed = false;

  @override
  Widget build(BuildContext context) {
    final isAr = AppLocalizations.of(context)?.locale.languageCode == 'ar';
    final hasDiscount = widget.salePrice > 0 && widget.regularPrice > widget.salePrice;

    return GestureDetector(
      onTapDown: (_) => setState(() => _isPressed = true),
      onTapUp: (_) {
        setState(() => _isPressed = false);
        widget.onTap();
      },
      onTapCancel: () => setState(() => _isPressed = false),
      child: AnimatedScale(
        scale: _isPressed ? 0.95 : 1.0,
        duration: const Duration(milliseconds: 150),
        curve: Curves.easeOutBack,
        child: Container(
          decoration: BoxDecoration(
            color: Theme.of(context).cardColor,
            borderRadius: BorderRadius.circular(20),
            boxShadow: [
              BoxShadow(
                color: AppTheme.primary.withOpacity(0.15),
                blurRadius: 12,
                offset: const Offset(0, 6),
              )
            ],
          ),
          clipBehavior: Clip.antiAlias,
          child: Stack(
            fit: StackFit.expand,
            children: [
              // Image covering the whole card
              Hero(
                tag: 'product_image_${widget.id}',
                child: CachedNetworkImage(
                  imageUrl: widget.imageUrl.isNotEmpty ? widget.imageUrl : 'error',
                  fit: BoxFit.cover, // Ensures the image fills the square beautifully
                  alignment: Alignment.topCenter, // Focus on the upper half
                  placeholder: (context, url) => Container(
                    color: Theme.of(context).scaffoldBackgroundColor,
                    child: const Center(
                      child: CircularProgressIndicator(color: AppTheme.primary),
                    ),
                  ),
                  errorWidget: (context, url, error) => Image.asset('assets/images/drsudani.png', fit: BoxFit.cover),
                ),
              ),
              
              // Gradient overlay at the bottom for aesthetic
              Positioned(
                bottom: 0, left: 0, right: 0,
                height: 40,
                child: Container(
                  decoration: BoxDecoration(
                    gradient: LinearGradient(
                      begin: Alignment.bottomCenter,
                      end: Alignment.topCenter,
                      colors: [Colors.black.withOpacity(0.3), Colors.transparent],
                    ),
                  ),
                ),
              ),

              // Discount Badge
              if (hasDiscount)
                Positioned(
                  top: 10,
                  left: isAr ? null : 10,
                  right: isAr ? 10 : null,
                  child: Container(
                    padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 4),
                    decoration: BoxDecoration(
                      color: AppTheme.error,
                      borderRadius: BorderRadius.circular(8),
                      boxShadow: [
                        BoxShadow(color: AppTheme.error.withOpacity(0.3), blurRadius: 4, offset: const Offset(0, 2)),
                      ],
                    ),
                    child: Text(
                      '${((widget.regularPrice - widget.salePrice) / widget.regularPrice * 100).round()}%',
                      style: const TextStyle(color: Colors.white, fontSize: 11, fontWeight: FontWeight.bold),
                    ),
                  ),
                ).animate().fade().scale(),

              // Popular Badge
              if (widget.isPopular)
                Positioned(
                  top: 10,
                  right: isAr ? null : 10,
                  left: isAr ? 10 : null,
                  child: Container(
                    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 5),
                    decoration: BoxDecoration(
                      color: AppTheme.primary,
                      borderRadius: BorderRadius.circular(12),
                      boxShadow: [
                        BoxShadow(color: AppTheme.primary.withOpacity(0.4), blurRadius: 6, offset: const Offset(0, 3)),
                      ],
                    ),
                    child: Row(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        const Icon(Icons.local_fire_department, color: Colors.white, size: 14),
                        const SizedBox(width: 4),
                        Text(
                          isAr ? 'الأكثر مبيعاً' : 'Popular',
                          style: const TextStyle(color: Colors.white, fontSize: 10, fontWeight: FontWeight.bold),
                        ),
                      ],
                    ),
                  ),
                ).animate().scale(duration: 400.ms, curve: Curves.easeOutBack),
            ],
          ),
        ),
      ),
    );
  }
}
