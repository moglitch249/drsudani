import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:go_router/go_router.dart';
import 'package:flutter_animate/flutter_animate.dart';
import 'package:confetti/confetti.dart';
import '../../../../core/theme/app_theme.dart';
import '../../../../core/theme/app_dimensions.dart';
import '../../../../core/widgets/ds_button.dart';
import '../../../../core/widgets/animated_check.dart';
import '../../../../core/l10n/app_localizations.dart';

class OrderSuccessScreen extends StatefulWidget {
  final String orderId;
  final Map<String, dynamic>? extraData;

  const OrderSuccessScreen({Key? key, required this.orderId, this.extraData}) : super(key: key);

  @override
  State<OrderSuccessScreen> createState() => _OrderSuccessScreenState();
}

class _OrderSuccessScreenState extends State<OrderSuccessScreen> {
  late ConfettiController _confettiController;

  @override
  void initState() {
    super.initState();
    _confettiController = ConfettiController(duration: const Duration(seconds: 3));
    _playSoundAndHaptic();
    _confettiController.play();
  }

  Future<void> _playSoundAndHaptic() async {
    try {
      // إهتزاز قوي وصوت نظامي كبديل عن ملف الصوت التالف لمنع الكراش
      HapticFeedback.heavyImpact();
      SystemSound.play(SystemSoundType.click);
      await Future.delayed(const Duration(milliseconds: 200));
      HapticFeedback.heavyImpact();
    } catch (e) {
      debugPrint('Error playing haptics: $e');
    }
  }

  @override
  void dispose() {
    _confettiController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context)!;
    final isArabic = l10n.locale.languageCode == 'ar';
    final currency = isArabic ? 'ج.س' : 'SDG';

    final double oldBalance = widget.extraData?['oldBalance'] ?? 0.0;
    final double newBalance = widget.extraData?['newBalance'] ?? 0.0;
    final String phone = widget.extraData?['phone'] ?? '';
    
    final now = DateTime.now();
    final dateStr = '${now.year}-${now.month.toString().padLeft(2, '0')}-${now.day.toString().padLeft(2, '0')}';
    final timeStr = '${now.hour.toString().padLeft(2, '0')}:${now.minute.toString().padLeft(2, '0')}';

    return Scaffold(
      backgroundColor: Theme.of(context).scaffoldBackgroundColor,
      body: Stack(
        alignment: Alignment.topCenter,
        children: [
          SafeArea(
            child: SingleChildScrollView(
              padding: const EdgeInsets.all(AppDimensions.xl),
              child: Column(
                mainAxisAlignment: MainAxisAlignment.center,
                crossAxisAlignment: CrossAxisAlignment.center,
                children: [
                  const SizedBox(height: 40),
                  
                  // Animated Success Checkmark
                  Container(
                    padding: const EdgeInsets.all(24),
                    decoration: BoxDecoration(
                      color: AppTheme.success.withOpacity(0.1),
                      shape: BoxShape.circle,
                    ),
                    child: const AnimatedCheck(
                      size: 80,
                      color: AppTheme.success,
                    ),
                  ).animate()
                   .scale(duration: 600.ms, curve: Curves.easeOutBack)
                   .fadeIn(duration: 600.ms),
                  
                  const SizedBox(height: AppDimensions.xl),
                  
                  // Title
                  Text(
                    isArabic ? 'تمت العملية بنجاح!' : 'Transaction Successful!',
                    style: Theme.of(context).textTheme.headlineMedium?.copyWith(
                      fontWeight: FontWeight.bold,
                      color: AppTheme.primary,
                    ),
                    textAlign: TextAlign.center,
                  ).animate().slideY(begin: 0.5, end: 0, duration: 500.ms, delay: 200.ms).fadeIn(),
                  
                  const SizedBox(height: AppDimensions.sm),
                  
                  // Subtitle
                  Text(
                    isArabic 
                      ? 'شكراً لثقتكم بنا. تم استلام طلبكم وجاري معالجته الآن.'
                      : 'Thank you for your trust. Your order has been received and is being processed.',
                    style: Theme.of(context).textTheme.bodyLarge?.copyWith(
                      color: Theme.of(context).colorScheme.onSurface.withOpacity(0.7),
                    ),
                    textAlign: TextAlign.center,
                  ).animate().slideY(begin: 0.5, end: 0, duration: 500.ms, delay: 300.ms).fadeIn(),
                  
                  const SizedBox(height: AppDimensions.xxl),
                  
                  // Detailed Receipt Card
                  Container(
                    padding: const EdgeInsets.all(AppDimensions.xl),
                    decoration: BoxDecoration(
                      color: Theme.of(context).cardColor,
                      borderRadius: BorderRadius.circular(AppDimensions.radiusLg),
                      border: Border.all(color: AppTheme.primary.withOpacity(0.1)),
                      boxShadow: [
                        BoxShadow(
                          color: AppTheme.primary.withOpacity(0.04),
                          blurRadius: 20,
                          offset: const Offset(0, 10),
                        )
                      ]
                    ),
                    child: Column(
                      children: [
                        _buildReceiptRow(context, isArabic ? 'رقم الطلب' : 'Order ID', '#${widget.orderId}', isBold: true),
                        const SizedBox(height: 16),
                        _buildReceiptRow(context, isArabic ? 'حالة الطلب' : 'Status', isArabic ? 'قيد الانتظار' : 'Pending', valueColor: Colors.orange),
                        const SizedBox(height: 16),
                        _buildReceiptRow(context, isArabic ? 'التاريخ' : 'Date', dateStr),
                        const SizedBox(height: 16),
                        _buildReceiptRow(context, isArabic ? 'الوقت' : 'Time', timeStr),
                        if (phone.isNotEmpty) ...[
                          const SizedBox(height: 16),
                          _buildReceiptRow(context, isArabic ? 'رقم الهاتف' : 'Phone', phone),
                        ],
                        
                        const Padding(
                          padding: EdgeInsets.symmetric(vertical: 24),
                          child: Row(
                            children: [
                              Expanded(child: Text('----------------------------------------------------', maxLines: 1, overflow: TextOverflow.clip, style: TextStyle(color: Colors.grey))),
                            ],
                          ),
                        ),

                        Container(
                          padding: const EdgeInsets.all(12),
                          decoration: BoxDecoration(
                            color: Theme.of(context).scaffoldBackgroundColor,
                            borderRadius: BorderRadius.circular(8),
                          ),
                          child: Column(
                            children: [
                              _buildReceiptRow(context, isArabic ? 'الرصيد السابق' : 'Old Balance', '${oldBalance.toStringAsFixed(2)} $currency'),
                              const SizedBox(height: 8),
                              _buildReceiptRow(context, isArabic ? 'الرصيد المتبقي' : 'New Balance', '${newBalance.toStringAsFixed(2)} $currency', isBold: true, valueColor: AppTheme.primary),
                            ],
                          ),
                        ),
                      ],
                    ),
                  ).animate().slideY(begin: 0.5, end: 0, duration: 500.ms, delay: 400.ms).fadeIn(),
                  
                  const SizedBox(height: AppDimensions.xxl),
                  
                  // Actions
                  DsButton(
                    label: isArabic ? 'تتبع الطلب' : 'Track Order',
                    width: double.infinity,
                    onPressed: () {
                      context.go('/orders');
                    },
                  ).animate().slideY(begin: 0.5, end: 0, duration: 500.ms, delay: 500.ms).fadeIn(),
                  
                  const SizedBox(height: AppDimensions.md),
                  
                  DsButton(
                    label: isArabic ? 'العودة للرئيسية' : 'Back to Home',
                    width: double.infinity,
                    onPressed: () {
                      context.go('/home');
                    },
                    variant: DsButtonVariant.secondary,
                  ).animate().slideY(begin: 0.5, end: 0, duration: 500.ms, delay: 600.ms).fadeIn(),
                  
                  const SizedBox(height: 40),
                ],
              ),
            ),
          ),
          ConfettiWidget(
            confettiController: _confettiController,
            blastDirectionality: BlastDirectionality.explosive,
            shouldLoop: false,
            colors: const [Colors.green, Colors.blue, Colors.pink, Colors.orange, Colors.purple],
            gravity: 0.1,
            emissionFrequency: 0.05,
          ),
        ],
      ),
    );
  }

  Widget _buildReceiptRow(BuildContext context, String label, String value, {bool isBold = false, Color? valueColor}) {
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
            fontWeight: isBold ? FontWeight.bold : FontWeight.w600,
            fontSize: isBold ? 16 : 14,
            color: valueColor ?? Theme.of(context).colorScheme.onSurface,
          ),
        ),
      ],
    );
  }
}
