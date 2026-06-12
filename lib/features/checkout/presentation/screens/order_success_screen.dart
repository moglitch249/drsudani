import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:flutter_animate/flutter_animate.dart';
import 'package:audioplayers/audioplayers.dart';
import '../../../../core/theme/app_theme.dart';
import '../../../../core/theme/app_dimensions.dart';
import '../../../../core/widgets/ds_button.dart';
import '../../../../core/widgets/animated_check.dart';

class OrderSuccessScreen extends StatefulWidget {
  final String orderId;

  const OrderSuccessScreen({Key? key, required this.orderId}) : super(key: key);

  @override
  State<OrderSuccessScreen> createState() => _OrderSuccessScreenState();
}

class _OrderSuccessScreenState extends State<OrderSuccessScreen> {
  late AudioPlayer _audioPlayer;

  @override
  void initState() {
    super.initState();
    _audioPlayer = AudioPlayer();
    _playSound();
  }

  Future<void> _playSound() async {
    try {
      await _audioPlayer.play(AssetSource('sounds/success.ogg'));
    } catch (e) {
      debugPrint('Error playing sound: \$e');
    }
  }

  @override
  void dispose() {
    _audioPlayer.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Theme.of(context).scaffoldBackgroundColor,
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.all(AppDimensions.xl),
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            crossAxisAlignment: CrossAxisAlignment.center,
            children: [
              const Spacer(),
              
              // Animated Success Checkmark (Video-like)
              Container(
                padding: const EdgeInsets.all(24),
                decoration: BoxDecoration(
                  color: AppTheme.success.withOpacity(0.1),
                  shape: BoxShape.circle,
                ),
                child: const AnimatedCheck(
                  size: 100,
                  color: AppTheme.success,
                ),
              ).animate()
               .scale(duration: 600.ms, curve: Curves.easeOutBack)
               .fadeIn(duration: 600.ms),
              
              const SizedBox(height: AppDimensions.xl),
              
              // Title
              Text(
                'تمت العملية بنجاح!',
                style: Theme.of(context).textTheme.headlineMedium?.copyWith(
                  fontWeight: FontWeight.bold,
                  color: AppTheme.primary,
                ),
                textAlign: TextAlign.center,
              ).animate().slideY(begin: 0.5, end: 0, duration: 500.ms, delay: 200.ms).fadeIn(),
              
              const SizedBox(height: AppDimensions.md),
              
              // Subtitle
              Text(
                'شكراً لثقتكم بنا. تم استلام طلبكم وجاري معالجته الآن.',
                style: Theme.of(context).textTheme.bodyLarge?.copyWith(
                  color: Theme.of(context).colorScheme.onSurface.withOpacity(0.7),
                ),
                textAlign: TextAlign.center,
              ).animate().slideY(begin: 0.5, end: 0, duration: 500.ms, delay: 300.ms).fadeIn(),
              
              const SizedBox(height: AppDimensions.xl),
              
              // Order Details Card
              Container(
                padding: const EdgeInsets.all(AppDimensions.lg),
                decoration: BoxDecoration(
                  color: Theme.of(context).cardColor,
                  borderRadius: BorderRadius.circular(AppDimensions.radiusLg),
                  border: Border.all(color: AppTheme.primary.withOpacity(0.2)),
                  boxShadow: [
                    BoxShadow(
                      color: AppTheme.primary.withOpacity(0.05),
                      blurRadius: 15,
                      offset: const Offset(0, 5),
                    )
                  ]
                ),
                child: Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          'رقم الطلب',
                          style: TextStyle(
                            color: Theme.of(context).colorScheme.onSurface.withOpacity(0.5),
                            fontSize: 12,
                          ),
                        ),
                        const SizedBox(height: 4),
                        Text(
                          '#\${widget.orderId}',
                          style: const TextStyle(
                            fontWeight: FontWeight.bold,
                            fontSize: 18,
                          ),
                        ),
                      ],
                    ),
                    Column(
                      crossAxisAlignment: CrossAxisAlignment.end,
                      children: [
                        Text(
                          'حالة الطلب',
                          style: TextStyle(
                            color: Theme.of(context).colorScheme.onSurface.withOpacity(0.5),
                            fontSize: 12,
                          ),
                        ),
                        const SizedBox(height: 4),
                        Container(
                          padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                          decoration: BoxDecoration(
                            color: Colors.orange.withOpacity(0.1),
                            borderRadius: BorderRadius.circular(12),
                          ),
                          child: const Text(
                            'قيد المعالجة',
                            style: TextStyle(
                              color: Colors.orange,
                              fontWeight: FontWeight.bold,
                              fontSize: 14,
                            ),
                          ),
                        ),
                      ],
                    ),
                  ],
                ),
              ).animate().slideY(begin: 0.5, end: 0, duration: 500.ms, delay: 400.ms).fadeIn(),
              
              const Spacer(),
              
              // Actions
              DsButton(
                text: 'تتبع الطلب',
                onPressed: () {
                  context.go('/orders');
                },
                isFullWidth: true,
              ).animate().slideY(begin: 0.5, end: 0, duration: 500.ms, delay: 500.ms).fadeIn(),
              
              const SizedBox(height: AppDimensions.md),
              
              DsButton(
                text: 'العودة للرئيسية',
                onPressed: () {
                  context.go('/home');
                },
                isFullWidth: true,
                isOutlined: true,
              ).animate().slideY(begin: 0.5, end: 0, duration: 500.ms, delay: 600.ms).fadeIn(),
            ],
          ),
        ),
      ),
    );
  }
}
